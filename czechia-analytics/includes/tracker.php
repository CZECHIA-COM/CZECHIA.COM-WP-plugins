<?php
defined('ABSPATH') || exit;

define('CZECHIA_ANALYTICS_MAX_HITS', 200);
define('CZECHIA_ANALYTICS_VISIT_GAP', 30 * MINUTE_IN_SECONDS);
define('CZECHIA_ANALYTICS_BOT_PATTERN', '/bot|crawl|spider|slurp|scrap|headless|phantom|puppeteer|playwright|selenium|webdriver|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|httpclient|libwww|perl\/|ruby|php\/|facebookexternalhit|embedly|whatsapp|telegram|discord|skype|preview|archiver|semrush|ahrefs|mj12|bytespider|gptbot|ccbot|perplexity/i');

function czechia_analytics_should_track_page() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return false;
    if (is_feed() || is_robots() || is_trackback() || is_embed() || is_preview() || is_customize_preview()) return false;

    $s = czechia_analytics_get_settings();
    if ($s['exclude_logged_in'] && is_user_logged_in()) return false;

    return true;
}

function czechia_analytics_enqueue_tracker() {
    if (!czechia_analytics_should_track_page()) return;

    wp_enqueue_script(
        'czechia-analytics',
        CZECHIA_ANALYTICS_URL . 'js/tracker.js',
        [],
        CZECHIA_ANALYTICS_VERSION,
        ['in_footer' => true, 'strategy' => 'defer']
    );

    $config = [
        'url' => admin_url('admin-ajax.php', 'relative'),
        'delay' => (int) apply_filters('czechia_analytics_delay', 1000),
        'is404' => is_404() ? 1 : 0,
        'postId' => is_singular() ? (int) get_queried_object_id() : 0,
    ];
    wp_add_inline_script('czechia-analytics', 'window.czechiaAnalytics = ' . wp_json_encode($config) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'czechia_analytics_enqueue_tracker');

/* ─── Příjem zobrazení ─── */

function czechia_analytics_handle_hit() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !czechia_analytics_is_human_request()) {
        czechia_analytics_end_hit();
    }

    $s = czechia_analytics_get_settings();
    if ($s['exclude_logged_in'] && is_user_logged_in()) {
        czechia_analytics_end_hit();
    }

    $ip = czechia_analytics_client_ip();
    $excluded = array_filter(array_map('trim', explode("\n", $s['excluded_ips'])));
    if ($ip === '' || in_array($ip, $excluded, true)) {
        czechia_analytics_end_hit();
    }

    $screen_w = absint($_POST['sw'] ?? 0);
    $screen_h = absint($_POST['sh'] ?? 0);
    if ($screen_w === 0 || $screen_h === 0 || ($screen_w === 800 && $screen_h === 600)) {
        czechia_analytics_end_hit();
    }

    $path = czechia_analytics_clean_path(wp_unslash($_POST['p'] ?? ''));
    if ($path === '') {
        czechia_analytics_end_hit();
    }

    global $wpdb;
    $table = czechia_analytics_table('hits');
    $ua = (string) wp_unslash($_SERVER['HTTP_USER_AGENT']);
    $now = czechia_analytics_now();
    $hash = czechia_analytics_visitor_hash($ip, $ua, $now);

    $recent = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE visitor_hash = %s AND created_at >= %s",
        $hash,
        $now->modify('-' . CZECHIA_ANALYTICS_VISIT_GAP . ' seconds')->format('Y-m-d H:i:s')
    ));
    if ($recent >= CZECHIA_ANALYTICS_MAX_HITS) {
        czechia_analytics_end_hit();
    }

    list($device, $browser, $os) = czechia_analytics_parse_ua($ua, absint($_POST['tp'] ?? 0));
    $referrer = czechia_analytics_clean_referrer(wp_unslash($_POST['r'] ?? ''));

    $wpdb->insert($table, [
        'created_at' => $now->format('Y-m-d H:i:s'),
        'visitor_hash' => $hash,
        'is_entry' => $recent === 0 ? 1 : 0,
        'is_404' => ($_POST['nf'] ?? '') === '1' ? 1 : 0,
        'post_id' => absint($_POST['id'] ?? 0),
        'path' => $path,
        'title' => mb_substr(sanitize_text_field(wp_unslash($_POST['t'] ?? '')), 0, 255),
        'referrer' => $referrer,
        'source' => czechia_analytics_source($referrer, wp_unslash($_POST['u'] ?? '')),
        'device' => $device,
        'browser' => $browser,
        'os' => $os,
        'screen_w' => min($screen_w, 20000),
        'screen_h' => min($screen_h, 20000),
    ], ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d']);

    czechia_analytics_end_hit();
}
add_action('wp_ajax_czechia_analytics_hit', 'czechia_analytics_handle_hit');
add_action('wp_ajax_nopriv_czechia_analytics_hit', 'czechia_analytics_handle_hit');

/* Odpověď je vždy stejná (i pro odmítnutá zobrazení), aby z ní nešlo vyčíst, co filtr zachytil. */
function czechia_analytics_end_hit() {
    nocache_headers();
    status_header(204);
    exit;
}

/* ─── Filtrování botů ─── */

function czechia_analytics_is_human_request() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

    if (strlen($ua) < 20 || strpos($ua, 'Mozilla/') === false || preg_match(CZECHIA_ANALYTICS_BOT_PATTERN, $ua)) {
        return false;
    }

    $client_hints = isset($_SERVER['HTTP_SEC_CH_UA']) ? (string) $_SERVER['HTTP_SEC_CH_UA'] : '';
    if ($client_hints !== '' && stripos($client_hints, 'headless') !== false) {
        return false;
    }

    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return false;
    }

    $fetch_site = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? (string) $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
    if ($fetch_site !== '' && $fetch_site !== 'same-origin') {
        return false;
    }

    $referer_host = isset($_SERVER['HTTP_REFERER']) ? (string) wp_parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']) : '';
    if ($referer_host !== '' && $host !== '' && strcasecmp($referer_host, $host) !== 0) {
        return false;
    }

    return (bool) apply_filters('czechia_analytics_is_human', true, $ua);
}

/* ─── Anonymizace návštěvníka ─── */

function czechia_analytics_client_ip() {
    $ip = '';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) continue;
        foreach (explode(',', (string) $_SERVER[$key]) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
                break 2;
            }
        }
    }
    return (string) apply_filters('czechia_analytics_client_ip', $ip);
}

function czechia_analytics_visitor_hash($ip, $ua, DateTimeImmutable $now) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = bin2hex(substr(inet_pton($ip), 0, 8));
    }
    $salt = czechia_analytics_salt($now->format('Y-m'));
    return substr(hash_hmac('sha256', $ip . '|' . $ua, $salt), 0, 16);
}

function czechia_analytics_salt($period) {
    global $wpdb;
    static $cache = [];
    if (isset($cache[$period])) return $cache[$period];

    $name = 'czechia_analytics_salt';
    $read = function () use ($wpdb, $name) {
        return $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
    };
    $parse = function ($raw) use ($period) {
        $value = is_string($raw) ? maybe_unserialize($raw) : null;
        return (is_array($value) && ($value['period'] ?? '') === $period && !empty($value['salt'])) ? $value['salt'] : null;
    };

    $raw = $read();
    $salt = $parse($raw);

    if ($salt === null) {
        $fresh = serialize(['period' => $period, 'salt' => wp_generate_password(64, true, true)]);
        if ($raw === null) {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, $fresh));
        } else {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $fresh, $name, $raw));
        }
        wp_cache_delete($name, 'options');
        $salt = $parse($read());
    }

    if ($salt === null) {
        return wp_generate_password(64, true, true);
    }

    return $cache[$period] = $salt;
}

/* ─── Čištění vstupů ─── */

function czechia_analytics_clean_path($path) {
    $path = preg_replace('/[\x00-\x20\x7F]/', '', (string) $path);
    if ($path === '' || $path[0] !== '/' || strpos($path, '//') === 0) return '';
    return substr($path, 0, 255);
}

function czechia_analytics_clean_referrer($url) {
    $parts = wp_parse_url(trim((string) $url));
    if (empty($parts['host']) || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https', 'android-app'], true)) {
        return '';
    }

    $clean = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . ($parts['path'] ?? '');
    return mb_substr(preg_replace('/[\x00-\x20\x7F]/', '', $clean), 0, 255);
}

/* Zdroj návštěvy: utm_source, jinak doména odkazující stránky (známé služby sjednoceny pod jeden název). */
function czechia_analytics_source($referrer, $utm_source) {
    $utm = strtolower(trim(sanitize_text_field((string) $utm_source)));
    if ($utm !== '') {
        $aliases = [
            'google' => 'Google',
            'seznam' => 'Seznam',
            'bing' => 'Bing',
            'facebook' => 'Facebook',
            'fb' => 'Facebook',
            'instagram' => 'Instagram',
            'ig' => 'Instagram',
            'twitter' => 'X (Twitter)',
            'x' => 'X (Twitter)',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'chatgpt' => 'ChatGPT',
            'chatgpt.com' => 'ChatGPT',
        ];
        return $aliases[$utm] ?? mb_substr($utm, 0, 100);
    }

    $host = $referrer !== '' ? (string) wp_parse_url($referrer, PHP_URL_HOST) : '';
    if ($host === '') return '';

    $host = preg_replace('/^www\./', '', $host);
    $own = isset($_SERVER['HTTP_HOST']) ? preg_replace(['/:\d+$/', '/^www\./'], '', strtolower((string) $_SERVER['HTTP_HOST'])) : '';
    if ($host === $own) return '';

    $known = [
        'Google' => '/(^|\.)google\.[a-z.]+$|^com\.google\./',
        'Seznam' => '/(^|\.)seznam\.cz$/',
        'Bing' => '/(^|\.)bing\.com$/',
        'DuckDuckGo' => '/(^|\.)duckduckgo\.com$/',
        'Facebook' => '/(^|\.)facebook\.com$|^fb\.me$|^com\.facebook\./',
        'Instagram' => '/(^|\.)instagram\.com$/',
        'X (Twitter)' => '/^t\.co$|(^|\.)twitter\.com$|(^|\.)x\.com$/',
        'LinkedIn' => '/(^|\.)linkedin\.com$|^lnkd\.in$/',
        'YouTube' => '/(^|\.)youtube\.com$|^youtu\.be$/',
        'ChatGPT' => '/(^|\.)chatgpt\.com$|(^|\.)openai\.com$/',
    ];
    foreach ($known as $label => $pattern) {
        if (preg_match($pattern, $host)) return $label;
    }
    return mb_substr($host, 0, 100);
}

/* Hrubé rozpoznání zařízení, prohlížeče a systému z User-Agentu (UA se neukládá). */
function czechia_analytics_parse_ua($ua, $touch_points) {
    $ipad_os = strpos($ua, 'Macintosh') !== false && $touch_points > 1;

    if (preg_match('/Mobi|iPhone|iPod|Windows Phone/', $ua)) {
        $device = 'mobile';
    } elseif ($ipad_os || preg_match('/iPad|Tablet|Android|Kindle|Silk/', $ua)) {
        $device = 'tablet';
    } else {
        $device = 'desktop';
    }

    $browsers = [
        'Edge' => '/Edg(e|A|iOS)?\//',
        'Opera' => '/OPR\/|Opera/',
        'Seznam.cz' => '/SznProhlizec/',
        'Samsung Internet' => '/SamsungBrowser/',
        'Firefox' => '/Firefox\/|FxiOS/',
        'Chrome' => '/Chrome\/|CriOS/',
        'Safari' => '/Version\/[\d.]+.*Safari\//',
    ];
    $browser = 'Ostatní';
    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $ua)) {
            $browser = $name;
            break;
        }
    }

    if (strpos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif ($ipad_os || preg_match('/iPhone|iPad|iPod/', $ua)) {
        $os = 'iOS';
    } elseif (strpos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'CrOS') !== false) {
        $os = 'ChromeOS';
    } elseif (strpos($ua, 'Macintosh') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'Linux') !== false) {
        $os = 'Linux';
    } else {
        $os = 'Ostatní';
    }

    return [$device, $browser, $os];
}
