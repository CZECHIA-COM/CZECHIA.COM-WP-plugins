<?php
defined('ABSPATH') || exit;

function czechia_analytics_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $hits = czechia_analytics_table('hits');
    $daily = czechia_analytics_table('daily');
    $monthly = czechia_analytics_table('monthly');

    dbDelta("CREATE TABLE $hits (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  visitor_hash char(16) NOT NULL,
  is_entry tinyint(1) unsigned NOT NULL DEFAULT 0,
  is_404 tinyint(1) unsigned NOT NULL DEFAULT 0,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  path varchar(255) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  referrer varchar(255) NOT NULL DEFAULT '',
  source varchar(100) NOT NULL DEFAULT '',
  device varchar(10) NOT NULL DEFAULT '',
  browser varchar(30) NOT NULL DEFAULT '',
  os varchar(30) NOT NULL DEFAULT '',
  screen_w smallint(5) unsigned NOT NULL DEFAULT 0,
  screen_h smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY visitor (visitor_hash,created_at)
) $charset_collate;");

    dbDelta("CREATE TABLE $daily (
  day date NOT NULL,
  pageviews int(10) unsigned NOT NULL DEFAULT 0,
  visits int(10) unsigned NOT NULL DEFAULT 0,
  visitors int(10) unsigned NOT NULL DEFAULT 0,
  not_found int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day)
) $charset_collate;");

    dbDelta("CREATE TABLE $monthly (
  month char(7) NOT NULL,
  visitors int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (month)
) $charset_collate;");

    update_option('czechia_analytics_db_version', CZECHIA_ANALYTICS_DB_VERSION);
}

function czechia_analytics_schedule_cron() {
    if (wp_next_scheduled('czechia_analytics_cron')) return;
    $first_run = new DateTimeImmutable('tomorrow 00:10', wp_timezone());
    wp_schedule_event($first_run->getTimestamp(), 'daily', 'czechia_analytics_cron');
}

function czechia_analytics_activation() {
    czechia_analytics_install();
    czechia_analytics_schedule_cron();
}

function czechia_analytics_deactivation() {
    wp_clear_scheduled_hook('czechia_analytics_cron');
}

function czechia_analytics_maybe_upgrade() {
    if (get_option('czechia_analytics_db_version') !== CZECHIA_ANALYTICS_DB_VERSION) {
        czechia_analytics_install();
    }
}
add_action('plugins_loaded', 'czechia_analytics_maybe_upgrade');
add_action('admin_init', 'czechia_analytics_schedule_cron');

function czechia_analytics_run_maintenance() {
    global $wpdb;

    czechia_analytics_aggregate_days();

    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . czechia_analytics_table('hits') . ' WHERE created_at < %s',
        czechia_analytics_retention_cutoff()->format('Y-m-d H:i:s')
    ));
}
add_action('czechia_analytics_cron', 'czechia_analytics_run_maintenance');

function czechia_analytics_delete_all_data() {
    global $wpdb;
    $wpdb->query('TRUNCATE TABLE ' . czechia_analytics_table('hits'));
    $wpdb->query('TRUNCATE TABLE ' . czechia_analytics_table('daily'));
    $wpdb->query('TRUNCATE TABLE ' . czechia_analytics_table('monthly'));
    delete_option('czechia_analytics_aggregated_until');
}
