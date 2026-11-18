<?php
if (PHP_SAPI !== 'cli') { exit; }
// Standalone test of CoinsnapGF::coinsnapgf_payment_validation() with minimal WordPress / Gravity Forms stubs.
// Run: php tests/payment-validation-test.php
define('ABSPATH', '/');
define('COINSNAPGF_VERSION', 'test');
define('COINSNAPGF_MIN_VERSION', '2.5');
function add_action(...$a) {}
function is_admin() { return false; }
function get_option($k, $d = false) { return $k === 'rg_gforms_currency' ? 'EUR' : $d; }
function __($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function rgar($a, $k, $d = '') { return (is_array($a) && isset($a[$k])) ? $a[$k] : $d; }
function rgpost($k) { return $_POST[$k] ?? ''; }
class GFForms { public static function include_payment_addon_framework() {} }
class GFCommon { public static function log_debug($m) {} }
class GFPaymentAddOn {
    public static $feeds = [];
    public function __construct() {}
    public function get_feeds($form_id = null) { return self::$feeds[$form_id] ?? []; }
}
require dirname(__DIR__) . '/class-gf-coinsnap.php';

// The Coinsnap API is not called in this test; the amount check result is set per case.
class TestCoinsnapGF extends CoinsnapGF {
    public $amountResult = ['result' => true];
    public $apiCalls = 0;
    public function coinsnapgf_amount_validation($amount, $currency) { $this->apiCalls++; return $this->amountResult; }
}
function field($id, $type) { $f = new stdClass(); $f->id = $id; $f->type = $type; $f->failed_validation = false; $f->validation_message = ''; return $f; }
function form($id, $fields) { return ['id' => $id, 'fields' => $fields]; }

$pass = 0; $fail = 0;
function ok($got, $want, $name) { global $pass, $fail; if ($got === $want) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name (got " . var_export($got, true) . ', want ' . var_export($want, true) . ")\n"; } }

GFPaymentAddOn::$feeds = [
    1 => [['id' => 1, 'form_id' => 1, 'is_active' => '1']],
    3 => [['id' => 2, 'form_id' => 3, 'is_active' => '0']],
];
$addon = new TestCoinsnapGF();
$_POST['input_5'] = '20.00';

// A contact form without a Coinsnap feed and without a Total field.
$r = $addon->coinsnapgf_payment_validation(['is_valid' => true, 'form' => form(2, [field(1, 'text')])]);
ok($r['is_valid'], true, 'form without Coinsnap feed stays valid');
$r = $addon->coinsnapgf_payment_validation(['is_valid' => false, 'form' => form(2, [field(1, 'text')])]);
ok($r['is_valid'], false, 'form without Coinsnap feed keeps its own failed validation');
$r = $addon->coinsnapgf_payment_validation(['is_valid' => true, 'form' => form(2, [field(5, 'total')])]);
ok($r['is_valid'], true, 'form with a Total field but no Coinsnap feed stays valid');
$r = $addon->coinsnapgf_payment_validation(['is_valid' => true, 'form' => form(3, [field(5, 'total')])]);
ok($r['is_valid'], true, 'form with an inactive Coinsnap feed stays valid');
ok($addon->apiCalls, 0, 'no Coinsnap API call for forms that do not pay with Coinsnap');

// A Coinsnap form.
$r = $addon->coinsnapgf_payment_validation(['is_valid' => true, 'form' => form(1, [field(1, 'text'), field(5, 'total')])]);
ok($r['is_valid'], true, 'Coinsnap form with a valid amount is valid');
ok($addon->apiCalls, 1, 'Coinsnap form checks the amount with the API');
$r = $addon->coinsnapgf_payment_validation(['is_valid' => false, 'form' => form(1, [field(1, 'text'), field(5, 'total')])]);
ok($r['is_valid'], false, "a valid amount does not override another field's failed validation");
$addon->amountResult = ['result' => false, 'error' => 'amountError', 'min_value' => 1];
$r = $addon->coinsnapgf_payment_validation(['is_valid' => true, 'form' => form(1, [field(5, 'total')])]);
ok($r['is_valid'], false, 'Coinsnap form with a too small amount is invalid');
ok($r['form']['fields'][0]->failed_validation, true, 'the Total field is marked failed');
$r = $addon->coinsnapgf_payment_validation(['is_valid' => true, 'form' => form(1, [field(1, 'text')])]);
ok($r['is_valid'], false, 'Coinsnap form without a Total field is invalid (unchanged)');

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
