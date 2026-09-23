<?php
/**
 * Plugin Name: CZECHIA - analytics
 * Description: Jednoduché měření návštěvnosti webu bez cookies.
 * Version: 1.2
 * Author: ZONER a.s.
 */

defined('ABSPATH') || exit;

define('CZECHIA_ANALYTICS_VERSION', '1.2');
define('CZECHIA_ANALYTICS_DB_VERSION', '2');
define('CZECHIA_ANALYTICS_DIR', plugin_dir_path(__FILE__));
define('CZECHIA_ANALYTICS_URL', plugin_dir_url(__FILE__));

require_once CZECHIA_ANALYTICS_DIR . 'includes/install.php';
require_once CZECHIA_ANALYTICS_DIR . 'includes/tracker.php';
require_once CZECHIA_ANALYTICS_DIR . 'includes/stats.php';
require_once CZECHIA_ANALYTICS_DIR . 'includes/admin-page.php';
require_once CZECHIA_ANALYTICS_DIR . 'includes/dashboard-widget.php';

function czechia_analytics_get_settings() {
    $defaults = [
        'exclude_logged_in' => 1,
        'retention_months' => 12,
        'excluded_ips' => '',
    ];
    return wp_parse_args(get_option('czechia_analytics_settings', []), $defaults);
}

function czechia_analytics_retention_options() {
    return [
        3 => '3 měsíce',
        6 => '6 měsíců',
        12 => '12 měsíců',
        24 => '24 měsíců',
    ];
}

function czechia_analytics_table($name) {
    global $wpdb;
    return $wpdb->prefix . 'czechia_analytics_' . $name;
}

function czechia_analytics_add_menu() {
    global $menu;
    $menu_exists = false;
    if (is_array($menu)) {
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === 'czechia-settings') {
                $menu_exists = true;
                break;
            }
        }
    }

    if (!$menu_exists) {
        add_menu_page(
            'CZECHIA',
            'CZECHIA',
            'manage_options',
            'czechia-settings',
            '__return_null',
            'dashicons-admin-generic',
            80
        );
    }

    add_submenu_page(
        'czechia-settings',
        'Návštěvnost',
        'Návštěvnost',
        'manage_options',
        'czechia-analytics',
        'czechia_analytics_render_page'
    );

    remove_submenu_page('czechia-settings', 'czechia-settings');
}
add_action('admin_menu', 'czechia_analytics_add_menu');

function czechia_analytics_enqueue_admin($hook) {
    if ($hook !== 'index.php' && strpos($hook, 'czechia-analytics') === false) return;
    wp_enqueue_style('czechia-analytics-admin', CZECHIA_ANALYTICS_URL . 'css/admin-analytics.css', [], CZECHIA_ANALYTICS_VERSION);
}
add_action('admin_enqueue_scripts', 'czechia_analytics_enqueue_admin');

function czechia_analytics_action_links($links) {
    $link = '<a href="admin.php?page=czechia-analytics">Přehled</a>';
    array_unshift($links, $link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'czechia_analytics_action_links');

register_activation_hook(__FILE__, 'czechia_analytics_activation');
register_deactivation_hook(__FILE__, 'czechia_analytics_deactivation');
