<?php
defined('ABSPATH') || exit;

function czechia_seo_disable_wp_sitemaps($is_enabled) {
    $s = czechia_seo_get_settings();
    if ($s['enable_sitemap']) return false;
    return $is_enabled;
}
add_filter('wp_sitemaps_enabled', 'czechia_seo_disable_wp_sitemaps');

function czechia_seo_sitemap_rewrite_rules() {
    add_rewrite_rule('^sitemap\.xml$', 'index.php?czechia_sitemap=index', 'top');
    add_rewrite_rule('^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?czechia_sitemap=$matches[1]', 'top');
}
add_action('init', 'czechia_seo_sitemap_rewrite_rules');

function czechia_seo_ensure_htaccess() {
    if (!is_admin()) return;
    $s = czechia_seo_get_settings();
    if (!$s['enable_sitemap']) return;

    $htaccess = ABSPATH . '.htaccess';
    if (!file_exists($htaccess)) return;

    $content = file_get_contents($htaccess);
    if (strpos($content, 'CZECHIA SEO Sitemap') === false) {
        czechia_seo_write_htaccess();
    }
}
add_action('admin_init', 'czechia_seo_ensure_htaccess');

function czechia_seo_sitemap_query_vars($vars) {
    $vars[] = 'czechia_sitemap';
    return $vars;
}
add_filter('query_vars', 'czechia_seo_sitemap_query_vars');

function czechia_seo_sitemap_detect() {
    $s = czechia_seo_get_settings();
    if (!$s['enable_sitemap']) return '';

    if (isset($_GET['czechia_sitemap']) && $_GET['czechia_sitemap']) {
        return sanitize_text_field($_GET['czechia_sitemap']);
    }

    $request = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    $request = trim($request, '/');

    $home_path = trim(parse_url(home_url(), PHP_URL_PATH) ?: '', '/');
    if ($home_path && strpos($request, $home_path) === 0) {
        $request = trim(substr($request, strlen($home_path)), '/');
    }

    if ($request === 'sitemap.xml') {
        return 'index';
    }
    if (preg_match('/^sitemap-([a-z0-9_-]+)\.xml$/', $request, $m)) {
        return $m[1];
    }

    return '';
}

function czechia_seo_sitemap_handle() {
    $type = czechia_seo_sitemap_detect();
    if (empty($type)) return;

    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow');

    if ($type === 'index') {
        czechia_seo_render_sitemap_index();
    } else {
        czechia_seo_render_sitemap_posts($type);
    }
    exit;
}
add_action('template_redirect', 'czechia_seo_sitemap_handle', -9999);

function czechia_seo_sitemap_handle_early() {
    if (!isset($_GET['czechia_sitemap'])) return;
    czechia_seo_sitemap_handle();
}
add_action('init', 'czechia_seo_sitemap_handle_early', 999);

function czechia_seo_render_sitemap_index() {
    $s = czechia_seo_get_settings();
    $post_types = !empty($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post', 'page'];

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    echo '<sitemap>' . "\n";
    echo '  <loc>' . esc_url(czechia_seo_sitemap_url('homepage')) . '</loc>' . "\n";
    echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
    echo '</sitemap>' . "\n";

    foreach ($post_types as $pt) {
        $count = wp_count_posts($pt);
        if (!$count || $count->publish < 1) continue;

        echo '<sitemap>' . "\n";
        echo '  <loc>' . esc_url(czechia_seo_sitemap_url($pt)) . '</loc>' . "\n";

        $latest = get_posts([
            'post_type' => $pt,
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'post_status' => 'publish',
        ]);
        if ($latest) {
            echo '  <lastmod>' . get_the_modified_date('c', $latest[0]) . '</lastmod>' . "\n";
        }

        echo '</sitemap>' . "\n";
    }

    echo '</sitemapindex>';
}

function czechia_seo_sitemap_url($type = 'index') {
    $permalink_structure = get_option('permalink_structure');
    if (!empty($permalink_structure)) {
        if ($type === 'index') {
            return home_url('/sitemap.xml');
        }
        return home_url('/sitemap-' . $type . '.xml');
    }
    return home_url('/?czechia_sitemap=' . $type);
}

function czechia_seo_render_sitemap_posts($type) {
    if ($type === 'homepage') {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo '<url>' . "\n";
        echo '  <loc>' . esc_url(home_url('/')) . '</loc>' . "\n";
        echo '  <changefreq>daily</changefreq>' . "\n";
        echo '  <priority>1.0</priority>' . "\n";
        echo '</url>' . "\n";
        echo '</urlset>';
        return;
    }

    $s = czechia_seo_get_settings();
    $post_types = !empty($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post', 'page'];

    if (!in_array($type, $post_types)) {
        status_header(404);
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        return;
    }

    $cache_key = 'czechia_seo_sitemap_' . $type;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        echo $cached;
        return;
    }

    $posts = get_posts([
        'post_type' => $type,
        'posts_per_page' => 2000,
        'post_status' => 'publish',
        'orderby' => 'modified',
        'order' => 'DESC',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_czechia_seo_noindex',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_czechia_seo_noindex',
                'value' => '1',
                'compare' => '!=',
            ],
        ],
    ]);

    ob_start();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($posts as $post) {
        $priority = ($type === 'page') ? '0.8' : '0.6';
        $changefreq = ($type === 'page') ? 'weekly' : 'monthly';

        echo '<url>' . "\n";
        echo '  <loc>' . esc_url(get_permalink($post)) . '</loc>' . "\n";
        echo '  <lastmod>' . get_the_modified_date('c', $post) . '</lastmod>' . "\n";
        echo '  <changefreq>' . $changefreq . '</changefreq>' . "\n";
        echo '  <priority>' . $priority . '</priority>' . "\n";
        echo '</url>' . "\n";
    }

    echo '</urlset>';
    $output = ob_get_clean();

    set_transient($cache_key, $output, HOUR_IN_SECONDS);
    echo $output;
}

function czechia_seo_flush_sitemap_cache($post_id) {
    $post_type = get_post_type($post_id);
    if ($post_type) {
        delete_transient('czechia_seo_sitemap_' . $post_type);
    }
}
add_action('save_post', 'czechia_seo_flush_sitemap_cache');
add_action('delete_post', 'czechia_seo_flush_sitemap_cache');

function czechia_seo_activation() {
    czechia_seo_sitemap_rewrite_rules();
    flush_rewrite_rules();
    czechia_seo_write_htaccess();
}

function czechia_seo_deactivation() {
    flush_rewrite_rules();
    czechia_seo_remove_htaccess();
}

function czechia_seo_write_htaccess() {
    $htaccess = ABSPATH . '.htaccess';
    if (!is_writable(dirname($htaccess))) return;

    $marker = 'CZECHIA SEO Sitemap';
    $rules = [
        '<IfModule mod_rewrite.c>',
        'RewriteEngine On',
        'RewriteRule ^sitemap\.xml$ index.php?czechia_sitemap=index [L,QSA]',
        'RewriteRule ^sitemap-([a-z0-9_-]+)\.xml$ index.php?czechia_sitemap=$1 [L,QSA]',
        '</IfModule>',
    ];

    insert_with_markers($htaccess, $marker, $rules);
}

function czechia_seo_remove_htaccess() {
    $htaccess = ABSPATH . '.htaccess';
    if (!is_writable($htaccess)) return;

    insert_with_markers($htaccess, 'CZECHIA SEO Sitemap', []);
}
