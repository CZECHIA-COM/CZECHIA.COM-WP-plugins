<?php
defined('ABSPATH') || exit;

function czechia_analytics_register_dashboard_widget() {
    if (!current_user_can('manage_options')) return;
    wp_add_dashboard_widget('czechia_analytics_dashboard', 'Návštěvnost', 'czechia_analytics_render_dashboard_widget');
}
add_action('wp_dashboard_setup', 'czechia_analytics_register_dashboard_widget');

function czechia_analytics_render_dashboard_widget() {
    $details_url = admin_url('admin.php?page=czechia-analytics');

    if (!czechia_analytics_has_data()) {
        $s = czechia_analytics_get_settings();
        ?>
        <div class="czechia-analytics-widget">
            <p class="czechia-analytics-empty">Zatím žádná data. Měření je aktivní, první návštěvy se tu objeví, jakmile na web přijdou návštěvníci.</p>
            <?php if ($s['exclude_logged_in']): ?>
            <p class="description">Přihlášení uživatelé (včetně vás) se nepočítají.</p>
            <?php endif; ?>
            <p class="czechia-analytics-widget-footer">
                <a href="<?php echo esc_url($details_url); ?>" class="button">Detaily</a>
            </p>
        </div>
        <?php
        return;
    }

    $views = czechia_analytics_views();
    ?>
    <div class="czechia-analytics czechia-analytics-widget" id="czechia-analytics-widget">
        <div class="czechia-analytics-widget-toolbar">
            <nav class="czechia-analytics-switch" aria-label="Období">
                <?php foreach (['day', 'month'] as $i => $view): ?>
                <a href="#" data-view="<?php echo esc_attr($view); ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>"><?php echo esc_html($views[$view]['current']); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php $active = czechia_analytics_active_visitors(); ?>
            <span class="czechia-analytics-live" title="Návštěvníci za posledních 30 minut">
                <span class="czechia-analytics-live-dot" aria-hidden="true"></span><strong><?php echo esc_html(number_format_i18n($active)); ?></strong> nyní
            </span>
        </div>

        <?php foreach (['day', 'month'] as $i => $view): ?>
        <div class="czechia-analytics-widget-panel" data-view="<?php echo esc_attr($view); ?>"<?php echo $i === 0 ? '' : ' hidden'; ?>>
            <?php czechia_analytics_render_widget_panel($view, $views[$view]); ?>
        </div>
        <?php endforeach; ?>

        <p class="czechia-analytics-widget-footer">
            <a href="<?php echo esc_url(add_query_arg('view', 'day', $details_url)); ?>" class="button button-primary" id="czechia-analytics-widget-details">Detaily</a>
        </p>
    </div>

    <script>
    (function(){
        var widget = document.getElementById('czechia-analytics-widget');
        if (!widget) return;
        var links = widget.querySelectorAll('.czechia-analytics-switch a');
        var panels = widget.querySelectorAll('.czechia-analytics-widget-panel');
        var details = document.getElementById('czechia-analytics-widget-details');
        var base = <?php echo wp_json_encode($details_url); ?>;

        function select(view) {
            links.forEach(function(a) { a.classList.toggle('active', a.getAttribute('data-view') === view); });
            panels.forEach(function(p) { p.hidden = p.getAttribute('data-view') !== view; });
            details.href = base + '&view=' + view;
            try { localStorage.setItem('czechiaAnalyticsWidgetView', view); } catch (e) {}
        }

        links.forEach(function(a) {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                select(a.getAttribute('data-view'));
            });
        });

        try {
            var saved = localStorage.getItem('czechiaAnalyticsWidgetView');
            if (saved === 'day' || saved === 'month') select(saved);
        } catch (e) {}
    })();
    </script>
    <?php
}

function czechia_analytics_render_widget_panel($view, array $labels) {
    $p = czechia_analytics_period($view);
    $current = czechia_analytics_totals($p['cur_start'], $p['end']);
    $previous = czechia_analytics_totals($p['prev_start'], $p['prev_same']);
    $series = czechia_analytics_widget_series($view, $p);

    $per_visit = function ($totals) {
        return $totals['visits'] > 0 ? $totals['pageviews'] / $totals['visits'] : 0;
    };

    $metrics = [
        ['Unikátní návštěvníci', $current['visitors'], $previous['visitors'], 0],
        ['Návštěvy', $current['visits'], $previous['visits'], 0],
        ['Zobrazení stránek', $current['pageviews'], $previous['pageviews'], 0],
        ['Stránek na návštěvu', $per_visit($current), $per_visit($previous), 1],
    ];
    ?>
    <div class="czechia-analytics-widget-kpis">
        <?php foreach ($metrics as $m): ?>
        <?php $delta = czechia_analytics_delta(round($m[1], $m[3]), round($m[2], $m[3])); ?>
        <div class="czechia-analytics-widget-kpi">
            <div class="czechia-analytics-kpi-label"><?php echo esc_html($m[0]); ?></div>
            <span class="czechia-analytics-widget-value"><?php echo esc_html(number_format_i18n($m[1], $m[3])); ?></span>
            <span class="czechia-analytics-kpi-delta <?php echo esc_attr($delta['class']); ?>" title="<?php echo esc_attr($labels['previous'] . ' ve stejný čas: ' . number_format_i18n($m[2], $m[3])); ?>"><?php echo esc_html($delta['text']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php czechia_analytics_render_widget_chart($series, $view === 'day' ? 'dnes po hodinách' : 'tento měsíc po dnech'); ?>
    <?php
}

/**
 * Data grafu za zvolené období: „Dnes“ = 24 hodin, „Tento měsíc“ = všechny dny měsíce.
 * Budoucí hodiny/dny mají hodnotu null (prázdný sloupec), aby graf vždy pokryl celé období.
 */
function czechia_analytics_widget_series($view, array $p) {
    global $wpdb;
    $series = [];

    if ($view === 'day') {
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT HOUR(created_at) AS h, COUNT(DISTINCT visitor_hash) AS visitors
             FROM ' . czechia_analytics_table('hits') . '
             WHERE created_at >= %s AND created_at < %s
             GROUP BY h',
            czechia_analytics_sql_date($p['today']),
            czechia_analytics_sql_date($p['end'])
        ), ARRAY_A);
        $by_hour = array_column($rows, 'visitors', 'h');
        $current_hour = (int) $p['now']->format('G');

        for ($h = 0; $h < 24; $h++) {
            $series[] = [
                'label' => $h . ':00',
                'title' => sprintf('%d:00–%d:59', $h, $h),
                'visitors' => $h > $current_hour ? null : (int) ($by_hour[$h] ?? 0),
                'partial' => $h === $current_hour,
            ];
        }
        return $series;
    }

    czechia_analytics_aggregate_days();
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT day, visitors FROM ' . czechia_analytics_table('daily') . ' WHERE day >= %s AND day < %s',
        $p['cur_start']->format('Y-m-d'),
        $p['today']->format('Y-m-d')
    ), ARRAY_A);
    $by_day = array_column($rows, 'visitors', 'day');
    $today = $p['today']->format('Y-m-d');
    $by_day[$today] = czechia_analytics_totals($p['today'], $p['end'])['visitors'];

    $month_end = $p['cur_start']->modify('first day of next month');
    for ($day = $p['cur_start']; $day < $month_end; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $series[] = [
            'label' => $day->format('j. n.'),
            'title' => czechia_analytics_bucket_title('day', $day),
            'visitors' => $key > $today ? null : (int) ($by_day[$key] ?? 0),
            'partial' => $key === $today,
        ];
    }
    return $series;
}

/* Naznačený graf – jen unikátní návštěvníci, bez os; hodnoty v tooltipu (title) a v aria-label. */
function czechia_analytics_render_widget_chart(array $series, $range) {
    $max = 0;
    foreach ($series as $bucket) {
        $max = max($max, (int) $bucket['visitors']);
    }
    $first = reset($series);
    $last = end($series);
    ?>
    <div class="czechia-analytics-widget-chart-title">
        Unikátní návštěvníci <small>· <?php echo esc_html($range); ?> · max. <?php echo esc_html(number_format_i18n($max)); ?></small>
    </div>
    <div class="czechia-analytics-spark" role="list">
        <?php foreach ($series as $bucket): ?>
        <?php
        $value = $bucket['visitors'];
        $height = ($max > 0 && $value) ? max($value / $max * 100, 3) : 0;
        $text = $bucket['title'] . ': ' . ($value === null ? '–' : number_format_i18n($value));
        ?>
        <span class="czechia-analytics-spark-col<?php echo $bucket['partial'] ? ' is-partial' : ''; ?>" role="listitem" title="<?php echo esc_attr($text); ?>" aria-label="<?php echo esc_attr($text); ?>">
            <span class="czechia-analytics-spark-bar" style="height:<?php echo esc_attr(round($height, 2)); ?>%"></span>
        </span>
        <?php endforeach; ?>
    </div>
    <div class="czechia-analytics-spark-axis" aria-hidden="true">
        <span><?php echo esc_html($first['label']); ?></span>
        <span><?php echo esc_html($last['label']); ?></span>
    </div>
    <?php
}
