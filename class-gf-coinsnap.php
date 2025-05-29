<?php

if (!defined( 'ABSPATH' )){
    exit;
}

GFForms::include_payment_addon_framework();

class CoinsnapGF extends GFPaymentAddOn {
    
    private static $_instance = null;
    protected $_version = COINSNAPGF_VERSION;
    protected $_min_gravityforms_version = COINSNAPGF_MIN_VERSION;
    protected $_slug = 'gravityforms_coinsnap';
    protected $_path = 'gravityforms_coinsnap/coinsnap.php';
    protected $_full_path = __FILE__;
    protected $_url = 'https://www.gravityforms.com';
    protected $_title = 'Coinsnap for Gravity Forms';
    protected $_short_title = 'Coinsnap';
    protected $_supports_callbacks = true;
    protected $_capabilities = array('gravityforms_coinsnap', 'gravityforms_coinsnap_uninstall');    
    protected $_capabilities_settings_page = 'gravityforms_coinsnap';    
    protected $_capabilities_form_settings = 'gravityforms_coinsnap';
    protected $_capabilities_uninstall = 'gravityforms_coinsnap_uninstall';
    protected $_enable_rg_autoupgrade = false;
    protected $_config= [];
    public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];	 
    
    public function __construct()
    {
        parent::__construct();
        $this->_config = get_option( 'gravityformsaddon_gravityforms_coinsnap_settings' );
        
        if (is_admin()) {
            add_action( 'admin_enqueue_scripts', [ $this, 'connectionCheckScript' ] );
            add_action( 'wp_ajax_coinsnap_connection_handler', [$this, 'coinsnapConnectionHandler'] );
            add_action( 'wp_ajax_btcpay_server_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
        }
        else {
            add_action('gform_validation', [ $this, 'coinsnapgf_payment_validation']);
        }
        
        // Adding template redirect handling for btcpay-settings-callback.
        add_action( 'template_redirect', function(){
    
            global $wp_query;
            $notice = new \Coinsnap\Util\Notice();
            
            // Only continue on a btcpay-settings-callback request.    
            if (!isset( $wp_query->query_vars['btcpay-settings-callback'])) {
                return;
            }
            
            $CoinsnapBTCPaySettingsUrl = admin_url('admin.php?page=gf_settings&subview=gravityforms_coinsnap');

            $rawData = file_get_contents('php://input');

            $btcpay_server_url = $this->_config['btcpay_server_url'];
            $btcpay_api_key  = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            $client = new \Coinsnap\Client\Store($btcpay_server_url,$btcpay_api_key);
            if (count($client->getStores()) < 1) {
                $messageAbort = __('Error on verifiying redirected API Key with stored BTCPay Server url. Aborting API wizard. Please try again or continue with manual setup.', 'coinsnap-for-gravity-forms');
                $notice->addNotice('error', $messageAbort);
                wp_redirect($CoinsnapBTCPaySettingsUrl);
            }

            // Data does get submitted with url-encoded payload, so parse $_POST here.
            if (!empty($_POST) || wp_verify_nonce(filter_input(INPUT_POST,'wp_nonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS),'-1')) {
                $data['apiKey'] = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
                $permissions = (isset($_POST['permissions']) && is_array($_POST['permissions']))? $_POST['permissions'] : null;
                    if (isset($permissions)) {
                        foreach ($permissions as $key => $value) {
                        $data['permissions'][$key] = sanitize_text_field($permissions[$key] ?? null);
                    }
                }
            }
            
            if (isset($data['apiKey']) && isset($data['permissions'])) {

                $apiData = new \Coinsnap\Client\BTCPayApiAuthorization($data);
                if ($apiData->hasSingleStore() && $apiData->hasRequiredPermissions()) {

                    $this->coinsnap_settings_update([
                        'btcpay_api_key' => $apiData->getApiKey(),
                        'btcpay_store_id' => $apiData->getStoreID(),
                        'coinsnap_provider' => 'btcpay'
                        ]);

                    $notice->addNotice('success', __('Successfully received api key and store id from BTCPay Server API. Please finish setup by saving this settings form.', 'coinsnap-for-gravity-forms'));

                    // Register a webhook.
                    if ($this->registerWebhook( $apiData->getStoreID(), $apiData->getApiKey(), $this->get_webhook_url())) {
                        $messageWebhookSuccess = __( 'Successfully registered a new webhook on BTCPay Server.', 'coinsnap-for-gravity-forms' );
                        $notice->addNotice('success', $messageWebhookSuccess);
                    }
                    else {
                        $messageWebhookError = __( 'Could not register a new webhook on the store.', 'coinsnap-for-gravity-forms' );
                        $notice->addNotice('error', $messageWebhookError );
                    }

                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
                else {
                    $notice->addNotice('error', __('Please make sure you only select one store on the BTCPay API authorization page.', 'coinsnap-for-gravity-forms'));
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
            }

            $notice->addNotice('error', __('Error processing the data from Coinsnap. Please try again.', 'coinsnap-for-gravity-forms'));
            wp_redirect($CoinsnapBTCPaySettingsUrl);
            exit();
        });
    }
    
    
    
    public function coinsnap_settings_update($data){
        
        $form_data = $this->_config;
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option('gravityformsaddon_gravityforms_coinsnap_settings',$form_data);
    }    
    
    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new CoinsnapGF();
        }
        
        return self::$_instance;
    }
    
    public function connectionCheckScript(){
        wp_register_style('coinsnapgf-backend-style', plugins_url('assets/css/coinsnapgf-backend-style.css',__FILE__),array(),COINSNAPGF_VERSION);
        wp_enqueue_style('coinsnapgf-backend-style');
        wp_enqueue_script('coinsnapgf-admin-fields', plugin_dir_url( __FILE__ ) . 'assets/js/adminFields.js',[ 'jquery' ],COINSNAPGF_VERSION,true);
        wp_enqueue_script('coinsnapgf-connection-check', plugin_dir_url( __FILE__ ) . 'assets/js/connectionCheck.js',[ 'jquery' ],COINSNAPGF_VERSION,true);
        wp_localize_script('coinsnapgf-connection-check', 'coinsnapgf_ajax', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce'  => wp_create_nonce( 'coinsnapgf-ajax-nonce' ),
        ));
    }
    
    public function coinsnapConnectionHandler(){
        
        $_nonce = filter_input(INPUT_POST,'_wpnonce',FILTER_SANITIZE_STRING);
        
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
        
        if( wp_verify_nonce($_nonce,'coinsnapgf-ajax-nonce') ){
            $response = ['result' => false,'message' => $_message_disconnected];

            try {
                $this_store = $store->getStore($this->getStoreId());
                
                if ($this_store['code'] !== 200) {
                    $this->sendJsonResponse($response);
                }
                
                $webhookExists = $this->webhookExists($this->getStoreId(), $this->getApiKey(), $this->get_webhook_url());

                if($webhookExists) {
                    $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];
                    $this->sendJsonResponse($response);
                }

                $webhook = $this->registerWebhook( $this->getStoreId(), $this->getApiKey(), $this->get_webhook_url());
                $response['result'] = (bool)$webhook;
                $response['message'] = $webhook ? $_message_connected.' ('.$connectionData.')' : $_message_disconnected.' (Webhook)';
            }
            catch (\Exception $e) {
                $response['message'] =  __('Gravity Forms: API connection is not established', 'coinsnap-for-gravity-forms');
            }

            $this->sendJsonResponse($response);
        }      
    }

    private function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
    
    /**
     * Handles the BTCPay server AJAX callback from the settings form.
     */
    public function btcpayApiUrlHandler() {
        $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
        if ( !wp_verify_nonce( $_nonce, 'coinsnapgf-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }
        
        if ( current_user_can( 'manage_options' ) ) {
            $host = filter_var(filter_input(INPUT_POST,'host',FILTER_SANITIZE_STRING), FILTER_VALIDATE_URL);

            if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                wp_send_json_error("Error validating BTCPayServer URL.");
            }

            $permissions = array_merge([
		'btcpay.store.canviewinvoices',
		'btcpay.store.cancreateinvoice',
		'btcpay.store.canviewstoresettings',
		'btcpay.store.canmodifyinvoices'
            ],
            [
		'btcpay.store.cancreatenonapprovedpullpayments',
		'btcpay.store.webhooks.canmodifywebhooks',
            ]);

            try {
		// Create the redirect url to BTCPay instance.
		$url = \Coinsnap\Client\BTCPayApiKey::getAuthorizeUrl(
                    $host,
                    $permissions,
                    'GravityForms',
                    true,
                    true,
                    home_url('?btcpay-settings-callback'),
                    null
		);

		// Store the host to options before we leave the site.
                $this->coinsnap_settings_update(['btcpay_server_url' => $host]);

		// Return the redirect url.
		wp_send_json_success(['url' => $url]);
            }
            
            catch (\Throwable $e) {
                
            }
	}
        wp_send_json_error("Error processing Ajax request.");
    }
    
    function coinsnapgf_amount_validation( $amount, $currency ) {
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
                    
        $_provider = $this->get_payment_provider();
        if($_provider === 'btcpay'){

                $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
                
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
        return $checkInvoice;
    }
    
    public function coinsnapgf_payment_validation($validation_result){
        GFCommon::log_debug( __METHOD__ . '(): running payment amount validation.' );
        $form = $validation_result['form'];
        $validation_result['is_valid'] = false;
        $currency  = get_option('rg_gforms_currency');

        foreach ( $form['fields'] as &$field ) {
            if ( $field->type == 'total' ) {
                $field_id = $field->id;
                $amount = filter_var(rgpost( 'input_'.$field_id ), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
                $checkInvoice = $this->coinsnapgf_amount_validation((float)$amount,strtoupper( $currency ));
                
                if($checkInvoice['result'] === true){
                    $validation_result['is_valid'] = true;
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
        $validation_result['form'] = $form;
        return $validation_result;
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
                        'class'    => 'medium coinsnap',                
                        'required' => false,
                        'description'  => __( 'Coinsnap API requires authentication with an API key.<br/>Generate your API key by visiting the <a href="https://app.coinsnap.io/register" target="_blank">Coinsnap registration Page</a>.', 'coinsnap-for-gravity-forms' ),
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
                        'class'    => 'medium btcpay',                
                        'required' => false,
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
        $currency  = rgar( $entry, 'currency' );
        
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        
        $checkInvoice = $this->coinsnapgf_amount_validation($payment_amount,strtoupper( $currency ));
                
        if($checkInvoice['result'] === true){
            $amount = round($payment_amount, 2);
            $buyerEmail = $submission_data['email'];		
            $buyerName = $submission_data['full_name'];

            $webhook_url = $this->get_webhook_url();		


            if (! $this->webhookExists($this->getStoreId(), $this->getApiKey(), $webhook_url)){
                if (! $this->registerWebhook($this->getStoreId(), $this->getApiKey(),$webhook_url)) {                
                    echo (esc_html__('unable to set Webhook url.', 'coinsnap-for-gravity-forms'));
                    exit;
                }
             }      

            //updating lead's payment_status to Pending
            GFAPI::update_entry_property($entry['id'], 'payment_status', 'Pending');
            $return_mode = '2';

            $return_url = $this->return_url($form['id'], $entry['id']) . "&rm={$return_mode}";              

            $invoice_no =  $entry['id'];		



            $metadata = [];
            $metadata['orderNumber'] = $invoice_no;
            $metadata['customerName'] = $buyerName;

            $camount = \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);

            $redirectAutomatically = $this->_config['coinsnap_autoredirect'] ;
            $walletMessage = '';

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

            $payurl = $csinvoice->getData()['checkoutLink'] ;
            return $payurl;
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
        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar(
            $feed['meta'],
            'selectedNotifications'
        ) : array();

        return isset($feed['meta']['delayNotification']) && in_array(
            $notification['id'],
            $selected_notifications
        ) ? true : $is_disabled;
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

    public function process_webhook(){
     
        $notify_json = file_get_contents('php://input');        

        $this->log_debug("coinsnap webhook : ".$notify_json);                
        $notify_ar = json_decode($notify_json, true);
        
        if(isset($notify_ar['invoiceId'])){
            
            $invoice_id = $notify_ar['invoiceId'];

            try {
                $client = new \Coinsnap\Client\Invoice( $this->getApiUrl(), $this->getApiKey() );			
                $csinvoice = $client->getInvoice($this->getStoreId(), $invoice_id);
                $status = $csinvoice->getData()['status'] ;
                $entry_id = $csinvoice->getData()['orderId'] ;				
            }
            catch (\Throwable $e) {													
                echo "Error";
                exit;
            }

            $entry = GFAPI::get_entry( $entry_id );
            $feed  = $this->get_payment_feed( $entry );
            $form   = GFFormsModel::get_form_meta($entry['form_id']);

            $this->log_debug( __METHOD__ . "(): Entry ID #" . $entry['id'] . " is set to Feed ID #" . $feed['id'] ); 

            $order_status = 'Pending';
            if ($status == 'Expired'){
                $order_status = $this->_config['coinsnap_expired_status'];
            }
            elseif ($status == 'Processing'){
                $order_status = $this->_config['coinsnap_processing_status'];
            }
            elseif ($status == 'Settled'){
                $order_status = $this->_config['coinsnap_settled_status'];
            }

            GFAPI::update_entry_property($entry_id, 'payment_status', $order_status);
            if ($order_status == 'Paid'){                        
                GFAPI::send_notifications($form, $entry, 'complete_payment');
                GFAPI::update_entry_property( $entry_id, 'transaction_id', $invoice_id );            
            }
            echo "OK";
        }
        exit;
    }

    
    public function is_callback_valid(): bool {
        if (rgget('page') != 'gf_coinsnap_webhook') {
            return false;
        }
        $this->process_webhook();

        return true;
    }  
    
    public function update_feed_id($old_feed_id, $new_feed_id){
        global $wpdb;
        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='coinsnap_feed_id' AND meta_value=%s",
            $new_feed_id,
            $old_feed_id
        );
        $wpdb->query($sql);
    }
    
    public function update_payment_gateway(){
        global $wpdb;
        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}rg_lead_meta SET meta_value=%s WHERE meta_key='payment_gateway' AND meta_value='coinsnap'",
            $this->_slug
        );
        $wpdb->query($sql);
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

    public function webhookExists(string $storeId, string $apiKey, string $webhook): bool {	
        try {		
            $whClient = new \Coinsnap\Client\Webhook( $this->getApiUrl(), $apiKey );		
            $Webhooks = $whClient->getWebhooks( $storeId );
            
            foreach ($Webhooks as $Webhook){					
                if ($Webhook->getData()['url'] == $webhook) return true;	
            }
        }catch (\Throwable $e) {			
            return false;
        }
    
        return false;
    }
    public  function registerWebhook(string $storeId, string $apiKey, string $webhook): bool {	
        try {			
            $whClient = new \Coinsnap\Client\Webhook($this->getApiUrl(), $apiKey);
            
            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $webhook, //$url
                self::WEBHOOK_EVENTS,   
                null    //$secret
            );	
            
            return true;
        } catch (\Throwable $e) {
            return false;	
        }

        return false;
    }

    public function deleteWebhook(string $storeId, string $apiKey, string $webhookid): bool {	    
        
        try {			
            $whClient = new \Coinsnap\Client\Webhook($this->getApiUrl(), $apiKey);
            
            $webhook = $whClient->deleteWebhook(
                $storeId,   //$storeId
                $webhookid, //$url			
            );					
            return true;
        } catch (\Throwable $e) {
            
            return false;	
        }
    }    


    public function uninstall() {
        $option_names = array(
          'coinsnap_provider',
          'coinsnap_store_id',
          'coinsnap_api_key',
          'btcpay_server_url',
          'btcpay_store_id',
          'btcpay_api_key',
          'coinsnap_autoredirect',
          'coinsnap_expired_status',
          'coinsnap_settled_status',
          'coinsnap_processing_status'          
        );
        
        foreach( $option_names as $option_name ){
          delete_option( $option_name );
        }
    
        parent::uninstall();
      }
}
