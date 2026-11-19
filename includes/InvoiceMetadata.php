<?php
declare(strict_types=1);

namespace CoinsnapGF;

defined('ABSPATH') || exit;

// Invoice metadata for a Gravity Forms entry: the entry ID as orderId, the form, the products and
// the submitted fields. Plain values (no HTML escaping); empty values are left out.
class InvoiceMetadata
{
    public const PLATFORM = 'gravity-forms';
    public const MAX_LENGTH = 500;

    // Never sent: files, passwords, card and captcha fields, hidden and layout fields. Pricing
    // fields are sent as lineItems instead.
    private const SKIP_TYPES = [
        'fileupload', 'post_image', 'signature', 'password', 'creditcard', 'captcha', 'honeypot', 'hidden',
        'html', 'section', 'page', 'consent', 'submit', 'stripe_creditcard', 'square_creditcard', 'paypal',
        'product', 'option', 'quantity', 'shipping', 'total', 'singleproduct', 'singleshipping', 'calculation', 'donation', 'coupon',
    ];

    /**
     * @param array $args entry_id, form_id, form_title, order_url, page_url, version, language,
     *                    name, email (from the feed's billing mapping), mapped_field_ids,
     *                    fields: list of ['id', 'type', 'input_type', 'label', 'value' (plain text), 'inputs' => [input id => value], 'admin_only'],
     *                    line_items, discounts (Gravity Forms submission data)
     */
    public static function forEntry(array $args): array
    {
        $entryId = (string) ($args['entry_id'] ?? '');
        $meta = [];
        // The webhook matches the invoice to the entry by this ID (top-level orderId, metadata orderId).
        self::put($meta, 'orderId', $entryId);
        self::put($meta, 'orderNumber', $entryId);
        self::put($meta, 'entryId', $entryId);
        self::put($meta, 'orderUrl', $args['order_url'] ?? '');
        self::put($meta, 'formId', (string) ($args['form_id'] ?? ''));
        self::put($meta, 'formTitle', $args['form_title'] ?? '');
        self::put($meta, 'itemDesc', $args['form_title'] ?? '');
        self::put($meta, 'platform', self::PLATFORM);
        self::put($meta, 'pluginVersion', $args['version'] ?? '');
        self::put($meta, 'pageUrl', $args['page_url'] ?? '');
        self::put($meta, 'language', $args['language'] ?? '');

        $items = [];
        $shipping = 0.0;
        $shippingNames = [];
        foreach ((array) ($args['line_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            if (!empty($item['is_shipping'])) {
                $shipping += $price * $qty;
                $shippingNames[] = (string) ($item['name'] ?? '');
                continue;
            }
            $line = [];
            self::put($line, 'name', $item['name'] ?? '');
            self::put($line, 'sku', (string) ($item['id'] ?? ''));
            $line['quantity'] = (fmod($qty, 1.0) === 0.0) ? (int) $qty : $qty;
            $line['price'] = self::money($price);
            $line['total'] = self::money($price * $qty);
            $options = [];
            foreach ((array) ($item['options'] ?? []) as $option) {
                $label = is_array($option) ? self::clean($option['option_label'] ?? ($option['option_name'] ?? '')) : null;
                if ($label !== null) {
                    $options[] = (string) $label;
                }
            }
            if ($options) {
                $line['options'] = implode(', ', $options);
            }
            $items[] = $line;
        }
        if ($items) {
            $meta['lineItems'] = $items;
        }
        if ($shippingNames) {
            self::put($meta, 'shippingMethod', implode(', ', array_filter($shippingNames)));
            $meta['shippingTotal'] = self::money($shipping);
        }
        $discount = 0.0;
        foreach ((array) ($args['discounts'] ?? []) as $item) {
            if (is_array($item)) {
                $discount += abs((float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 1));
            }
        }
        if ($discount > 0) {
            $meta['discountTotal'] = self::money($discount);
        }

        // Personal data. The plugin has no "send customer data" setting, so it is always sent.
        $name = self::clean($args['name'] ?? '');
        $email = self::clean($args['email'] ?? '');
        $mapped = array_map('strval', (array) ($args['mapped_field_ids'] ?? []));
        $fields = [];
        foreach ((array) ($args['fields'] ?? []) as $field) {
            // The input type (e.g. textarea for a post custom field), and the field type for pricing fields.
            $type = strtolower((string) (($field['input_type'] ?? '') !== '' ? $field['input_type'] : ($field['type'] ?? '')));
            $skip = in_array($type, self::SKIP_TYPES, true) || in_array(strtolower((string) ($field['type'] ?? '')), self::SKIP_TYPES, true);
            $id = (string) ($field['id'] ?? '');
            if ($id === '' || $skip || !empty($field['admin_only']) || in_array($id, $mapped, true)) {
                continue;
            }
            $inputs = is_array($field['inputs'] ?? null) ? $field['inputs'] : [];
            if ($type === 'address' && !isset($meta['buyerAddress1'])) {
                $parts = ['buyerAddress1' => '.1', 'buyerAddress2' => '.2', 'buyerCity' => '.3', 'buyerState' => '.4', 'buyerZip' => '.5', 'buyerCountry' => '.6'];
                foreach ($parts as $key => $suffix) {
                    self::put($meta, $key, $inputs[$id . $suffix] ?? '');
                }
                continue;
            }
            $value = self::clean($field['value'] ?? '');
            if ($value === null) {
                continue;
            }
            if ($type === 'email' && $email === null) {
                $email = $value;
            } elseif ($type === 'name' && $name === null) {
                $name = $value;
            } elseif ($type === 'phone' && !isset($meta['buyerPhone'])) {
                $meta['buyerPhone'] = $value;
            } elseif ($type === 'textarea' && !isset($meta['buyerMessage'])) {
                $meta['buyerMessage'] = $value;
            } else {
                $label = trim((string) ($field['label'] ?? ''));
                $label = ($label === '') ? 'Field ' . $id : $label;
                $fields[isset($fields[$label]) ? $label . ' (' . $id . ')' : $label] = $value;
            }
        }
        self::put($meta, 'customerName', $name);
        self::put($meta, 'buyerName', $name);
        self::put($meta, 'buyerEmail', $email);
        if ($fields) {
            $meta['formFields'] = $fields;
        }
        return $meta;
    }

    // Adds $value under $key unless it is empty.
    public static function put(array &$meta, string $key, $value): void
    {
        $value = self::clean($value);
        if ($value !== null) {
            $meta[$key] = $value;
        }
    }

    // A plain value for metadata, or null to leave it out (empty, not text, or a card number).
    public static function clean($value)
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $part) {
                $part = is_scalar($part) && !is_bool($part) ? self::clean($part) : null;
                if ($part !== null) {
                    $parts[] = (string) $part;
                }
            }
            return $parts ? self::cut(implode(', ', $parts)) : null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || self::looksLikeCardNumber($value)) {
            return null;
        }
        return self::cut($value);
    }

    // 13 to 19 digits (spaces and dashes allowed) that pass the Luhn check.
    public static function looksLikeCardNumber(string $value): bool
    {
        $digits = preg_replace('/[\s-]/', '', $value);
        if (!is_string($digits) || !preg_match('/^\d{13,19}$/', $digits)) {
            return false;
        }
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = !$double;
        }
        return $sum % 10 === 0;
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function cut(string $value): string
    {
        if (function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > self::MAX_LENGTH ? mb_substr($value, 0, self::MAX_LENGTH, 'UTF-8') : $value;
        }
        return strlen($value) > self::MAX_LENGTH ? substr($value, 0, self::MAX_LENGTH) : $value;
    }
}
