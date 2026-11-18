<?php
declare(strict_types=1);

namespace CoinsnapGF\Webhook;

defined('ABSPATH') || exit;

// Only the invoice id is taken from the request; every decision comes from the payment server's API.
class WebhookHandler
{
    // Statuses a webhook never changes: paid, or a final status the merchant set.
    private const FINAL = ['Paid', 'Refunded', 'Voided', 'Active', 'Authorized'];
    private const OPEN = ['', 'Pending', 'Processing'];

    private $provider;
    private $secret;
    private $statuses;

    // Plain properties, not constructor promotion: the plugin still supports PHP 7.4.
    // $statuses: ['settled' => ..., 'processing' => ..., 'expired' => ...] from the plugin settings.
    public function __construct(string $provider, string $secret, array $statuses)
    {
        $this->provider = $provider;
        $this->secret = $secret;
        $this->statuses = $statuses;
    }

    public function handle(string $rawBody, string $signatureHeader, int $nowSeconds): int
    {
        $problem = ($this->provider === 'btcpay')
            ? Signature::btcpayProblem($signatureHeader, $rawBody, $this->secret)
            : Signature::coinsnapProblem($signatureHeader, $rawBody, $this->secret, $nowSeconds);
        if ($problem !== null) {
            $this->logSecurity($problem);
            return 401;
        }

        $body = json_decode($rawBody, true);
        $invoiceId = (is_array($body) && is_string($body['invoiceId'] ?? null)) ? $body['invoiceId'] : '';
        if ($invoiceId === '') {
            return 200;
        }
        $claimsPaid = in_array($body['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        try {
            $invoice = $this->fetchInvoice($invoiceId);
        } catch (\Coinsnap\Exception\RequestException $e) {
            if (in_array($e->getCode(), [400, 404], true)) {
                // The dashboard's "Send test webhook" button lands here.
                $this->logInfo('unknown invoice', $invoiceId);
                return 200;
            }
            $this->logInfo('retry: API unavailable', $invoiceId);
            return 503;
        } catch (\Throwable $e) {
            $this->logInfo('retry: API unavailable', $invoiceId);
            return 503;
        }

        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $orderId = ($this->provider === 'btcpay') ? ($metadata['orderId'] ?? '') : ($invoice['orderId'] ?? '');
        $entryId = (is_scalar($orderId) && ctype_digit((string) $orderId)) ? (int) $orderId : 0;
        $entry = $entryId > 0 ? $this->entry($entryId) : null;
        if ($entry === null || !$entry['ours']) {
            $this->logInfo('not ours', $invoiceId);
            return 200;
        }

        $recorded = $entry['invoices'];
        $hasRecord = is_array($recorded) && $recorded !== [];
        // Without a record, only entries from before the upgrade are accepted, for one day after it.
        if (!$hasRecord && !$this->legacyAllowed((int) $entry['created'])) {
            $this->logSecurity('no invoice record for this entry', $invoiceId);
            return 200;
        }
        if ($hasRecord && !isset($recorded[$invoiceId])) {
            $this->logSecurity('invoice not created for this entry', $invoiceId);
            return 200;
        }
        $expected = $hasRecord ? $recorded[$invoiceId] : [
            'amount' => (string) $entry['amount'],
            'currency' => (string) $entry['currency'],
        ];

        $decision = InvoiceDecision::decide($invoice, $this->provider === 'btcpay', (string) ($expected['amount'] ?? ''), (string) ($expected['currency'] ?? ''));
        if ($decision !== InvoiceDecision::PAID && ($claimsPaid || $decision === InvoiceDecision::MISMATCH)) {
            $this->logSecurity($claimsPaid ? 'forged status' : 'amount mismatch', $invoiceId);
            return 200;
        }
        if ($decision === InvoiceDecision::NONE) {
            $this->logInfo('no change', $invoiceId);
            return 200;
        }

        if (!$this->lock($entryId)) {
            return 503;
        }
        try {
            // Read again under the lock so a parallel webhook's change is seen.
            $current = $this->entry($entryId);
            $this->apply($decision, $entryId, $current ?? $entry, $invoiceId);
        } finally {
            $this->unlock($entryId);
        }
        return 200;
    }

    // Statuses only move forward; a paid, refunded or voided entry is never changed.
    private function apply(string $decision, int $entryId, array $entry, string $invoiceId): void
    {
        $current = (string) $entry['status'];
        if (in_array($current, self::FINAL, true) || (string) $entry['paid_invoice'] !== '') {
            $this->logInfo('already processed', $invoiceId);
            return;
        }
        if ($decision === InvoiceDecision::PAID) {
            // A late payment may still settle an expired or cancelled entry.
            $this->markPaid($entryId, $invoiceId, (string) ($this->statuses['settled'] ?? 'Paid'));
            $this->logInfo('paid', $invoiceId);
            return;
        }
        $target = '';
        if ($decision === InvoiceDecision::PROCESSING && in_array($current, ['', 'Pending'], true)) {
            $target = (string) ($this->statuses['processing'] ?? '');
        }
        elseif ($decision === InvoiceDecision::EXPIRED && in_array($current, self::OPEN, true)) {
            $target = (string) ($this->statuses['expired'] ?? '');
        }
        // Only a settled, verified invoice may mark an entry paid, whatever the settings say.
        if (in_array($target, self::FINAL, true)) {
            $target = '';
        }
        if ($target === '' || $target === $current) {
            $this->logInfo('no change', $invoiceId);
            return;
        }
        $this->setStatus($entryId, $target);
        $this->logInfo('status ' . $decision, $invoiceId);
    }

    protected function fetchInvoice(string $invoiceId): array
    {
        $addon = \CoinsnapGF::get_instance();
        return (new \Coinsnap\Client\Invoice($addon->getApiUrl(), $addon->getApiKey()))->getInvoice($addon->getStoreId(), $invoiceId)->getData();
    }

    // The entry as far as the payment check needs it, or null.
    protected function entry(int $entryId): ?array
    {
        $entry = \GFAPI::get_entry($entryId);
        if (!is_array($entry) || empty($entry['id'])) {
            return null;
        }
        $invoices = gform_get_meta($entryId, 'coinsnap_invoices');
        $amount = (string) rgar($entry, 'payment_amount');
        if ($amount === '' || (float) $amount <= 0) {
            $form = \GFAPI::get_form((int) $entry['form_id']);
            $amount = is_array($form) ? number_format((float) \GFCommon::get_order_total($form, $entry), 2, '.', '') : '';
        }
        return [
            'status' => (string) rgar($entry, 'payment_status'),
            'ours' => is_array($invoices) || \CoinsnapGF::get_config_by_entry($entry) !== false,
            'amount' => $amount,
            'currency' => (string) rgar($entry, 'currency'),
            'created' => (int) strtotime((string) rgar($entry, 'date_created') . ' UTC'),
            'invoices' => $invoices,
            'paid_invoice' => (string) gform_get_meta($entryId, 'coinsnap_paid_invoice'),
        ];
    }

    protected function markPaid(int $entryId, string $invoiceId, string $status): void
    {
        gform_update_meta($entryId, 'coinsnap_paid_invoice', $invoiceId);
        \GFAPI::update_entry_property($entryId, 'payment_status', $status);
        \GFAPI::update_entry_property($entryId, 'transaction_id', $invoiceId);
        if ($status === 'Paid') {
            $entry = \GFAPI::get_entry($entryId);
            $form = \GFAPI::get_form((int) $entry['form_id']);
            \GFAPI::send_notifications($form, $entry, 'complete_payment');
        }
    }

    protected function setStatus(int $entryId, string $status): void
    {
        \GFAPI::update_entry_property($entryId, 'payment_status', $status);
    }

    protected function lock(int $entryId): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', 'coinsnap_gf_' . $entryId)) === 1;
    }

    protected function unlock(int $entryId): void
    {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'coinsnap_gf_' . $entryId));
    }

    protected function logInfo(string $reason, string $invoiceId = ''): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(self::line('INFO', $reason, $invoiceId));
        }
    }

    // At most 10 security lines a minute, so a flood of fake requests can't fill the log.
    protected function logSecurity(string $reason, string $invoiceId = ''): void
    {
        $key = 'coinsnapgf_seclog_' . gmdate('YmdHi');
        $count = (int) get_transient($key) + 1;
        set_transient($key, $count, 120);
        if ($count === 11) {
            error_log('Coinsnap GF SECURITY webhook: more rejections this minute, not logged');
        }
        if ($count <= 10) {
            error_log(self::line('SECURITY', $reason, $invoiceId));
        }
    }

    public static function line(string $level, string $reason, string $invoiceId): string
    {
        // The invoice id comes from the request.
        $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceId), 0, 64);
        return sprintf('Coinsnap GF %s webhook reason=%s invoice=%s', $level, $reason, $id !== '' ? $id : '-');
    }

    // Entries created before invoices were recorded are accepted only during the first day after the upgrade.
    protected function legacyAllowed(int $entryCreated): bool
    {
        $since = (int) get_option('coinsnapgf_records_since', 0);
        return $since > 0 && time() - $since < DAY_IN_SECONDS && $entryCreated > 0 && $entryCreated < $since;
    }
}
