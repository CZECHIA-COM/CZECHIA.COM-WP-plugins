<?php
defined('ABSPATH') || exit;

function czechia_analytics_views() {
    return [
        'day' => [
            'tab' => 'Den',
            'current' => 'Dnes',
            'previous' => 'Včera',
            'compare' => 'oproti včerejšku ve stejný čas',
            'range' => 'posledních 30 dní',
        ],
        'week' => [
            'tab' => 'Týden',
            'current' => 'Tento týden',
            'previous' => 'Minulý týden',
            'compare' => 'oproti minulému týdnu ve stejný čas',
            'range' => 'posledních 12 týdnů',
        ],
        'month' => [
            'tab' => 'Měsíc',
            'current' => 'Tento měsíc',
            'previous' => 'Minulý měsíc',
            'compare' => 'oproti minulému měsíci ve stejný čas',
            'range' => 'posledních 12 měsíců',
        ],
    ];
}

function czechia_analytics_render_page() {
    if (!current_user_can('manage_options')) return;

    $tab = (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'settings' : 'overview';
    $logo = plugins_url('img/logo.svg', dirname(__FILE__));
    $base_url = admin_url('admin.php?page=czechia-analytics');
    ?>

    <div class="wrap czechia-analytics">
        <div style="float:right; text-align:center; margin:15px 0 10px 20px;">
            <img src="<?php echo esc_url($logo); ?>" alt="Logo" width="120">
        </div>

        <h1>CZECHIA - analytics</h1>

        <div class="czechia-analytics-tabs">
            <a href="<?php echo esc_url($base_url); ?>" class="<?php echo $tab === 'overview' ? 'active' : ''; ?>">Přehled</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'settings', $base_url)); ?>" class="<?php echo $tab === 'settings' ? 'active' : ''; ?>">Nastavení</a>
        </div>

        <div class="czechia-analytics-tab-content">
            <?php
            if ($tab === 'settings') {
                czechia_analytics_render_settings();
            } else {
                czechia_analytics_render_overview();
            }
            ?>
        </div>
    </div>
    <?php
}

/* ─── Přehled ─── */

function czechia_analytics_render_overview() {
    $views = czechia_analytics_views();
    $view = (isset($_GET['view']) && isset($views[$_GET['view']])) ? sanitize_key($_GET['view']) : 'day';
    $labels = $views[$view];
    $base_url = admin_url('admin.php?page=czechia-analytics');

    if (!czechia_analytics_has_data()) {
        $s = czechia_analytics_get_settings();
        ?>
        <div class="czechia-analytics-empty-state">
            <span class="dashicons dashicons-chart-bar"></span>
            <h2>Zatím žádná data</h2>
            <p>Měření je aktivní. První návštěvy se tu objeví, jakmile na web přijdou návštěvníci.</p>
            <?php if ($s['exclude_logged_in']): ?>
            <p>Přihlášení uživatelé (včetně vás) se nepočítají – pro vyzkoušení otevřete web v anonymním okně prohlížeče.</p>
            <?php endif; ?>
        </div>
        <?php
        return;
    }

    $p = czechia_analytics_period($view);
    $current = czechia_analytics_totals($p['cur_start'], $p['end']);
    $previous = czechia_analytics_totals($p['prev_start'], $p['prev_same']);
    $previous_full = czechia_analytics_totals($p['prev_start'], $p['cur_start']);
    $series = czechia_analytics_series($view, $p);
    $range = czechia_analytics_totals($p['range_start'], $p['end']);
    $active = czechia_analytics_active_visitors();
    $range_label = '· ' . $labels['range'];

    $per_visit = function ($totals) {
        return $totals['visits'] > 0 ? $totals['pageviews'] / $totals['visits'] : 0;
    };
    ?>

    <div class="czechia-analytics-toolbar">
        <nav class="czechia-analytics-switch" aria-label="Období">
            <?php foreach ($views as $key => $item): ?>
            <a href="<?php echo esc_url(add_query_arg('view', $key, $base_url)); ?>" class="<?php echo $key === $view ? 'active' : ''; ?>"<?php echo $key === $view ? ' aria-current="page"' : ''; ?>><?php echo esc_html($item['tab']); ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="czechia-analytics-live">
            <span class="czechia-analytics-live-dot" aria-hidden="true"></span>
            <strong><?php echo esc_html(number_format_i18n($active)); ?></strong>
            <?php echo esc_html(czechia_analytics_plural($active, 'návštěvník', 'návštěvníci', 'návštěvníků')); ?> za posledních 30 minut
        </span>
    </div>

    <h2 class="czechia-analytics-heading"><?php echo esc_html($labels['current']); ?> <small><?php echo esc_html($labels['compare']); ?></small></h2>

    <div class="czechia-analytics-kpis">
        <?php
        czechia_analytics_render_kpi('Unikátní návštěvníci', $current['visitors'], $previous['visitors'], $previous_full['visitors'], $labels['previous']);
        czechia_analytics_render_kpi('Návštěvy', $current['visits'], $previous['visits'], $previous_full['visits'], $labels['previous']);
        czechia_analytics_render_kpi('Zobrazení stránek', $current['pageviews'], $previous['pageviews'], $previous_full['pageviews'], $labels['previous']);
        czechia_analytics_render_kpi('Stránek na návštěvu', $per_visit($current), $per_visit($previous), $per_visit($previous_full), $labels['previous'], 1);
        ?>
    </div>

    <div class="czechia-analytics-section-title">
        <h2>Vývoj návštěvnosti <small><?php echo esc_html($range_label); ?></small></h2>
        <div class="czechia-analytics-legend">
            <span><i class="czechia-analytics-legend-key is-visitors"></i>Unikátní návštěvníci</span>
            <span><i class="czechia-analytics-legend-key is-pageviews"></i>Zobrazení stránek</span>
            <span>Světlejší sloupec = období ještě probíhá</span>
        </div>
    </div>

    <?php czechia_analytics_render_chart($series); ?>

    <?php if ($p['range_start'] < czechia_analytics_retention_cutoff()): ?>
    <p class="czechia-analytics-note" style="margin:16px 0 0;">Seznamy níže zahrnují jen období, za které se uchovávají podrobná data (<?php echo esc_html(czechia_analytics_retention_options()[(int) czechia_analytics_get_settings()['retention_months']] ?? ''); ?>). Graf a souhrny jsou kompletní.</p>
    <?php endif; ?>

    <div class="czechia-analytics-cards">
        <?php
        czechia_analytics_render_top_pages(czechia_analytics_top_pages($p['range_start'], $p['end'], 5), $range['pageviews'], $range_label);
        czechia_analytics_render_breakdown('Zdroje návštěv', czechia_analytics_breakdown('source', $p['range_start'], $p['end'], 6), $range['visits'], $range_label, [], 'Přímý přístup');
        ?>
    </div>

    <div class="czechia-analytics-cards is-small">
        <?php
        czechia_analytics_render_breakdown('Zařízení', czechia_analytics_breakdown('device', $p['range_start'], $p['end'], 3), $range['visits'], $range_label, ['mobile' => 'Mobil', 'tablet' => 'Tablet', 'desktop' => 'Počítač']);
        czechia_analytics_render_breakdown('Prohlížeče', czechia_analytics_breakdown('browser', $p['range_start'], $p['end'], 5), $range['visits'], $range_label);
        czechia_analytics_render_breakdown('Rozlišení obrazovky', czechia_analytics_breakdown('screen', $p['range_start'], $p['end'], 5), $range['visits'], $range_label);
        ?>
    </div>

    <div class="czechia-analytics-cards">
        <?php czechia_analytics_render_not_found(czechia_analytics_not_found($p['range_start'], $p['end'], 10), $range_label); ?>
    </div>
    <?php
}

function czechia_analytics_plural($count, $one, $few, $many) {
    if ($count === 1) return $one;
    if ($count >= 2 && $count <= 4) return $few;
    return $many;
}

function czechia_analytics_delta($current, $previous) {
    if ($previous <= 0) {
        return ['class' => 'is-flat', 'text' => '–'];
    }
    $pct = ($current - $previous) / $previous * 100;
    if (abs($pct) < 0.5) {
        return ['class' => 'is-flat', 'text' => '= 0 %'];
    }
    return $pct > 0
        ? ['class' => 'is-up', 'text' => '▲ ' . number_format_i18n($pct) . ' %']
        : ['class' => 'is-down', 'text' => '▼ ' . number_format_i18n(abs($pct)) . ' %'];
}

function czechia_analytics_render_kpi($label, $current, $previous, $previous_full, $previous_label, $decimals = 0) {
    /* Změna se počítá ze zobrazených (zaokrouhlených) hodnot, aby „2,0 vs. 2,0“ neukazovalo −1 %. */
    $delta = czechia_analytics_delta(round($current, $decimals), round($previous, $decimals));
    ?>
    <div class="czechia-analytics-kpi">
        <div class="czechia-analytics-kpi-label"><?php echo esc_html($label); ?></div>
        <div>
            <span class="czechia-analytics-kpi-value"><?php echo esc_html(number_format_i18n($current, $decimals)); ?></span>
            <span class="czechia-analytics-kpi-delta <?php echo esc_attr($delta['class']); ?>" title="Změna oproti stejné části předchozího období"><?php echo esc_html($delta['text']); ?></span>
        </div>
        <div class="czechia-analytics-kpi-prev">
            <?php echo esc_html($previous_label); ?>: <?php echo esc_html(number_format_i18n($previous, $decimals)); ?> ve stejný čas · <?php echo esc_html(number_format_i18n($previous_full, $decimals)); ?> celkem
        </div>
    </div>
    <?php
}

function czechia_analytics_render_chart(array $series) {
    $max = 0;
    foreach ($series as $bucket) {
        $max = max($max, $bucket['pageviews']);
    }
    $top = czechia_analytics_nice_max($max);
    $count = count($series);
    /* U 30 dní popisujeme jen každý pátý den (počítáno od dneška), jinak by se popisky překrývaly. */
    $label_step = $count > 14 ? 5 : 1;
    $height = function ($value) use ($top) {
        $pct = $top > 0 ? $value / $top * 100 : 0;
        return $value > 0 ? max($pct, 1.5) : 0;
    };
    ?>
    <div class="czechia-analytics-chart-wrap" id="czechia-analytics-chart">
        <div class="czechia-analytics-chart">
            <div class="czechia-analytics-y" aria-hidden="true">
                <?php for ($i = 0; $i <= 4; $i++): ?>
                <span style="bottom:<?php echo $i * 25; ?>%"><?php echo esc_html(number_format_i18n($top / 4 * $i)); ?></span>
                <?php endfor; ?>
            </div>
            <div class="czechia-analytics-plot">
                <div class="czechia-analytics-gridlines" aria-hidden="true">
                    <?php for ($i = 0; $i <= 4; $i++): ?>
                    <span class="<?php echo $i === 0 ? 'is-base' : ''; ?>" style="bottom:<?php echo $i * 25; ?>%"></span>
                    <?php endfor; ?>
                </div>
                <div class="czechia-analytics-cols">
                    <?php foreach ($series as $i => $bucket): ?>
                    <div class="czechia-analytics-col<?php echo $bucket['partial'] ? ' is-partial' : ''; ?>"
                        tabindex="0"
                        data-title="<?php echo esc_attr($bucket['title']); ?>"
                        data-visitors="<?php echo esc_attr($bucket['visitors']); ?>"
                        data-visits="<?php echo esc_attr($bucket['visits']); ?>"
                        data-pageviews="<?php echo esc_attr($bucket['pageviews']); ?>"
                        aria-label="<?php echo esc_attr(sprintf('%s – návštěvníci: %s, návštěvy: %s, zobrazení: %s', $bucket['title'], number_format_i18n($bucket['visitors']), number_format_i18n($bucket['visits']), number_format_i18n($bucket['pageviews']))); ?>">
                        <div class="czechia-analytics-bars">
                            <span class="czechia-analytics-bar is-visitors" style="height:<?php echo esc_attr(round($height($bucket['visitors']), 2)); ?>%"></span>
                            <span class="czechia-analytics-bar is-pageviews" style="height:<?php echo esc_attr(round($height($bucket['pageviews']), 2)); ?>%"></span>
                        </div>
                        <span class="czechia-analytics-xlabel" aria-hidden="true"><?php echo (($count - 1 - $i) % $label_step === 0) ? esc_html($bucket['label']) : ''; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="czechia-analytics-tooltip" hidden></div>
    </div>

    <details class="czechia-analytics-table-toggle">
        <summary>Zobrazit data jako tabulku</summary>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Období</th>
                    <th class="num">Unikátní návštěvníci</th>
                    <th class="num">Návštěvy</th>
                    <th class="num">Zobrazení stránek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($series) as $bucket): ?>
                <tr>
                    <td><?php echo esc_html($bucket['title']); ?></td>
                    <td class="num"><?php echo esc_html(number_format_i18n($bucket['visitors'])); ?></td>
                    <td class="num"><?php echo esc_html(number_format_i18n($bucket['visits'])); ?></td>
                    <td class="num"><?php echo esc_html(number_format_i18n($bucket['pageviews'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </details>

    <script>
    (function(){
        var wrap = document.getElementById('czechia-analytics-chart');
        if (!wrap) return;
        var tip = wrap.querySelector('.czechia-analytics-tooltip');
        var fmt = new Intl.NumberFormat('cs-CZ');
        var rows = [
            ['visitors', 'Unikátní návštěvníci', 'var(--czechia-analytics-visitors)'],
            ['pageviews', 'Zobrazení stránek', 'var(--czechia-analytics-pageviews)'],
            ['visits', 'Návštěvy', '']
        ];

        function show(col) {
            tip.textContent = '';
            var title = document.createElement('div');
            title.className = 'czechia-analytics-tooltip-title';
            title.textContent = col.getAttribute('data-title');
            tip.appendChild(title);

            rows.forEach(function(r) {
                var row = document.createElement('div');
                var key = document.createElement('span');
                key.className = 'czechia-analytics-tooltip-key';
                if (r[2]) {
                    key.style.background = r[2];
                } else {
                    key.style.visibility = 'hidden';
                }
                var value = document.createElement('strong');
                value.textContent = fmt.format(Number(col.getAttribute('data-' + r[0])));
                row.appendChild(key);
                row.appendChild(value);
                row.appendChild(document.createTextNode(r[1]));
                tip.appendChild(row);
            });

            tip.hidden = false;

            var wrapRect = wrap.getBoundingClientRect();
            var colRect = col.getBoundingClientRect();
            var left = colRect.right - wrapRect.left + wrap.scrollLeft + 8;
            if (left + tip.offsetWidth > wrap.scrollLeft + wrap.clientWidth) {
                left = colRect.left - wrapRect.left + wrap.scrollLeft - tip.offsetWidth - 8;
            }
            tip.style.left = Math.max(0, left) + 'px';
        }

        function hide() {
            tip.hidden = true;
        }

        wrap.querySelectorAll('.czechia-analytics-col').forEach(function(col) {
            col.addEventListener('mouseenter', function() { show(col); });
            col.addEventListener('focus', function() { show(col); });
            col.addEventListener('mouseleave', hide);
            col.addEventListener('blur', hide);
        });
    })();
    </script>
    <?php
}

/* ─── Seznamy ─── */

function czechia_analytics_site_origin() {
    $parts = wp_parse_url(home_url());
    return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
}

function czechia_analytics_strip_site_name($title) {
    $names = [get_bloginfo('name')];
    if (function_exists('czechia_seo_get_blogname')) {
        $names[] = czechia_seo_get_blogname();
    }

    foreach (array_unique(array_filter($names)) as $name) {
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        if ($title === $name) return '';
        $quoted = preg_quote($name, '/');
        $title = preg_replace('/\s*[\-–—|·:\/]\s*' . $quoted . '$/u', '', $title);
        $title = preg_replace('/^' . $quoted . '\s*[\-–—|·:\/]\s*/u', '', $title);
    }

    return trim($title);
}

function czechia_analytics_page_label(array $row) {
    $home_path = trailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    if ($row['path'] === $home_path) return 'Domovská stránka';

    if (!empty($row['post_id'])) {
        $title = html_entity_decode(get_the_title((int) $row['post_id']), ENT_QUOTES, 'UTF-8');
        if ($title !== '') return $title;
    }

    $title = czechia_analytics_strip_site_name((string) $row['title']);
    return $title !== '' ? $title : rawurldecode($row['path']);
}

function czechia_analytics_render_top_pages(array $rows, $total, $range_label) {
    $origin = czechia_analytics_site_origin();
    ?>
    <div class="czechia-analytics-card">
        <h3>Nejnavštěvovanější stránky <small><?php echo esc_html($range_label); ?></small></h3>
        <?php if (empty($rows)): ?>
        <p class="czechia-analytics-empty">Za toto období nejsou žádná data.</p>
        <?php else: ?>
        <table class="czechia-analytics-list">
            <thead>
                <tr>
                    <th>Stránka</th>
                    <th class="num">Zobrazení</th>
                    <th class="num">Návštěvníci</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <?php $share = $total > 0 ? $row['pageviews'] / $total * 100 : 0; ?>
                <tr>
                    <td>
                        <a class="czechia-analytics-list-title" href="<?php echo esc_url($origin . $row['path']); ?>" target="_blank" rel="noopener"><?php echo esc_html(czechia_analytics_page_label($row)); ?></a>
                        <span class="czechia-analytics-list-sub"><?php echo esc_html(rawurldecode($row['path'])); ?></span>
                        <span class="czechia-analytics-share"><span style="width:<?php echo esc_attr(round($share, 1)); ?>%"></span></span>
                    </td>
                    <td class="num"><?php echo esc_html(number_format_i18n($row['pageviews'])); ?></td>
                    <td class="num"><?php echo esc_html(number_format_i18n($row['visitors'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function czechia_analytics_render_breakdown($title, array $rows, $total, $range_label, array $labels = [], $empty_label = 'Neznámé') {
    ?>
    <div class="czechia-analytics-card">
        <h3><?php echo esc_html($title); ?> <small><?php echo esc_html($range_label); ?></small></h3>
        <?php if (empty($rows)): ?>
        <p class="czechia-analytics-empty">Za toto období nejsou žádná data.</p>
        <?php else: ?>
        <table class="czechia-analytics-list">
            <thead>
                <tr>
                    <th><?php echo esc_html($title); ?></th>
                    <th class="num">Návštěvy</th>
                    <th class="num">Podíl</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <?php
                $name = $row['label'] === '' ? $empty_label : ($labels[$row['label']] ?? $row['label']);
                $share = $total > 0 ? $row['visits'] / $total * 100 : 0;
                ?>
                <tr>
                    <td>
                        <span class="czechia-analytics-list-title"><?php echo esc_html($name); ?></span>
                        <span class="czechia-analytics-share"><span style="width:<?php echo esc_attr(round($share, 1)); ?>%"></span></span>
                    </td>
                    <td class="num"><?php echo esc_html(number_format_i18n($row['visits'])); ?></td>
                    <td class="num"><?php echo esc_html(number_format_i18n($share)); ?> %</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

function czechia_analytics_render_not_found(array $rows, $range_label) {
    $origin = czechia_analytics_site_origin();
    ?>
    <div class="czechia-analytics-card">
        <h3>Nenalezené stránky (404) <small><?php echo esc_html($range_label); ?></small></h3>
        <p class="description">Adresy, na které návštěvníci narazili, ale neexistují. Opravte odkaz na stránce, odkud přišli, nebo nastavte přesměrování.</p>
        <?php if (empty($rows)): ?>
        <p class="czechia-analytics-empty">Žádné nenalezené stránky – výborně.</p>
        <?php else: ?>
        <table class="czechia-analytics-list">
            <thead>
                <tr>
                    <th>Adresa</th>
                    <th class="num">Zobrazení</th>
                    <th class="col-date">Naposledy</th>
                    <th>Poslední odkazující stránka</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <?php $last_seen = new DateTimeImmutable($row['last_seen'], wp_timezone()); ?>
                <tr>
                    <td><a class="czechia-analytics-list-title" href="<?php echo esc_url($origin . $row['path']); ?>" target="_blank" rel="noopener"><?php echo esc_html(rawurldecode($row['path'])); ?></a></td>
                    <td class="num"><?php echo esc_html(number_format_i18n($row['hits'])); ?></td>
                    <td class="col-date"><?php echo esc_html($last_seen->format('j. n. Y H:i')); ?></td>
                    <td>
                        <?php if (!empty($row['referrer'])): ?>
                        <a class="czechia-analytics-list-title" href="<?php echo esc_url($row['referrer']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(rawurldecode(preg_replace('#^[a-z-]+://(www\.)?#', '', $row['referrer']))); ?></a>
                        <?php else: ?>
                        <span class="czechia-analytics-list-sub">přímý přístup / neznámé</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

/* ─── Nastavení ─── */

function czechia_analytics_render_settings() {
    if (isset($_POST['czechia_analytics_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['czechia_analytics_nonce'])), 'czechia_analytics_save')) {
        $retention = absint($_POST['retention_months'] ?? 12);
        if (!isset(czechia_analytics_retention_options()[$retention])) {
            $retention = 12;
        }

        $ips = [];
        foreach (preg_split('/[\s,;]+/', (string) wp_unslash($_POST['excluded_ips'] ?? '')) as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        update_option('czechia_analytics_settings', [
            'exclude_logged_in' => isset($_POST['exclude_logged_in']) ? 1 : 0,
            'retention_months' => $retention,
            'excluded_ips' => implode("\n", array_unique($ips)),
        ]);

        echo '<div class="notice notice-success inline"><p>Nastavení bylo uloženo.</p></div>';
    }

    if (isset($_POST['czechia_analytics_reset_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['czechia_analytics_reset_nonce'])), 'czechia_analytics_reset')) {
        czechia_analytics_delete_all_data();
        echo '<div class="notice notice-success inline"><p>Všechna naměřená data byla smazána.</p></div>';
    }

    $s = czechia_analytics_get_settings();
    $my_ip = czechia_analytics_client_ip();
    ?>

    <form method="post">
        <?php wp_nonce_field('czechia_analytics_save', 'czechia_analytics_nonce'); ?>

        <h2>Měření</h2>
        <table class="form-table">
            <tr>
                <th>Přihlášení uživatelé</th>
                <td>
                    <label>
                        <input type="checkbox" name="exclude_logged_in" value="1" <?php checked($s['exclude_logged_in'], 1); ?>>
                        Nepočítat návštěvy přihlášených uživatelů (administrátorů, redaktorů…)
                    </label>
                </td>
            </tr>
            <tr>
                <th>Vyloučené IP adresy</th>
                <td>
                    <textarea name="excluded_ips" rows="4" class="large-text code" placeholder="Jedna IP adresa na řádek"><?php echo esc_textarea($s['excluded_ips']); ?></textarea>
                    <p class="description">Návštěvy z těchto adres se nezapočítají (např. kancelář). Adresy se používají jen pro porovnání, k naměřeným datům se neukládají.<?php if ($my_ip): ?> Vaše aktuální IP adresa: <code><?php echo esc_html($my_ip); ?></code><?php endif; ?></p>
                </td>
            </tr>
            <tr>
                <th>Uchovávat podrobná data</th>
                <td>
                    <select name="retention_months">
                        <?php foreach (czechia_analytics_retention_options() as $months => $label): ?>
                        <option value="<?php echo esc_attr($months); ?>" <?php selected((int) $s['retention_months'], $months); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Jednotlivá zobrazení (stránky, zdroje, zařízení, 404) se po této době automaticky mažou. Denní souhrny pro graf zůstávají zachovány.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary" value="Uložit nastavení">
        </p>
    </form>

    <div class="czechia-analytics-danger">
        <h2>Smazání dat</h2>
        <form method="post" onsubmit="return confirm('Opravdu smazat všechna naměřená data? Tuto akci nelze vrátit.');">
            <?php wp_nonce_field('czechia_analytics_reset', 'czechia_analytics_reset_nonce'); ?>
            <p>Smaže veškerou naměřenou návštěvnost včetně denních souhrnů. Nastavení zůstane zachováno.</p>
            <p><input type="submit" class="button" value="Smazat všechna data"></p>
        </form>
    </div>
    <?php
}
