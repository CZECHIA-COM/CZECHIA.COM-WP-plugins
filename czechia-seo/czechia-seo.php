<?php
/**
 * Plugin Name: CZECHIA - SEO
 * Description: Základní SEO nastavení – titulky, popisy, Open Graph, canonical, robots, sitemap.
 * Version: 1.1
 * Author: ZONER a.s.
 */

defined('ABSPATH') || exit;

define('CZECHIA_SEO_DIR', plugin_dir_path(__FILE__));
define('CZECHIA_SEO_URL', plugin_dir_url(__FILE__));
define('CZECHIA_SEO_AI_KEY', 'BWRWi45UezGGZgieHGxlT7M17FlAwuio');

require_once CZECHIA_SEO_DIR . 'includes/conflicts.php';
require_once CZECHIA_SEO_DIR . 'includes/admin-settings.php';
require_once CZECHIA_SEO_DIR . 'includes/meta-box.php';
require_once CZECHIA_SEO_DIR . 'includes/frontend.php';
require_once CZECHIA_SEO_DIR . 'includes/sitemap.php';

function czechia_seo_get_settings() {
    $defaults = [
        'title_separator' => '–',
        'title_format' => 'post_first',
        'blogname_override' => '',
        'homepage_title' => '',
        'homepage_description' => '',
        'og_default_image' => 0,
        'enabled_post_types' => ['post', 'page'],
        'enable_sitemap' => 1,
    ];
    return wp_parse_args(get_option('czechia_seo_settings', []), $defaults);
}

function czechia_seo_get_blogname() {
    $s = czechia_seo_get_settings();
    return !empty($s['blogname_override']) ? $s['blogname_override'] : get_bloginfo('name');
}

function czechia_seo_register_settings() {
    register_setting('czechia_seo_group', 'czechia_seo_settings');
}
add_action('admin_init', 'czechia_seo_register_settings');

function czechia_seo_add_menu() {
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
        'SEO nastavení',
        'SEO nastavení',
        'manage_options',
        'czechia-seo',
        'czechia_seo_render_settings'
    );

    remove_submenu_page('czechia-settings', 'czechia-settings');
}
add_action('admin_menu', 'czechia_seo_add_menu');

function czechia_seo_register_meta() {
    $s = czechia_seo_get_settings();
    $post_types = !empty($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post', 'page'];

    $meta_keys = [
        '_czechia_seo_title' => 'string',
        '_czechia_seo_description' => 'string',
        '_czechia_seo_noindex' => 'string',
        '_czechia_seo_nofollow' => 'string',
    ];

    foreach ($post_types as $pt) {
        foreach ($meta_keys as $key => $type) {
            register_post_meta($pt, $key, [
                'show_in_rest' => true,
                'single' => true,
                'type' => $type,
                'default' => '',
                'auth_callback' => function() { return current_user_can('edit_posts'); },
            ]);
        }
    }
}
add_action('init', 'czechia_seo_register_meta');

function czechia_seo_enqueue_admin($hook) {
    $screen = get_current_screen();
    if (!$screen) return;

    $s = czechia_seo_get_settings();
    $post_types = !empty($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post', 'page'];

    if ($screen->base === 'post' && in_array($screen->post_type, $post_types)) {
        wp_enqueue_style('czechia-seo-admin', CZECHIA_SEO_URL . 'css/admin-seo.css', [], '1.0');

        if ($screen->is_block_editor()) {
            wp_enqueue_script('czechia-seo-gutenberg', CZECHIA_SEO_URL . 'js/gutenberg-seo.js', [
                'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element', 'wp-compose', 'wp-i18n', 'wp-blocks', 'wp-block-editor'
            ], '1.0', true);

            wp_localize_script('czechia-seo-gutenberg', 'czechiaSeoData', [
                'separator' => $s['title_separator'],
                'titleFormat' => $s['title_format'],
                'blogname' => czechia_seo_get_blogname(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'aiNonce' => wp_create_nonce('czechia_seo_ai'),
                'settingsUrl' => admin_url('admin.php?page=czechia-seo'),
            ]);
        }
    }

    if ($hook === 'czechia_page_czechia-seo' || strpos($hook, 'czechia-seo') !== false) {
        wp_enqueue_media();
        wp_enqueue_style('czechia-seo-admin', CZECHIA_SEO_URL . 'css/admin-seo.css', [], '1.0');
    }
}
add_action('admin_enqueue_scripts', 'czechia_seo_enqueue_admin');

function czechia_seo_action_links($links) {
    $settings_link = '<a href="admin.php?page=czechia-seo">Nastavení</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'czechia_seo_action_links');

/* ─── AI generation AJAX handler ─── */
function czechia_seo_ai_generate() {
    check_ajax_referer('czechia_seo_ai', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Nedostatečná oprávnění.', 403);
    }

    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    if (empty($content)) {
        wp_send_json_error('Nebyl předán žádný obsah článku.');
    }

    $prompt = 'Udělej mi meta title a meta description k tomuto textu, respektuj délku maximálně 60 znaků pro meta title a 140 znaků pro meta description. DODRŽUJ DÉLKU ZNAKŮ, nesmí to být více. Nevracej nic jiného, než samotný text. Title a description odděl novým řádkem. Text: ' . $content;

    $response = wp_remote_post('https://llm.airgpt.cz/api/chat', [
        'timeout' => 60,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . CZECHIA_SEO_AI_KEY,
        ],
        'body' => wp_json_encode([
            'model'   => 'gemma3:27b',
            'stream'  => false,
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ]),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Chyba při komunikaci s AI: ' . $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        wp_send_json_error('AI API vrátilo chybu (HTTP ' . $code . ').');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body) || empty($body['done']) || $body['done_reason'] !== 'stop') {
        wp_send_json_error('AI nevrátila platnou odpověď. Zkuste to znovu.');
    }

    $text = isset($body['message']['content']) ? $body['message']['content'] : '';
    if (empty($text)) {
        wp_send_json_error('AI vrátila prázdnou odpověď.');
    }

    /* Parse title and description (separated by newline) */
    $parts = preg_split('/\r?\n/', trim($text), 2);
    $title = isset($parts[0]) ? trim($parts[0]) : '';
    $desc  = isset($parts[1]) ? trim($parts[1]) : '';

    /* Strip possible surrounding quotes */
    $title = trim($title, '"\'');
    $desc  = trim($desc, '"\'');

    if (empty($title) || empty($desc)) {
        wp_send_json_error('AI nevrátila titulek i popis. Zkuste to znovu.');
    }

    wp_send_json_success([
        'title'       => $title,
        'description' => $desc,
    ]);
}
add_action('wp_ajax_czechia_seo_ai_generate', 'czechia_seo_ai_generate');

register_activation_hook(__FILE__, 'czechia_seo_activation');
register_deactivation_hook(__FILE__, 'czechia_seo_deactivation');
