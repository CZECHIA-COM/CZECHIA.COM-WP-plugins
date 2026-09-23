<?php
/* Odinstalace pluginu – smaže tabulky i veškerá nastavení. Deaktivace data ponechává. */
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}czechia_analytics_hits");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}czechia_analytics_daily");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}czechia_analytics_monthly");

delete_option('czechia_analytics_settings');
delete_option('czechia_analytics_db_version');
delete_option('czechia_analytics_salt');
delete_option('czechia_analytics_aggregated_until');

wp_clear_scheduled_hook('czechia_analytics_cron');
