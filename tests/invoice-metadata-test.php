<?php
if (PHP_SAPI !== 'cli') { exit; }
// Standalone test of the create-invoice request sent by CoinsnapGF::redirect_url(), for both
// providers, with minimal WordPress / Gravity Forms stubs and a fake HTTP API.
// Run: php tests/invoice-metadata-test.php
define('ABSPATH', '/');
define('COINSNAPGF_VERSION', '9.9.9');
define('COINSNAPGF_MIN_VERSION', '2.5');
define('COINSNAPGF_REFERRAL_CODE', 'REF');
define('COINSNAP_SERVER_URL', 'https://coinsnap.test');
define('COINSNAP_CURRENCIES', ['EUR', 'USD', 'SATS', 'BTC']);
$GLOBALS['t_config'] = [];
$GLOBALS['t_requests'] = [];
$GLOBALS['t_gform_meta'] = [];
function add_action(...$a) {}
function is_admin() { return false; }
function get_option($k, $d = false) { return $k === 'gravityformsaddon_gravityforms_coinsnap_settings' ? $GLOBALS['t_config'] : $d; }
function __($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function esc_url($s) { return $s; }
function rgar($a, $k, $d = '') { return (is_array($a) && isset($a[$k])) ? $a[$k] : $d; }
function rgget($k) { return $_GET[$k] ?? ''; }
function rgempty($k, $a = null) { return empty($_GET[$k]); }
function admin_url($p) { return 'https://shop.test/wp-admin/' . $p; }
function determine_locale() { return 'de_DE'; }
function gform_get_meta($id, $k) { return $GLOBALS['t_gform_meta'][$id][$k] ?? false; }
function gform_update_meta($id, $k, $v) { $GLOBALS['t_gform_meta'][$id][$k] = $v; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function is_wp_error($v) { return false; }
class T_Headers { public function getAll() { return []; } }
function wp_remote_retrieve_headers($r) { return new T_Headers(); }
function wp_remote_request($url, $args) {
    $GLOBALS['t_requests'][] = ['url' => $url, 'method' => $args['method'], 'body' => $args['body']];
    $json = function ($v) { return ['response' => ['code' => 200], 'body' => json_encode($v)]; };
    if (strpos($url, 'coingecko') !== false) { return $json(['rates' => ['eur' => ['value' => 80000], 'usd' => ['value' => 90000]]]); }
    if (substr($url, -16) === '/payment-methods') { return $json([['paymentMethodId' => 'BTC-LN', 'enabled' => true]]); }
    if (strpos($url, '/rates?') !== false) { return $json([['currencyPair' => 'BTC_EUR', 'rate' => '80000', 'errors' => []]]); }
    if ($args['method'] === 'POST' && substr($url, -9) === '/invoices') { return $json(['id' => 'inv' . count($GLOBALS['t_requests']), 'checkoutLink' => 'https://pay.test/i']); }
    return ['response' => ['code' => 404], 'body' => '{}'];
}
class GFForms { public static function include_payment_addon_framework() {} }
class GFCommon { public static function log_debug($m) {} }
class GFAPI { public static function update_entry_property(...$a) { return true; } }
class GFPaymentAddOn {
    public function __construct() {}
    public function log_error($m) {}
}
class T_Field {
    public $id; public $type; public $inputType; public $label; public $inputs; public $visibility = 'visible';
    public function __construct($id, $type, $label, $inputs = null, $inputType = '') { $this->id = $id; $this->type = $type; $this->label = $label; $this->inputs = $inputs; $this->inputType = $inputType; }
    public function get_input_type() { return $this->inputType !== '' ? $this->inputType : $this->type; }
    public function get_value_export($entry, $input_id = '', $use_text = false) {
        if (is_array($this->inputs)) {
            $parts = [];
            foreach ($this->inputs as $i) { if (($entry[$i['id']] ?? '') !== '') { $parts[] = $entry[$i['id']]; } }
            return implode($this->type === 'checkbox' ? ', ' : ' ', $parts);
        }
        return $entry[(string) $this->id] ?? '';
    }
}
require dirname(__DIR__) . '/library/loader.php';
require dirname(__DIR__) . '/includes/InvoiceMetadata.php';
require dirname(__DIR__) . '/class-gf-coinsnap.php';

// The webhook check is not part of this test.
class TestCoinsnapGF extends CoinsnapGF {
    public function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool { return true; }
}

$form = ['id' => 3, 'title' => 'Workshop booking', 'fields' => [
    new T_Field(1, 'name', 'Name', [['id' => '1.3'], ['id' => '1.6']]),
    new T_Field(2, 'email', 'Email'),
    new T_Field(3, 'phone', 'Phone'),
    new T_Field(4, 'address', 'Address', [['id' => '4.1'], ['id' => '4.2'], ['id' => '4.3'], ['id' => '4.4'], ['id' => '4.5'], ['id' => '4.6']]),
    new T_Field(5, 'textarea', 'Message'),
    new T_Field(6, 'select', 'Session'),
    new T_Field(7, 'checkbox', 'Extras', [['id' => '7.1'], ['id' => '7.2']]),
    new T_Field(8, 'text', 'Card'),
    new T_Field(9, 'hidden', 'Ref'),
    new T_Field(10, 'fileupload', 'CV'),
    new T_Field(11, 'password', 'Password'),
    new T_Field(12, 'product', 'Ticket', [['id' => '12.1'], ['id' => '12.2'], ['id' => '12.3']], 'singleproduct'),
    new T_Field(13, 'total', 'Total'),
    new T_Field(14, 'text', 'Internal note'),
    new T_Field(15, 'text', 'Empty'),
    new T_Field(16, 'post_custom_field', 'Company', null, 'text'),
]];
$form['fields'][13]->visibility = 'administrative';
$entry = ['id' => 42, 'form_id' => 3, 'currency' => 'EUR', 'source_url' => 'https://shop.test/book/',
    '1.3' => 'Ana', '1.6' => 'Buyer', '2' => 'ana@example.com', '3' => '+381 11 123',
    '4.1' => 'Main St 1', '4.2' => 'Apt 2', '4.3' => 'Belgrade', '4.4' => 'Serbia Central', '4.5' => '11000', '4.6' => 'Serbia',
    '5' => "Please\nbring coffee", '6' => 'Morning', '7.1' => 'Lunch', '7.2' => 'Parking', '8' => '4111 1111 1111 1111',
    '9' => 'abc', '10' => 'https://shop.test/cv.pdf', '11' => 'hunter2', '12.1' => 'Ticket', '12.2' => '20.00', '12.3' => '2',
    '13' => '45', '14' => 'VIP', '15' => '', '16' => 'ACME'];
$submission = ['payment_amount' => 45, 'email' => 'ana@example.com', 'full_name' => 'Ana Buyer',
    'line_items' => [
        ['id' => 12, 'name' => 'Ticket', 'quantity' => '2', 'unit_price' => 20, 'options' => [['option_label' => 'Front row']], 'is_shipping' => 0],
        ['id' => 17, 'name' => 'Standard shipping', 'quantity' => 1, 'unit_price' => 10, 'is_shipping' => 1],
    ],
    'discounts' => [['id' => 'SAVE5', 'name' => 'SAVE5', 'quantity' => 1, 'unit_price' => -5]],
];
$feed = ['id' => 1, 'meta' => ['billingInformation_email' => '2', 'billingInformation_full_name' => '1']];

$fail = 0;
$check = function ($ok, $label) use (&$fail) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (!$ok) { $fail++; } };

foreach (['coinsnap', 'btcpay'] as $provider) {
    $GLOBALS['t_requests'] = [];
    $GLOBALS['t_config'] = ['coinsnap_provider' => $provider, 'coinsnap_store_id' => 'store1', 'coinsnap_api_key' => 'key',
        'btcpay_store_id' => 'store1', 'btcpay_api_key' => 'key', 'btcpay_server_url' => 'https://btcpay.test',
        'coinsnap_returnurl' => 'https://shop.test/thanks/', 'coinsnap_autoredirect' => '1'];
    $addon = new TestCoinsnapGF();
    $url = $addon->redirect_url($feed, $submission, $form, $entry);
    $check($url === 'https://pay.test/i', "$provider: buyer is sent to the checkout");
    $create = array_values(array_filter($GLOBALS['t_requests'], function ($r) { return $r['method'] === 'POST' && substr($r['url'], -9) === '/invoices'; }));
    $check(count($create) === 1, "$provider: one create-invoice request");
    if (!$create) { continue; }
    $body = json_decode($create[0]['body'], true);
    $m = $body['metadata'] ?? [];
    $check(($body['orderId'] ?? null) === '42', "$provider: top-level orderId is the entry ID");
    $check(($body['buyerEmail'] ?? null) === 'ana@example.com', "$provider: top-level buyerEmail");
    $expected = [
        'orderId' => '42', 'orderNumber' => '42', 'entryId' => '42',
        'orderUrl' => 'https://shop.test/wp-admin/admin.php?page=gf_entries&view=entry&id=3&lid=42',
        'formId' => '3', 'formTitle' => 'Workshop booking', 'itemDesc' => 'Workshop booking', 'platform' => 'gravity-forms',
        'pluginVersion' => '9.9.9', 'pageUrl' => 'https://shop.test/book/', 'language' => 'de_DE',
        'shippingMethod' => 'Standard shipping', 'shippingTotal' => '10.00', 'discountTotal' => '5.00',
        'customerName' => 'Ana Buyer', 'buyerName' => 'Ana Buyer', 'buyerEmail' => 'ana@example.com', 'buyerPhone' => '+381 11 123',
        'buyerAddress1' => 'Main St 1', 'buyerAddress2' => 'Apt 2', 'buyerCity' => 'Belgrade', 'buyerState' => 'Serbia Central',
        'buyerZip' => '11000', 'buyerCountry' => 'Serbia', 'buyerMessage' => "Please\nbring coffee",
    ];
    foreach ($expected as $k => $v) {
        $check(($m[$k] ?? null) === $v, "$provider: metadata.$k");
    }
    $check(($m['lineItems'] ?? null) === [['name' => 'Ticket', 'sku' => '12', 'quantity' => 2, 'price' => '20.00', 'total' => '40.00', 'options' => 'Front row']], "$provider: lineItems");
    $check(($m['formFields'] ?? null) === ['Session' => 'Morning', 'Extras' => 'Lunch, Parking', 'Company' => 'ACME'], "$provider: formFields has only the other filled-in fields");
    $flat = json_encode($m);
    $leaked = array_filter(['4111 1111 1111 1111', 'abc', 'cv.pdf', 'hunter2', 'VIP'], function ($v) use ($flat) { return strpos($flat, $v) !== false; });
    $check(!$leaked, "$provider: no card number, hidden, file, password or admin-only values");
    $check(isset($GLOBALS['t_gform_meta'][42]['coinsnap_invoices']), "$provider: invoice recorded on the entry");
}
exit($fail ? 1 : 0);
