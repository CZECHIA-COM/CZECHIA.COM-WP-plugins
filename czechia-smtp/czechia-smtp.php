<?php
/**
 * Plugin Name: CZECHIA - odesílání emailů přes SMTP
 * Description: Nastavte odesílání emailů přes SMTP server Czechia.
 * Version: 1.1
 * Author: ZONER a.s.
 */

function czechia_smtp_register_settings() {
    register_setting('czechia_smtp_group', 'czechia_smtp_settings');
}
add_action('admin_init', 'czechia_smtp_register_settings');

function czechia_smtp_add_menu() {
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
        'SMTP odesílání emailů',
        'SMTP odesílání emailů',
        'manage_options',
        'czechia-smtp',
        'czechia_smtp_render_settings'
    );

    remove_submenu_page('czechia-settings', 'czechia-settings');
}
add_action('admin_menu', 'czechia_smtp_add_menu');

function czechia_smtp_get_settings() {
    $defaults = [
        'enabled' => 0,
        'server' => 'smtp.zoner.com',
        'encryption' => 'ssl',
        'port' => '465',
        'auth' => 1,
        'user' => '',
        'from_name' => '',
        'pass' => '',
        'last_tab' => 'simple'
    ];
    return wp_parse_args(get_option('czechia_smtp_settings', []), $defaults);
}

function czechia_smtp_render_settings() {
    $settings = czechia_smtp_get_settings();
    $logo = plugins_url('img/logo.svg', __FILE__);
    if (empty($settings['from_name'])) $settings['from_name'] = get_bloginfo('name');
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $settings['last_tab'];
    ?>

    <style>
        .czechia-smtp-tabs { display:flex; gap:0; margin:20px 0 0 0; }
        .czechia-smtp-tabs a {
            padding:12px 28px;
            background:#e5e5e5;
            color:#23282d;
            text-decoration:none;
            font-weight:600;
            font-size:14px;
            border:1px solid #ccc;
            border-bottom:none;
            border-radius:4px 4px 0 0;
            margin-right:-1px;
            position:relative;
        }
        .czechia-smtp-tabs a.active {
            background:#fff;
            border-bottom:1px solid #fff;
            z-index:1;
            color:#0073aa;
        }
        .czechia-smtp-tab-content {
            background:#fff;
            border:1px solid #ccc;
            padding:20px 24px;
            margin-top:-1px;
        }
        #czechia-smtp-result { margin-top:15px; }
        #czechia-smtp-result .notice { margin:0; }
        #czechia-smtp-spinner { display:none; margin-left:10px; vertical-align:middle; }
    </style>

    <div class="wrap">
        <div style="float:right; text-align:center; margin:15px 0 10px 20px;">
            <img src="<?php echo esc_url($logo); ?>" alt="Logo" width="120">
            <p><a href="https://napoveda.czechia.com/clanek/odesilani-emailu-pres-autentizovany-smtp-server/" target="_blank">Nápověda</a></p>
        </div>

        <h1>CZECHIA - odesílání emailů přes SMTP</h1>

        <p>Nastavte SMTP připojení pro odesílání emailů.</p>

        <div class="czechia-smtp-tabs">
            <a href="?page=czechia-smtp&tab=simple" class="<?php echo $active_tab === 'simple' ? 'active' : ''; ?>">Jednoduché</a>
            <a href="?page=czechia-smtp&tab=advanced" class="<?php echo $active_tab === 'advanced' ? 'active' : ''; ?>">Pokročilé</a>
        </div>

        <?php if ($active_tab === 'simple'): ?>

        <div class="czechia-smtp-tab-content">
            <p>Zadejte svůj email a heslo. Ostatní nastavení se vyplní automaticky. <a href="https://napoveda.czechia.com/clanek/odesilani-emailu-pres-autentizovany-smtp-server/" target="_blank" style="text-decoration:none;"><span class="dashicons dashicons-editor-help" style="font-size:18px; width:18px; height:18px; vertical-align:text-bottom; margin-right:2px;"></span><span style="text-decoration:underline;">Co mám vyplnit?</span></a></p>

            <table class="form-table">
                <tr>
                    <th>Emailová adresa</th>
                    <td>
                        <input id="czechia-simple-user" type="email" class="regular-text" value="<?php echo esc_attr($settings['user']); ?>">
                    </td>
                </tr>
                <tr>
                    <th>Heslo</th>
                    <td>
                        <input id="czechia-simple-pass" type="password" class="regular-text" value="" placeholder="<?php if (!empty($settings['pass'])): ?>(uloženo – změňte přepsáním)<?php endif; ?>">
                    </td>
                </tr>
            </table>

            <p>
                <button id="czechia-simple-save" class="button button-primary">Uložit nastavení</button>
                <span id="czechia-smtp-spinner" class="spinner is-active"></span>
            </p>

            <div id="czechia-smtp-result"></div>
        </div>

        <?php else: ?>

        <div class="czechia-smtp-tab-content">
            <table class="form-table">
                <tr>
                    <th>Používat toto nastavení</th>
                    <td>
                        <input id="czechia-adv-enabled" type="checkbox" value="1" <?php checked($settings['enabled'], 1); ?>>
                    </td>
                </tr>
                <tr>
                    <th>SMTP server</th>
                    <td>
                        <input id="czechia-adv-server" type="text" class="regular-text czechia-adv-field" value="<?php echo esc_attr($settings['server']); ?>">
                    </td>
                </tr>
                <tr>
                    <th>Šifrování</th>
                    <td>
                        <select id="czechia-adv-encryption" class="czechia-adv-field">
                            <option value="">Žádné</option>
                            <option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>SMTP port</th>
                    <td>
                        <input id="czechia-adv-port" type="number" class="czechia-adv-field" value="<?php echo esc_attr($settings['port']); ?>">
                    </td>
                </tr>
                <tr>
                    <th>Autentifikace</th>
                    <td>
                        <input id="czechia-adv-auth" type="checkbox" class="czechia-adv-field" value="1" <?php checked($settings['auth'], 1); ?>>
                    </td>
                </tr>
                <tr>
                    <th>SMTP uživatel</th>
                    <td>
                        <input id="czechia-adv-user" type="text" class="regular-text czechia-adv-field" value="<?php echo esc_attr($settings['user']); ?>">
                    </td>
                </tr>
                <tr>
                    <th>Název odesílatele</th>
                    <td>
                        <input id="czechia-adv-from-name" type="text" class="regular-text czechia-adv-field" value="<?php echo esc_attr($settings['from_name']); ?>">
                    </td>
                </tr>
                <tr>
                    <th>SMTP heslo</th>
                    <td>
                        <input id="czechia-adv-pass" type="password" class="regular-text czechia-adv-field" value="" placeholder="<?php if (!empty($settings['pass'])): ?>(uloženo – změňte přepsáním)<?php endif; ?>">
                    </td>
                </tr>
            </table>

            <p>
                <button id="czechia-adv-save" class="button button-primary">Uložit nastavení</button>
                <span id="czechia-smtp-spinner-adv" class="spinner is-active" style="display:none; float:none; vertical-align:middle; margin-left:10px;"></span>
            </p>

            <div id="czechia-smtp-result-adv"></div>
        </div>

        <?php endif; ?>

    </div>

    <script>
    (function(){
        var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        var nonce = '<?php echo wp_create_nonce('czechia_smtp_save_test'); ?>';

        window.czechiaDownloadErrorLog = function(log, hostname) {
            var content = 'Czechia SMTP error report - ' + hostname + '\n';
            content += 'Hostname: ' + hostname + '\n';
            content += 'Datum: ' + new Date().toLocaleString('cs-CZ') + '\n';
            content += '-------------------------------------------\n\n';
            content += log;
            var blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'czechia-smtp-error-report.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        };

        var simpleBtn = document.getElementById('czechia-simple-save');
        if (simpleBtn) {
            simpleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var spinner = document.getElementById('czechia-smtp-spinner');
                var result = document.getElementById('czechia-smtp-result');
                spinner.style.display = 'inline-block';
                result.innerHTML = '';
                simpleBtn.disabled = true;

                var data = new FormData();
                data.append('action', 'czechia_smtp_save_test');
                data.append('nonce', nonce);
                data.append('mode', 'simple');
                data.append('user', document.getElementById('czechia-simple-user').value);
                data.append('pass', document.getElementById('czechia-simple-pass').value);

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function(r){ return r.json(); })
                    .then(function(r){
                        spinner.style.display = 'none';
                        simpleBtn.disabled = false;
                        if (r.success) {
                            result.innerHTML = '<div class="notice notice-success"><p>' + r.data.message + '</p></div>';
                        } else {
                            result.innerHTML = '<div class="notice notice-error"><p>' + r.data.message + '</p></div>';
                            if (r.data.log) {
                                window._czechiaSmtpLastLog = r.data.log;
                                window._czechiaSmtpLastHost = r.data.hostname || '';
                                result.innerHTML += '<p><a href="#" onclick="document.getElementById(\'smtp-debug-log\').style.display=\'block\'; return false;">Zobrazit detaily</a> &nbsp; <a href="#" class="button" onclick="czechiaDownloadErrorLog(window._czechiaSmtpLastLog, window._czechiaSmtpLastHost); return false;">Stáhnout chybové hlášení</a></p>';
                                result.innerHTML += '<pre id="smtp-debug-log" style="display:none; background:#fff; padding:10px; border:1px solid #ccc; max-height:400px; overflow:auto;">' + r.data.log + '</pre>';
                            }
                        }
                    })
                    .catch(function(){
                        spinner.style.display = 'none';
                        simpleBtn.disabled = false;
                        result.innerHTML = '<div class="notice notice-error"><p>Chyba při komunikaci se serverem.</p></div>';
                    });
            });
        }

        var advBtn = document.getElementById('czechia-adv-save');
        if (advBtn) {
            advBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var spinner = document.getElementById('czechia-smtp-spinner-adv');
                var result = document.getElementById('czechia-smtp-result-adv');
                spinner.style.display = 'inline-block';
                result.innerHTML = '';
                advBtn.disabled = true;

                var data = new FormData();
                data.append('action', 'czechia_smtp_save_test');
                data.append('nonce', nonce);
                data.append('mode', 'advanced');
                data.append('enabled', document.getElementById('czechia-adv-enabled').checked ? '1' : '0');
                data.append('server', document.getElementById('czechia-adv-server').value);
                data.append('encryption', document.getElementById('czechia-adv-encryption').value);
                data.append('port', document.getElementById('czechia-adv-port').value);
                data.append('auth', document.getElementById('czechia-adv-auth').checked ? '1' : '0');
                data.append('user', document.getElementById('czechia-adv-user').value);
                data.append('from_name', document.getElementById('czechia-adv-from-name').value);
                data.append('pass', document.getElementById('czechia-adv-pass').value);

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function(r){ return r.json(); })
                    .then(function(r){
                        spinner.style.display = 'none';
                        advBtn.disabled = false;
                        if (r.success) {
                            result.innerHTML = '<div class="notice notice-success"><p>' + r.data.message + '</p></div>';
                        } else {
                            result.innerHTML = '<div class="notice notice-error"><p>' + r.data.message + '</p></div>';
                            if (r.data.log) {
                                window._czechiaSmtpLastLog = r.data.log;
                                window._czechiaSmtpLastHost = r.data.hostname || '';
                                result.innerHTML += '<p><a href="#" onclick="document.getElementById(\'smtp-debug-log-adv\').style.display=\'block\'; return false;">Zobrazit detaily</a> &nbsp; <a href="#" class="button" onclick="czechiaDownloadErrorLog(window._czechiaSmtpLastLog, window._czechiaSmtpLastHost); return false;">Stáhnout chybové hlášení</a></p>';
                                result.innerHTML += '<pre id="smtp-debug-log-adv" style="display:none; background:#fff; padding:10px; border:1px solid #ccc; max-height:400px; overflow:auto;">' + r.data.log + '</pre>';
                            }
                        }
                    })
                    .catch(function(){
                        spinner.style.display = 'none';
                        advBtn.disabled = false;
                        result.innerHTML = '<div class="notice notice-error"><p>Chyba při komunikaci se serverem.</p></div>';
                    });
            });
        }
    })();
    </script>
    <?php
}

function czechia_smtp_ajax_save_test() {
    check_ajax_referer('czechia_smtp_save_test', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Nedostatečná oprávnění.']);
    }

    $mode = sanitize_text_field($_POST['mode']);
    $old_settings = czechia_smtp_get_settings();

    if ($mode === 'simple') {
        $new_settings = [
            'server' => 'smtp.zoner.com',
            'encryption' => 'ssl',
            'port' => '465',
            'auth' => 1,
            'user' => sanitize_email($_POST['user']),
            'from_name' => $old_settings['from_name'] ?: get_bloginfo('name'),
            'pass' => !empty($_POST['pass']) ? base64_encode($_POST['pass']) : $old_settings['pass'],
            'enabled' => 0,
            'last_tab' => 'simple',
        ];
    } else {
        $new_settings = [
            'enabled' => intval($_POST['enabled']),
            'server' => sanitize_text_field($_POST['server']),
            'encryption' => sanitize_text_field($_POST['encryption']),
            'port' => sanitize_text_field($_POST['port']),
            'auth' => intval($_POST['auth']),
            'user' => sanitize_text_field($_POST['user']),
            'from_name' => sanitize_text_field($_POST['from_name']),
            'pass' => !empty($_POST['pass']) ? base64_encode($_POST['pass']) : $old_settings['pass'],
            'last_tab' => 'advanced',
        ];
    }

    update_option('czechia_smtp_settings', $new_settings);

    $test_settings = $new_settings;
    $test_settings['enabled'] = 1;

    $GLOBALS['czechia_smtp_test_override'] = $test_settings;
    $GLOBALS['czechia_smtp_log'] = '';

    add_action('phpmailer_init', function($phpmailer) {
        $phpmailer->SMTPDebug = 2;
        $phpmailer->Debugoutput = function($str) {
            $GLOBALS['czechia_smtp_log'] .= $str . "\n";
        };
    }, 9999);

    $admin_email = get_option('admin_email');
    $result = wp_mail($admin_email, 'Test CZECHIA SMTP', 'Testovací email z pluginu CZECHIA SMTP.');

    unset($GLOBALS['czechia_smtp_test_override']);

    if ($result) {
        if ($mode === 'simple') {
            $new_settings['enabled'] = 1;
            update_option('czechia_smtp_settings', $new_settings);
        } elseif ($mode === 'advanced' && intval($_POST['enabled'])) {
            $new_settings['enabled'] = 1;
            update_option('czechia_smtp_settings', $new_settings);
        }
        wp_send_json_success(['message' => 'Nastavení bylo uloženo a testovací email byl úspěšně odeslán na ' . esc_html($admin_email) . '.']);
    } else {
        if ($mode === 'simple') {
            $new_settings['enabled'] = 0;
            update_option('czechia_smtp_settings', $new_settings);
        } elseif ($mode === 'advanced') {
            $new_settings['enabled'] = 0;
            update_option('czechia_smtp_settings', $new_settings);
        }
        wp_send_json_error([
            'message' => 'Nastavení bylo uloženo, ale testovací email se nepodařilo odeslat. SMTP odesílání nebylo aktivováno. <strong>Zkontrolujte zadané údaje a zkuste to znovu.</strong>',
            'log' => esc_html($GLOBALS['czechia_smtp_log']),
            'hostname' => esc_html(parse_url(home_url(), PHP_URL_HOST)),
        ]);
    }
}
add_action('wp_ajax_czechia_smtp_save_test', 'czechia_smtp_ajax_save_test');


function czechia_smtp_phpmailer($phpmailer) {
    if (!empty($GLOBALS['czechia_smtp_test_override'])) {
        $s = $GLOBALS['czechia_smtp_test_override'];
    } else {
        $s = czechia_smtp_get_settings();
    }
    if (!$s['enabled']) return;

    $phpmailer->isSMTP();
    $phpmailer->Host = $s['server'];
    $phpmailer->Port = $s['port'];
    $phpmailer->SMTPAuth = $s['auth'] ? true : false;

    if ($s['encryption']) $phpmailer->SMTPSecure = $s['encryption'];

    if (!empty($s['user'])) {
        $phpmailer->setFrom($s['user'], $s['from_name']);
    }

    if ($s['auth']) {
        $phpmailer->Username = $s['user'];
        $phpmailer->Password = base64_decode($s['pass']);
    }
}
add_action('phpmailer_init', 'czechia_smtp_phpmailer');

function czechia_smtp_action_links($links) {
    $settings_link = '<a href="admin.php?page=czechia-smtp">Nastavení</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'czechia_smtp_action_links');

function czechia_smtp_admin_notice() {
    $s = czechia_smtp_get_settings();
    if (!$s['enabled']) {
        echo '<div class="notice notice-error" style="background:#fff0f0; border-left-color:#dc3232;"><p><strong>⚠️ [CZECHIA SMTP]</strong> Není nastaveno správné odesílání emailů. Dokončete nastavení <a href="admin.php?page=czechia-smtp">zde</a>.</p></div>';
    }
}
add_action('admin_notices', 'czechia_smtp_admin_notice');
