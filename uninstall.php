<?php
// Removes only this add-on's own data when the plugin is deleted: its settings (keys included),
// its webhook record and the upgrade timestamp. Generic coinsnap_* options of other plugins stay.
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('gravityformsaddon_gravityforms_coinsnap_settings');
delete_option('gravityformsaddon_coinsnap_webhook');
delete_option('coinsnapgf_records_since');
