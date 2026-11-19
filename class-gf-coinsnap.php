<?php

if (!defined( 'ABSPATH' )){
    exit;
}
use Coinsnap\Client\Webhook;
GFForms::include_payment_addon_framework();

class CoinsnapGF extends GFPaymentAddOn {
    
    private static $_instance = null;
    protected $_version = COINSNAPGF_VERSION;
    protected $_min_gravityforms_version = COINSNAPGF_MIN_VERSION;
    protected $_slug = 'gravityforms_coinsnap';
    protected $_path = 'gravityforms_coinsnap/coinsnap.php';
    protected $_full_path = __FILE__;
    protected $_url = 'https://www.gravityforms.com';
    protected $_title = 'Bitcoin payment for Gravity Forms';
    protected $_short_title = 'Coinsnap';
    protected $_supports_callbacks = true;
    protected $_capabilities = array('gravityforms_coinsnap', 'gravityforms_coinsnap_uninstall');    
    protected $_capabilities_settings_page = 'gravityforms_coinsnap';    
    protected $_capabilities_form_settings = 'gravityforms_coinsnap';
    protected $_capabilities_uninstall = 'gravityforms_coinsnap_uninstall';
    protected $_enable_rg_autoupgrade = false;
    protected $_config= [];
    public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];
    public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing'];	 
    
    public function __construct()
    {
        parent::__construct();
        $this->_config = get_option( 'gravityformsaddon_gravityforms_coinsnap_settings' );
        if (!is_array($this->_config)) {
            $this->_config = [];
        }
        
        if (is_admin()) {
            add_action( 'admin_enqueue_scripts', [ $this, 'connectionCheckScript' ] );
            add_action( 'wp_ajax_coinsnap_connection_handler', [$this, 'coinsnapConnectionHandler'] );
            add_action( 'wp_ajax_btcpay_server_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
            add_action( 'admin_notices', [$this, 'showNotices'] );
        }
        else {
            add_action('gform_validation', [ $this, 'coinsnapgf_payment_validation']);
        }
        
        // Adding template redirect handling for coinsnap-for-gravity-forms-btcpay-settings-callback.
        add_action( 'template_redirect', [$this, 'btcpaySettingsCallback'] );
    }

    // BTCPay posts the new key here from the admin's browser. The login cookie is usually not sent
    // with that cross-site POST, so the proof is the one-time state handed out by btcpayApiUrlHandler().
    public function btcpaySettingsCallback() {
        global $wp_query;

        // Only continue on a coinsnap-for-gravity-forms-btcpay-settings-callback request.
        if (!isset( $wp_query->query_vars['coinsnap-for-gravity-forms-btcpay-settings-callback'])) {
            return;
        }

        $notice = new \Coinsnap\Util\Notice();
        $CoinsnapBTCPaySettingsUrl = admin_url('admin.php?page=gf_settings&subview=gravityforms_coinsnap');

        // One use, even if a later check fails.
        $state = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['state'])) : '';
        $stateHost = ($state !== '') ? get_transient('coinsnapgf_btcpay_state_' . $state) : false;
        if ($state !== '') {
            delete_transient('coinsnapgf_btcpay_state_' . $state);
        }
        // The server URL is the one the admin entered when starting the wizard, never one from this request.
        $btcpay_server_url = is_string($stateHost) ? $stateHost : '';
        if ($btcpay_server_url === '') {
            status_header(403);
            exit();
        }

        $apiKey = isset($_POST['apiKey']) ? sanitize_text_field(wp_unslash($_POST['apiKey'])) : '';
        // Permissions and store come from BTCPay itself; whatever the POST claims about them is ignored.
        $storeId = ($apiKey !== '') ? \CoinsnapGF\BtcpayKey::verify($btcpay_server_url, $apiKey) : null;
        if ($storeId === null) {
            $notice->addNotice('error', __('The BTCPay API key was refused. Create a key for exactly one store with the requested permissions, or continue with manual setup.', 'coinsnap-for-gravity-forms'));
            wp_safe_redirect($CoinsnapBTCPaySettingsUrl);
            exit();
        }

        $this->coinsnap_settings_update([
            'btcpay_server_url' => $btcpay_server_url,
            'btcpay_api_key' => $apiKey,
            'btcpay_store_id' => $storeId,
            'coinsnap_provider' => 'btcpay'
        ]);

        $notice->addNotice('success', __('Successfully received api key and store id from BTCPay Server API. Please finish setup by saving this settings form.', 'coinsnap-for-gravity-forms'));

        // Register a webhook.
        if ($this->registerWebhook( $btcpay_server_url, $apiKey, $storeId )) {
            $notice->addNotice('success', __( 'Successfully registered a new webhook on BTCPay Server.', 'coinsnap-for-gravity-forms' ));
        }
        else {
            $notice->addNotice('error', __( 'Could not register a new webhook on the store.', 'coinsnap-for-gravity-forms' ));
        }

        wp_safe_redirect($CoinsnapBTCPaySettingsUrl);
        exit();
    }

    public function showNotices() {
        if ('gravityforms_coinsnap' === filter_input(INPUT_GET, 'subview', FILTER_UNSAFE_RAW) && current_user_can('manage_options')) {
            (new \Coinsnap\Util\Notice())->showNotices();
        }
    }

    public function coinsnap_settings_update($data){
        
        $form_data = $this->_config;
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option('gravityformsaddon_gravityforms_coinsnap_settings',$form_data);
        $this->_config = $form_data;
    }    
    
    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new CoinsnapGF();
        }
        
        return self::$_instance;
    }
    
    public function connectionCheckScript(){
        // The connection check can re-register the webhook; only administrators get its nonce.
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_register_style('coinsnapgf-backend-style', plugins_url('assets/css/coinsnapgf-backend-style.css',__FILE__),array(),COINSNAPGF_VERSION);
        wp_enqueue_style('coinsnapgf-backend-style');
        
        if('gravityforms_coinsnap' === filter_input(INPUT_GET,'subview',FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
            wp_enqueue_script('coinsnapgf-admin-fields', plugin_dir_url( __FILE__ ) . 'assets/js/adminFields.js',[ 'jquery' ],COINSNAPGF_VERSION,true);
        }
        
        wp_enqueue_script('coinsnapgf-connection-check', plugin_dir_url( __FILE__ ) . 'assets/js/connectionCheck.js',[ 'jquery' ],COINSNAPGF_VERSION,true);
        wp_localize_script('coinsnapgf-connection-check', 'coinsnapgf_ajax', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce'  => wp_create_nonce( 'coinsnapgf-ajax-nonce' ),
        ));
    }
    
    public function coinsnapConnectionHandler(){
        
        $_nonce = (string) filter_input(INPUT_POST,'_wpnonce',FILTER_UNSAFE_RAW);
        // Before any API call: the check can re-register the webhook and replace its secret.
        if (!wp_verify_nonce($_nonce, 'coinsnapgf-ajax-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not allowed'], 403);
        }
        
        if(empty($this->getApiUrl()) || empty($this->getApiKey())){
            $response = [
                'result' => false,
                'message' => __('Gravity Forms: empty gateway URL or API Key', 'coinsnap-for-gravity-forms')
            ];
            $this->sendJsonResponse($response);
        }
        
        $_provider = $this->get_payment_provider();
        $client = new \Coinsnap\Client\Invoice($this->getApiUrl(),$this->getApiKey());
        $store = new \Coinsnap\Client\Store($this->getApiUrl(),$this->getApiKey());
        $currency = get_option('rg_gforms_currency');
        
        if($_provider === 'btcpay'){
            try {
                $storePaymentMethods = $store->getStorePaymentMethods($this->getStoreId());

                if ($storePaymentMethods['code'] === 200) {
                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'bitcoin','calculation');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'lightning','calculation');
                    }
                }
            }
            catch (\Exception $e) {
                $response = [
                        'result' => false,
                        'message' => __('Gravity Forms: API connection is not established', 'coinsnap-for-gravity-forms')
                ];
                $this->sendJsonResponse($response);
            }
        }
        else {
            $checkInvoice = $client->checkPaymentData(0,$currency,'coinsnap','calculation');
        }
        
        if(isset($checkInvoice) && $checkInvoice['result']){
            $connectionData = __('Min order amount is', 'coinsnap-for-gravity-forms') .' '. $checkInvoice['min_value'].' '.$currency;
        }
        else {
            $connectionData = __('No payment method is configured', 'coinsnap-for-gravity-forms');
        }
        
        $_message_disconnected = ($_provider !== 'btcpay')? 
            __('Gravity Forms: Coinsnap server is disconnected', 'coinsnap-for-gravity-forms') :
            __('Gravity Forms: BTCPay server is disconnected', 'coinsnap-for-gravity-forms');
        $_message_connected = ($_provider !== 'btcpay')?
            __('Gravity Forms: Coinsnap server is connected', 'coinsnap-for-gravity-forms') : 
            __('Gravity Forms: BTCPay server is connected', 'coinsnap-for-gravity-forms');
        
        $response = ['result' => false,'message' => $_message_disconnected];

        try {
            $this_store = $store->getStore($this->getStoreId());
            
            if ($this_store->getData()['code'] !== 200) {
                $this->sendJsonResponse($response);
            }
            
            $webhookExists = $this->webhookExists( $this->getApiUrl(), $this->getApiKey(), $this->getStoreId() );

            if($webhookExists) {
                $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];
                $this->sendJsonResponse($response);
            }

            $webhook = $this->registerWebhook( $this->getApiUrl(), $this->getApiKey(), $this->getStoreId() );
            $response['result'] = (bool)$webhook;
            $response['message'] = $webhook ? $_message_connected.' ('.$connectionData.')' : $_message_disconnected.' (Webhook)';
        }
        catch (\Exception $e) {
            $response = [
                    'result' => false,
                    'message' => __('Gravity Forms: API connection is not established', 'coinsnap-for-gravity-forms')
            ];
        }

        $this->sendJsonResponse($response);
    }

    private function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
    
    /**
     * Handles the BTCPay server AJAX callback from the settings form.
     */
    public function btcpayApiUrlHandler() {
        $_nonce = (string) filter_input(INPUT_POST,'apiNonce',FILTER_UNSAFE_RAW);
        if ( !wp_verify_nonce( $_nonce, 'coinsnapgf-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }
        
        if ( current_user_can( 'manage_options' ) ) {
            $host = filter_var(trim((string) filter_input(INPUT_POST,'host',FILTER_UNSAFE_RAW)), FILTER_VALIDATE_URL);

            if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                wp_send_json_error("Error validating BTCPayServer URL.");
            }

            $permissions = \CoinsnapGF\BtcpayKey::NEEDED_PERMISSIONS;

            // One-time proof for the callback; it also remembers which server the key is for.
            $state = wp_generate_password(32, false);
            set_transient('coinsnapgf_btcpay_state_' . $state, $host, 15 * MINUTE_IN_SECONDS);

            try {
                // Create the redirect url to BTCPay instance.
                $url = \Coinsnap\Client\BTCPayApiKey::getAuthorizeUrl(
                    $host,
                    $permissions,
                    'GravityForms',
                    true,
                    true,
                    add_query_arg('state', $state, home_url('?coinsnap-for-gravity-forms-btcpay-settings-callback')),
                    null
                );

                // The host is saved only by the callback, once a key for it is verified; the stored key never goes to an unverified host.
                wp_send_json_success(['url' => $url]);
            }
            catch (\Throwable $e) {
                wp_send_json_error("Error processing Ajax request.");
            }
        }
        wp_send_json_error("Error processing Ajax request.");
    }
    
    function coinsnapgf_amount_validation( $amount, $currency ) {
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
        
        try {
            $_provider = $this->get_payment_provider();
            if($_provider === 'btcpay'){
                try {
                    $storePaymentMethods = $store->getStorePaymentMethods($this->getStoreId());

                    if ($storePaymentMethods['code'] === 200) {
                        if(!$storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                            $errorMessage = __( 'No payment method is configured on BTCPay server', 'coinsnap-for-gravity-forms' );
                            $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                        }
                    }
                    else {
                        $errorMessage = __( 'Error store loading. Wrong or empty Store ID', 'coinsnap-for-gravity-forms' );
                        $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                    }

                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ),'bitcoin');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ),'lightning');
                    }
                }
                catch (\Throwable $e){
                    $errorMessage = __( 'API connection is not established', 'coinsnap-for-gravity-forms' );
                    $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                }
            }
            else {
                $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ));
            }
        }
        catch (\Throwable $e){
            $errorMessage = __( 'API connection is not established', 'coinsnap-for-gravity-forms' );
            $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
        }
        return $checkInvoice;
    }
    
    public function coinsnapgf_payment_validation($validation_result){
        $form = $validation_result['form'];
        // Only forms that pay with Coinsnap. Other forms on the site are left alone.
        if (!$this->form_has_coinsnap_feed($form)) {
            return $validation_result;
        }
        GFCommon::log_debug( __METHOD__ . '(): running payment amount validation.' );
        // Never turn another field's failed validation into a pass.
        $is_valid = !empty($validation_result['is_valid']);
        $amount_ok = false;
        $currency  = get_option('rg_gforms_currency');

        foreach ( $form['fields'] as &$field ) {
            if ( $field->type == 'total' ) {
                $field_id = $field->id;
                $amount = filter_var(rgpost( 'input_'.$field_id ), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
                $checkInvoice = $this->coinsnapgf_amount_validation((float)$amount,strtoupper( $currency ));
                
                if($checkInvoice['result'] === true){
                    $amount_ok = true;
                }
                else {
                    if($checkInvoice['error'] === 'currencyError'){
                        $errorMessage = sprintf( 
                        /* translators: 1: Currency */
                        __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-for-gravity-forms' ), strtoupper( $currency ));
                    }      
                    elseif($checkInvoice['error'] === 'amountError'){
                        $errorMessage = sprintf( 
                        /* translators: 1: Amount, 2: Currency */
                        __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-for-gravity-forms' ), $checkInvoice['min_value'], strtoupper( $currency ));
                    }
                    else {
                        $errorMessage = $checkInvoice['error'];
                    }
                    $field->failed_validation = true;
                    $field->validation_message = $errorMessage; 
                }
            }
        }
        $validation_result['is_valid'] = $is_valid && $amount_ok;
        $validation_result['form'] = $form;
        return $validation_result;
    }

    // True if the form has an active Coinsnap feed.
    public function form_has_coinsnap_feed($form): bool {
        $form_id = (int) rgar($form, 'id');
        if ($form_id < 1) {
            return false;
        }
        foreach ((array) $this->get_feeds($form_id) as $feed) {
            if (!empty($feed['is_active'])) {
                return true;
            }
        }
        return false;
    }
    
    public function pre_init() {        
        add_action('wp', array('CoinsnapGF', 'maybe_thankyou_page'), 5); 
        parent::pre_init();
    }

    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();
        if ( ! $instance->is_gravityforms_supported()) {
            return;
        }
        if ($str = rgget('gf_coinsnap_return')) {
            $str = base64_decode($str);
            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                list($form_id, $lead_id) = explode('|', $query['ids']);
                $form = GFAPI::get_form($form_id);
                $lead = GFAPI::get_entry($lead_id);
                if ( ! class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }
                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);
                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }
                GFFormDisplay::$submission[$form_id] = array(
                    'is_confirmation'      => true,
                    'confirmation_message' => $confirmation,
                    'form'                 => $form,
                    'lead'                 => $lead
                );
            }
        }
    }

    public static function get_config_by_entry($entry){
        $coinsnap = CoinsnapGF::get_instance();
        $feed    = $coinsnap->get_payment_feed($entry);
        if (empty($feed)) {
            return false;
        }

        return $feed['addon_slug'] == $coinsnap->_slug ? $feed : false;
    }

    public static function get_config($form_id){
        $coinsnap = CoinsnapGF::get_instance();
        $feed    = $coinsnap->get_feeds($form_id);        
        if ( ! $feed) {
            return false;
        }

        return $feed[0]; 
    }

    public function init_frontend(){
        parent::init_frontend();
        add_filter('gform_disable_post_creation', array($this, 'delay_post'), 10, 3);
        add_filter('gform_disable_notification', array($this, 'delay_notification'), 10, 4);
    }
    
    public function billing_info_fields() {		

        return array(
            array(
                'name' => 'email',
		'label'      => __( 'Email address', 'coinsnap-for-gravity-forms' ),
		'field_type' => array( 'email' ),
                'default_value' => '2',
                'required'   => true,
            ),
            array(
		'name'       => 'full_name',
		'label'      => __( 'Full Name', 'coinsnap-for-gravity-forms' ),
		'field_type' => array( 'name', 'text' ),
                'default_value' => '1',
		'required'   => true,
            ),					
	);
    }
    
    public function plugin_settings_fields(){

        $sts = GFCommon::get_entry_payment_statuses();
        
        $statuses = [];
        foreach ($sts as $key => $val ){
            $statuses[] = array('label'=>$key, 'value'=>$val);
        }
        
        $settings_fields = array(
            array(
                'title'       => esc_html__('Coinsnap Setting', 'coinsnap-for-gravity-forms'),
                'description' => '<div id="coinsnapConnectionStatus"><span class="success"></span></div>',
                'fields'      => array(               
                    array(
                        'name'     => 'coinsnap_provider',
                        'label'    => __('Payment provider', 'coinsnap-for-gravity-forms'),
                        'type'     => 'select',
                        'choices'  => array(
                            array('label'   => 'Coinsnap','value' => 'coinsnap'),
                            array('label'   => 'BTCPay Server','value' => 'btcpay'),
			),                        
                        'class'    => 'option_select',
                        'tooltip'  =>  __('Select payment provider','coinsnap-for-gravity-forms')
                    ),                  
                    array(
                        'name'     => 'coinsnap_store_id',
                        'label'    => __('Store Id*', 'coinsnap-for-gravity-forms'),
                        'type'     => 'text',
                        'class'    => 'medium coinsnap',
                        'required' => false,
                        'tooltip'  =>  __('Enter Your Coinsnap Store ID.','coinsnap-for-gravity-forms')
                    ),
                    array(
                        'name'     => 'coinsnap_api_key',
                        'label'    => __('API Key*', 'coinsnap-for-gravity-forms'),
                        'type'     => 'text',
                        'input_type' => 'password',
                        'class'    => 'medium coinsnap',                
                        'required' => false,
                        'description'  => $this->apiKeyHint('coinsnap_api_key') . __( 'Coinsnap API requires authentication with an API key.<br/>Generate your API key by visiting the <a href="https://app.coinsnap.io/register" target="_blank">Coinsnap registration Page</a>.', 'coinsnap-for-gravity-forms' ),
                        'tooltip'  => __('Your Coinsnap API Key. You can find it on the store settings page on your Coinsnap Server.','coinsnap-for-gravity-forms'),
                    ),
                    array(
                        'name'     => 'btcpay_server_url',
                        'label'    => __('BTCPay server URL*', 'coinsnap-for-gravity-forms'),
                        'type'     => 'text',
                        'class'    => 'medium btcpay',
                        'required' => false,
                        'tooltip'  =>  __('Enter Your Coinsnap Store ID.','coinsnap-for-gravity-forms'),
                    ),
                    array(
                        'name'     => 'btcpay_store_id',
                        'label'    => '',
                        'type'     => 'text',
                        'class'    => 'medium btcpay',
                        'required' => false,
                        'tooltip'  =>  __('Your BTCPay Store ID. You can find it on the store settings page on your BTCPay Server.','coinsnap-for-gravity-forms'),
                        'description' => __( '<a href="#" class="btcpay-apikey-link">Check connection</a>', 'coinsnap-for-gravity-forms' ).'<br/><br/><button type="button" class="button btcpay-apikey-link" id="btcpay_wizard_button" target="_blank">'. __('Generate API key','coinsnap-for-gravity-forms').'</button><br/></br/>'.__('Store Id*', 'coinsnap-for-gravity-forms'),
                    ),
                    array(
                        'name'     => 'btcpay_api_key',
                        'label'    => __('API Key*', 'coinsnap-for-gravity-forms'),
                        'type'     => 'text',
                        'input_type' => 'password',
                        'class'    => 'medium btcpay',                
                        'required' => false,
                        'description' => $this->apiKeyHint('btcpay_api_key'),
                        'tooltip'  =>  __('Your BTCPay server API Key. You can generate it in your BTCPay Server.','coinsnap-for-gravity-forms')
                    ), 
                    array(
                        'name'     => 'coinsnap_autoredirect',
                        'type'     => 'checkbox',
                        'class'    => 'medium',                
                        'required' => false,
                        'choices' => array(
                            array(
                            'label'    => __('Auto-redirect after payment', 'coinsnap-for-gravity-forms'),
                            'name'          => 'coinsnap_autoredirect',
                            'tooltip'       => __('Auto-redirect after payment.','coinsnap-for-gravity-forms'),
                            'default_value' => 1,
                            )
                        )
                    ),   
                    array(
                        'name'     => 'coinsnap_returnurl',
                        'label'    => __('Return URL after payment', 'coinsnap-for-gravity-forms'),
                        'type'     => 'text',
                        'class'    => 'medium',                
                        'required' => false,
                        'description'  => __( 'After successful payment (default URL if blank)', 'coinsnap-for-gravity-forms' ),
                        'tooltip'  => __('Custom return URL after successful payment (default URL if blank)','coinsnap-for-gravity-forms'),
                    ),
                    array(
                        'name'     => 'coinsnap_expired_status',
                        'label'    => __('Expired Status', 'coinsnap-for-gravity-forms'),
                        'type'     => 'select',
                        'choices'  => $statuses,
                        'class'    => 'option_select',                
                        'default_value' => 'Failed',
                        'tooltip'  =>  __('Select Expired Status.','coinsnap-for-gravity-forms')
                    ),                  
                    array(
                        'name'     => 'coinsnap_settled_status',
                        'label'    => __('Settled Status', 'coinsnap-for-gravity-forms'),
                        'type'     => 'select',
                        'choices'  => $statuses,
                        'class'    => 'option_select',                
                        'default_value' => 'Paid',
                        'tooltip'  =>  __('Select Settled Status.','coinsnap-for-gravity-forms')
                    ),      
                    array(
                      'name'     => 'coinsnap_processing_status',
                      'label'    => __('Processing Status', 'coinsnap-for-gravity-forms'),
                      'type'     => 'select',
                      'choices'  => $statuses,
                      'class'    => 'option_select',                
                      'default_value' => 'Processing',
                      'tooltip'  =>  __('Select Processing Status.','coinsnap-for-gravity-forms')
                    ), 
                )
            )
        );

        return $settings_fields;
    }

    // "A key ending in ab12 is saved." The stored key itself is never printed.
    private function apiKeyHint(string $name): string {
        $stored = (string) ($this->_config[$name] ?? '');
        if ($stored === '') {
            return '';
        }
        return esc_html(sprintf(
            /* translators: 1: last characters of the stored API key */
            __('A key ending in %1$s is saved. Leave the field empty to keep it.', 'coinsnap-for-gravity-forms'),
            substr($stored, -4)
        )) . '<br/>';
    }

    private function isOwnSettingsPage(): bool {
        return is_admin() && rgget('page') === 'gf_settings' && rgget('subview') === $this->_slug;
    }

    // The settings form is filled from here: it gets empty key fields, never the stored keys.
    public function get_plugin_settings() {
        $settings = parent::get_plugin_settings();
        if (is_array($settings) && $this->isOwnSettingsPage()) {
            $settings['coinsnap_api_key'] = '';
            $settings['btcpay_api_key'] = '';
        }
        return $settings;
    }

    // Called by the settings form on save.
    public function update_plugin_settings($settings) {
        $stored = get_option('gravityformsaddon_gravityforms_coinsnap_settings');
        $settings = $this->keepStoredApiKeys(is_array($settings) ? $settings : [], is_array($stored) ? $stored : []);
        parent::update_plugin_settings($settings);
        $this->_config = $settings;
    }

    // An empty key field keeps the stored key, except a BTCPay key whose server URL changed.
    // A pasted BTCPay key is kept only if BTCPay says it is limited to the configured store.
    public function keepStoredApiKeys(array $input, array $stored): array {
        if (array_key_exists('coinsnap_api_key', $input) && trim((string) $input['coinsnap_api_key']) === '') {
            $input['coinsnap_api_key'] = (string) ($stored['coinsnap_api_key'] ?? '');
        }
        $storedUrl = (string) ($stored['btcpay_server_url'] ?? '');
        $storedKey = (string) ($stored['btcpay_api_key'] ?? '');
        $url = trim((string) ($input['btcpay_server_url'] ?? $storedUrl));
        if (array_key_exists('btcpay_api_key', $input) && trim((string) $input['btcpay_api_key']) === '') {
            // A key is never sent to a server it was not created on.
            $input['btcpay_api_key'] = ($url === $storedUrl) ? $storedKey : '';
        }
        if (array_key_exists('btcpay_api_key', $input) && ($input['coinsnap_provider'] ?? ($stored['coinsnap_provider'] ?? '')) === 'btcpay') {
            $key = trim((string) $input['btcpay_api_key']);
            $storeId = trim((string) ($input['btcpay_store_id'] ?? ($stored['btcpay_store_id'] ?? '')));
            $changed = $key !== $storedKey || $url !== $storedUrl || $storeId !== (string) ($stored['btcpay_store_id'] ?? '');
            if ($key !== '' && $changed && \CoinsnapGF\BtcpayKey::verify($url, $key, $storeId) === null) {
                $input['btcpay_api_key'] = '';
                (new \Coinsnap\Util\Notice())->addNotice('error', __('The BTCPay API key was not saved: it must be limited to this store, with only the permissions this plugin needs.', 'coinsnap-for-gravity-forms'));
            }
        }
        return $input;
    }

    public function feed_list_no_item_message(){
        $settings = $this->get_plugin_settings();
        if ( ! rgar($settings, 'gf_coinsnap_configured')) {
            return sprintf(
                /* translators: 1: Link to settings page opening tag 2: Link to settings page closing tag */
                __('To get started, configure your %1$sCoinsnap Settings%2$s!', 'coinsnap-for-gravity-forms'),
                '<a href="' . admin_url('admin.php?page=gf_settings&subview=' . $this->_slug) . '">',
                '</a>'
            );
        }
        else {
            return parent::feed_list_no_item_message();
        }
    }

    public function feed_settings_fields(){
        $feed_settings_fields = parent::feed_settings_fields();
        unset( $feed_settings_fields[0]['fields'][1]['choices'][2] );    
        $feed_settings_fields[0]['fields'][1]['default_value'] = 'product';  
        return apply_filters('gform_coinsnap_feed_settings_fields', $feed_settings_fields);
    }

    public function field_map_title(){
        return __('Coinsnap Field', 'coinsnap-for-gravity-forms');
    }

    public function option_choices(){
        return false;
    }

    public function redirect_url($feed, $submission_data, $form, $entry){        

        //Don't process redirect url if request is a Coinsnap return
        if(!rgempty(rgget('gf_coinsnap_return'))){
            return false;
        }
        
        $payment_amount = $submission_data['payment_amount'];
        $currency  = strtoupper(rgar( $entry, 'currency' ));
        
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        
        $checkInvoice = $this->coinsnapgf_amount_validation($payment_amount,$currency);
                
        if($checkInvoice['result'] === true){
            $amount = round($payment_amount, 2);
            $buyerEmail = $submission_data['email'];		
            $buyerName = $submission_data['full_name'];

            // An API error is not "no webhook": only a confirmed missing webhook is registered again.
            try {
                $webhookExists = $this->webhookExists($this->getApiUrl(),$this->getApiKey(), $this->getStoreId());
            }
            catch (\Throwable $e) {
                $webhookExists = null;
            }
            if ($webhookExists === false && ! $this->registerWebhook($this->getApiUrl(),$this->getApiKey(), $this->getStoreId())) {
                $this->log_error( __METHOD__ . '(): the webhook could not be registered.' );
                return false;
            }

            //updating lead's payment_status to Pending
            GFAPI::update_entry_property($entry['id'], 'payment_status', 'Pending');
            $return_mode = '2';

            $return_url = (!empty($this->_config['coinsnap_returnurl']))? $this->_config['coinsnap_returnurl'] : $this->return_url($form['id'], $entry['id']) . "&rm={$return_mode}";
            $invoice_no =  $entry['id'];
            
            // orderId / orderNumber stay the entry ID: the webhook matches the invoice to the entry by it.
            $mapped = [];
            foreach (['billingInformation_email', 'billingInformation_full_name'] as $key) {
                $fieldId = (string) rgar($feed['meta'] ?? [], $key);
                if ($fieldId !== '') {
                    $mapped[] = explode('.', $fieldId)[0];
                }
            }
            $metadata = \CoinsnapGF\InvoiceMetadata::forEntry([
                'entry_id' => $invoice_no,
                'form_id' => rgar($form, 'id'),
                'form_title' => (string) rgar($form, 'title'),
                'order_url' => admin_url('admin.php?page=gf_entries&view=entry&id=' . (int) rgar($form, 'id') . '&lid=' . (int) $invoice_no),
                'page_url' => (string) rgar($entry, 'source_url'),
                'version' => COINSNAPGF_VERSION,
                'language' => function_exists('determine_locale') ? determine_locale() : get_locale(),
                'name' => $buyerName,
                'email' => $buyerEmail,
                'mapped_field_ids' => $mapped,
                'fields' => $this->metadataFields($form, $entry),
                'line_items' => rgar($submission_data, 'line_items'),
                'discounts' => rgar($submission_data, 'discounts'),
            ]);
            
            if($this->get_payment_provider() === 'btcpay' && $currency !== 'BTC'){
                $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
                $btcpayCurrencies = $store -> getStoreCurrenciesRates($this->getStoreId(),array($currency));
                $isCurrency = true;
                if(!isset($btcpayCurrencies['result']['error']) && count($btcpayCurrencies['result']['currencies'])>0){
                        if(!isset($btcpayCurrencies['result']['currencies']['BTC_'.$currency])){
                            $isCurrency = false;
                        }
                }
                else {
                    $isCurrency = false;
                }
                    
                // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
                if( !$isCurrency ){
                        $currency = 'BTC';
                        $rate = 1/$checkInvoice['rate'];
                        $amountBTC = bcdiv(strval($amount), strval($rate), 8);
                        $amount = (float)$amountBTC;
                }
            }
                
            $camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat($amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);

            $redirectAutomatically = $this->_config['coinsnap_autoredirect'] ;
            $walletMessage = '';
            
            try {

                $csinvoice = $client->createInvoice(
                    $this->getStoreId(),  
                    strtoupper( $currency ),
                    $camount,
                    $invoice_no,
                    $buyerEmail,
                    $buyerName, 
                    $return_url,
                    COINSNAPGF_REFERRAL_CODE,     
                    $metadata,
                    $redirectAutomatically,
                    $walletMessage
                );	

                $invoiceData = $csinvoice->getData();
            }
            catch (\Throwable $e){
                $this->log_error( __METHOD__ . '(): the invoice could not be created (status ' . (int) $e->getCode() . ').' );
                return false;
            }

            if (empty($invoiceData['id']) || empty($invoiceData['checkoutLink'])) {
                return false;
            }

            // Only invoices recorded on the entry can pay it, and only for the amount and currency sent.
            $invoices = gform_get_meta($entry['id'], 'coinsnap_invoices');
            $invoices = is_array($invoices) ? $invoices : [];
            $invoices[(string) $invoiceData['id']] = [
                'amount' => (string) $camount,
                'currency' => strtoupper( $currency ),
            ];
            gform_update_meta($entry['id'], 'coinsnap_invoices', $invoices);

            return $invoiceData['checkoutLink'];
        }
        else {
            if($checkInvoice['error'] === 'currencyError'){
                $errorMessage = sprintf( 
                /* translators: 1: Currency */
                __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-for-gravity-forms' ), strtoupper( $currency ));
            }      
            elseif($checkInvoice['error'] === 'amountError'){
                $errorMessage = sprintf( 
                /* translators: 1: Amount, 2: Currency */
                __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-for-gravity-forms' ), $checkInvoice['min_value'], strtoupper( $currency ));
            }
            return false;
        }
    }

    // The form's fields with the entry's values as plain text, for the invoice metadata.
    private function metadataFields($form, $entry): array {
        $fields = [];
        foreach ((array) rgar($form, 'fields') as $field) {
            if (!is_object($field) || !isset($field->id)) {
                continue;
            }
            $inputs = [];
            foreach ((is_array($field->inputs ?? null) ? $field->inputs : []) as $input) {
                if (isset($input['id'])) {
                    $inputs[(string) $input['id']] = (string) rgar($entry, (string) $input['id']);
                }
            }
            $fields[] = [
                'id' => (string) $field->id,
                'type' => (string) ($field->type ?? ''),
                'input_type' => method_exists($field, 'get_input_type') ? (string) $field->get_input_type() : '',
                'label' => (string) ($field->label ?? ''),
                'value' => method_exists($field, 'get_value_export') ? $field->get_value_export($entry, (string) $field->id, true) : rgar($entry, (string) $field->id),
                'inputs' => $inputs,
                'admin_only' => ($field->visibility ?? '') === 'administrative' || !empty($field->adminOnly),
            ];
        }
        return $fields;
    }

    public function return_url($form_id, $lead_id){
        $pageURL     = GFCommon::is_ssl() ? 'https://' : 'http://';
        $server_port = apply_filters('gform_coinsnap_return_url_port', filter_input(INPUT_SERVER,'SERVER_PORT', FILTER_SANITIZE_NUMBER_INT));
        if ($server_port != '80') {
            $pageURL .= filter_input(INPUT_SERVER,'SERVER_NAME', FILTER_SANITIZE_FULL_SPECIAL_CHARS) . ':' . $server_port . filter_input(INPUT_SERVER,'REQUEST_URI', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $pageURL .= filter_input(INPUT_SERVER,'SERVER_NAME', FILTER_SANITIZE_FULL_SPECIAL_CHARS) . filter_input(INPUT_SERVER,'REQUEST_URI', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        $ids_query = "ids={$form_id}|{$lead_id}";
        $ids_query .= '&hash=' . wp_hash($ids_query);

        return add_query_arg('gf_coinsnap_return', base64_encode($ids_query), $pageURL);
    }

    public function delay_post($is_disabled, $form, $entry)
    {
        $feed            = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);
        if ( ! $feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return ! rgempty('delayPost', $feed['meta']);
    }

    public function delay_notification($is_disabled, $notification, $form, $entry)
    {
        $feed            = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);
        if ( ! $feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }
        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'],'selectedNotifications') : array();

        return isset($feed['meta']['delayNotification']) && in_array( $notification['id'], $selected_notifications) ? true : $is_disabled;
    }

    public function get_payment_feed($entry, $form = false)
    {
        $feed = parent::get_payment_feed($entry, $form);
        if (empty($feed) && ! empty($entry['id'])) {            
            $feed = $this->get_coinsnap_feed_by_entry($entry['id']);
        }

        return apply_filters('gform_coinsnap_get_payment_feed', $feed, $entry, $form);
    }

    public function get_coinsnap_feed_by_entry($entry_id){
        $feed_id = gform_get_meta($entry_id, 'coinsnap_feed_id');
        $feed    = $this->get_feed($feed_id);

        return ! empty($feed) ? $feed : false;
    }

    public function is_callback_valid(): bool {
        if (rgget('page') != 'gf_coinsnap_webhook') {
            return false;
        }
        $this->process_webhook();

        return true;
    }  
    
    public function process_webhook(){
        $rawPostData = (string) file_get_contents("php://input");
        $provider = $this->get_payment_provider();
        $header = ($provider === 'btcpay') ? 'HTTP_BTCPAY_SIG' : 'HTTP_X_COINSNAP_SIGNATURE';
        $signature = isset($_SERVER[$header]) ? (string) wp_unslash($_SERVER[$header]) : '';
        $webhook = get_option('gravityformsaddon_coinsnap_webhook');
        $secret = (is_array($webhook) && is_string($webhook['secret'] ?? null)) ? $webhook['secret'] : '';

        $handler = new \CoinsnapGF\Webhook\WebhookHandler($provider, $secret, [
            'settled' => (string) ($this->_config['coinsnap_settled_status'] ?? 'Paid'),
            'processing' => (string) ($this->_config['coinsnap_processing_status'] ?? 'Processing'),
            'expired' => (string) ($this->_config['coinsnap_expired_status'] ?? 'Failed'),
        ]);
        $status = $handler->handle($rawPostData, $signature, time());

        status_header($status);
        nocache_headers();
        echo ($status === 200) ? 'OK' : (($status === 401) ? 'Unauthorized' : 'Retry later');
        exit;
    }

    
    public function update_feed_id($old_feed_id, $new_feed_id){
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
            "UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='coinsnap_feed_id' AND meta_value=%s",
            $new_feed_id,
            $old_feed_id
            )
        );
    }
    
    public function update_payment_gateway(){
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='payment_gateway' AND meta_value='coinsnap'",
                $this->_slug
            )     
        );
    }
    
    public function get_payment_provider() {
        return ($this->_config['coinsnap_provider'] === 'btcpay')? 'btcpay' : 'coinsnap';
    }
    public function get_webhook_url() {		
        return get_bloginfo('url') . '/?page=gf_coinsnap_webhook';
    }
    public function getStoreId() {
        return ($this->get_payment_provider() === 'btcpay')? $this->_config['btcpay_store_id'] : $this->_config['coinsnap_store_id'];
    }
    public function getApiKey() {
        return ($this->get_payment_provider() === 'btcpay')? $this->_config['btcpay_api_key'] : $this->_config['coinsnap_api_key'];
    }
    public function getApiUrl() {
        return ($this->get_payment_provider() === 'btcpay')? $this->_config['btcpay_server_url'] : COINSNAP_SERVER_URL;
    }	

    // Throws when the API can't be asked; the caller must not treat that as "no webhook".
    public function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool {
        $whClient = new Webhook( $apiUrl, $apiKey );
        $stored = get_option( 'gravityformsaddon_coinsnap_webhook');

        if (is_array($stored) && !empty($stored['id']) && !empty($stored['secret'])) {
            try {
                $existing = $whClient->getWebhook( $storeId, (string) $stored['id'] );
                if (strpos((string) ($existing->getData()['url'] ?? ''), $this->get_webhook_url()) !== false) {
                    return true;
                }
            }
            catch (\Coinsnap\Exception\RequestException $e) {
                if (!in_array($e->getCode(), [400, 404], true)) {
                    throw $e;
                }
            }
        }
        // Remove webhooks of ours whose secret this site does not hold.
        foreach ($whClient->getWebhooks( $storeId ) as $webhook) {
            if (strpos((string) $webhook->getData()['url'], $this->get_webhook_url()) !== false) {
                try {
                    $whClient->deleteWebhook( $storeId, $webhook->getData()['id'] );
                }
                catch (\Throwable $e) {
                    $this->log_error( __METHOD__ . '(): a stale webhook could not be deleted.' );
                }
            }
        }
        return false;
    }

    public function registerWebhook(string $apiUrl, $apiKey, $storeId): bool {
        try {
            $whClient = new Webhook( $apiUrl, $apiKey );
            $webhook_events = ($this->get_payment_provider() === 'btcpay')? self::BTCPAY_WEBHOOK_EVENTS : self::COINSNAP_WEBHOOK_EVENTS;
            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $this->get_webhook_url(), //$url
                $webhook_events,   //$specificEvents
                null    //$secret: generated by the server
            )->getData();
        }
        catch (\Throwable $e) {
            $this->log_error( __METHOD__ . '(): the webhook could not be registered (status ' . (int) $e->getCode() . ').' );
            return false;
        }
        if (empty($webhook['id']) || empty($webhook['secret'])) {
            return false;
        }
        update_option(
            'gravityformsaddon_coinsnap_webhook',
            [
                'id' => $webhook['id'],
                'secret' => $webhook['secret'],
                'url' => $webhook['url'] ?? $this->get_webhook_url()
            ],
            false
        );
        return true;
    }


    public function uninstall() {
        // Only this add-on's own options; the generic coinsnap_* names may belong to other Coinsnap plugins.
        // The settings option itself is removed by parent::uninstall().
        delete_option( 'gravityformsaddon_coinsnap_webhook' );
        delete_option( 'coinsnapgf_records_since' );

        parent::uninstall();
      }
}
