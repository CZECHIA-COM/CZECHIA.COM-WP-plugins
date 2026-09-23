<?php
defined('ABSPATH') || exit;

function czechia_analytics_now() {
    return new DateTimeImmutable('now', wp_timezone());
}

function czechia_analytics_sql_date(DateTimeInterface $date) {
    return $date->format('Y-m-d H:i:s');
}

function czechia_analytics_retention_cutoff() {
    $s = czechia_analytics_get_settings();
    $months = max(1, (int) $s['retention_months']);
    return czechia_analytics_now()->setTime(0, 0)->modify("-{$months} months");
}

function czechia_analytics_has_data() {
    global $wpdb;
    return (bool) $wpdb->get_var('SELECT 1 FROM ' . czechia_analytics_table('hits') . ' LIMIT 1')
        || (bool) $wpdb->get_var('SELECT 1 FROM ' . czechia_analytics_table('daily') . ' LIMIT 1');
}

function czechia_analytics_aggregate_days() {
    global $wpdb;
    $hits = czechia_analytics_table('hits');
    $daily = czechia_analytics_table('daily');
    $today = czechia_analytics_now()->format('Y-m-d');

    $from = get_option('czechia_analytics_aggregated_until', '');
    if ($from === '') {
        $first = $wpdb->get_var("SELECT MIN(created_at) FROM $hits");
        $from = $first ? substr($first, 0, 10) : $today;
    }

    if ($from < $today) {
        $wpdb->query($wpdb->prepare(
            "REPLACE INTO $daily (day, pageviews, visits, visitors, not_found)
             SELECT DATE(created_at), COUNT(*), SUM(is_entry), COUNT(DISTINCT visitor_hash), SUM(is_404)
             FROM $hits
             WHERE created_at >= %s AND created_at < %s
             GROUP BY DATE(created_at)",
            $from . ' 00:00:00',
            $today . ' 00:00:00'
        ));
    }

    update_option('czechia_analytics_aggregated_until', $today, false);

    czechia_analytics_aggregate_months();
}

function czechia_analytics_aggregate_months() {
    global $wpdb;
    $hits = czechia_analytics_table('hits');
    $daily = czechia_analytics_table('daily');
    $monthly = czechia_analytics_table('monthly');

    $first_day = $wpdb->get_var("SELECT MIN(day) FROM $daily");
    if (!$first_day) return;

    $current = czechia_analytics_now()->format('Y-m');
    $done = array_flip($wpdb->get_col("SELECT month FROM $monthly"));
    $oldest_hit = (string) $wpdb->get_var("SELECT MIN(created_at) FROM $hits");

    for ($month = new DateTimeImmutable(substr($first_day, 0, 7) . '-01', wp_timezone()); $month->format('Y-m') < $current; $month = $month->modify('+1 month')) {
        $key = $month->format('Y-m');
        if (isset($done[$key])) continue;

        $next = $month->modify('+1 month');

        $complete = $oldest_hit !== '' && (substr($oldest_hit, 0, 10) <= $month->format('Y-m-d') || substr($oldest_hit, 0, 10) <= $first_day);
        if ($complete) {
            $visitors = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT visitor_hash) FROM $hits WHERE created_at >= %s AND created_at < %s",
                czechia_analytics_sql_date($month),
                czechia_analytics_sql_date($next)
            ));
        } else {
            $visitors = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(visitors), 0) FROM $daily WHERE day >= %s AND day < %s",
                $month->format('Y-m-d'),
                $next->format('Y-m-d')
            ));
        }

        $wpdb->replace($monthly, ['month' => $key, 'visitors' => (int) $visitors], ['%s', '%d']);
    }
}

function czechia_analytics_totals(DateTimeInterface $from, DateTimeInterface $to) {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT COUNT(*) AS pageviews, COALESCE(SUM(is_entry), 0) AS visits, COUNT(DISTINCT visitor_hash) AS visitors
         FROM ' . czechia_analytics_table('hits') . '
         WHERE created_at >= %s AND created_at < %s',
        czechia_analytics_sql_date($from),
        czechia_analytics_sql_date($to)
    ), ARRAY_A);

    return [
        'pageviews' => (int) ($row['pageviews'] ?? 0),
        'visits' => (int) ($row['visits'] ?? 0),
        'visitors' => (int) ($row['visitors'] ?? 0),
    ];
}

function czechia_analytics_period($view) {
    $now = czechia_analytics_now();
    $today = $now->setTime(0, 0);

    if ($view === 'week') {
        $offset = ((int) $today->format('w') - (int) get_option('start_of_week', 1) + 7) % 7;
        $cur_start = $today->modify("-{$offset} days");
        $prev_start = $cur_start->modify('-7 days');
        $prev_same = $now->modify('-7 days');
        $range_start = $cur_start->modify('-11 weeks');
    } elseif ($view === 'month') {
        $cur_start = $today->modify('first day of this month');
        $prev_start = $cur_start->modify('-1 month');
        $elapsed = $now->getTimestamp() - $cur_start->getTimestamp();
        $prev_same = min($prev_start->modify("+{$elapsed} seconds"), $cur_start);
        $range_start = $cur_start->modify('-11 months');
    } else {
        $cur_start = $today;
        $prev_start = $today->modify('-1 day');
        $prev_same = $now->modify('-1 day');
        $range_start = $today->modify('-29 days');
    }

    return [
        'now' => $now,
        'today' => $today,
        'end' => $today->modify('+1 day'),
        'cur_start' => $cur_start,
        'prev_start' => $prev_start,
        'prev_same' => $prev_same,
        'range_start' => $range_start,
    ];
}

function czechia_analytics_series($view, array $p) {
    global $wpdb;
    czechia_analytics_aggregate_days();

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT day, pageviews, visits, visitors FROM ' . czechia_analytics_table('daily') . ' WHERE day >= %s AND day < %s',
        $p['range_start']->format('Y-m-d'),
        $p['today']->format('Y-m-d')
    ), ARRAY_A);

    $days = [];
    foreach ($rows as $row) {
        $days[$row['day']] = $row;
    }
    $days[$p['today']->format('Y-m-d')] = czechia_analytics_totals($p['today'], $p['end']);

    $start_of_week = (int) get_option('start_of_week', 1);
    $buckets = [];
    for ($day = $p['range_start']; $day <= $p['today']; $day = $day->modify('+1 day')) {
        if ($view === 'week') {
            $start = $day->modify('-' . (((int) $day->format('w') - $start_of_week + 7) % 7) . ' days');
            $key = $start->format('Y-m-d');
        } elseif ($view === 'month') {
            $start = $day->modify('first day of this month');
            $key = $start->format('Y-m');
        } else {
            $start = $day;
            $key = $day->format('Y-m-d');
        }

        if (!isset($buckets[$key])) {
            $buckets[$key] = ['start' => $start, 'visitors' => 0, 'visits' => 0, 'pageviews' => 0];
        }

        $data = $days[$day->format('Y-m-d')] ?? null;
        if ($data) {
            $buckets[$key]['visitors'] += (int) $data['visitors'];
            $buckets[$key]['visits'] += (int) $data['visits'];
            $buckets[$key]['pageviews'] += (int) $data['pageviews'];
        }
    }

    if ($view === 'week') {
        $weekly = czechia_analytics_weekly_visitors($p['range_start'], $p['end']);
        foreach ($buckets as $key => &$bucket) {
            $bucket['visitors'] = $weekly[$key] ?? 0;
        }
        unset($bucket);
    } elseif ($view === 'month') {
        $monthly = $wpdb->get_results($wpdb->prepare(
            'SELECT month, visitors FROM ' . czechia_analytics_table('monthly') . ' WHERE month >= %s',
            $p['range_start']->format('Y-m')
        ), ARRAY_A);
        $monthly = array_column($monthly, 'visitors', 'month');
        $monthly[$p['cur_start']->format('Y-m')] = czechia_analytics_totals($p['cur_start'], $p['end'])['visitors'];
        foreach ($buckets as $key => &$bucket) {
            if (isset($monthly[$key])) {
                $bucket['visitors'] = (int) $monthly[$key];
            }
        }
        unset($bucket);
    }

    $buckets = array_values($buckets);
    $last = count($buckets) - 1;
    foreach ($buckets as $i => &$bucket) {
        $bucket['partial'] = $i === $last;
        $bucket['label'] = czechia_analytics_bucket_label($view, $bucket['start'], $i === 0);
        $bucket['title'] = czechia_analytics_bucket_title($view, $bucket['start']) . ($i === $last ? ' (zatím)' : '');
    }
    unset($bucket);

    return $buckets;
}

function czechia_analytics_weekly_visitors(DateTimeInterface $from, DateTimeInterface $to) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT DATE_SUB(DATE(created_at), INTERVAL ((DAYOFWEEK(created_at) - 1 - %d + 7) %% 7) DAY) AS week_start,
                COUNT(DISTINCT visitor_hash) AS visitors
         FROM ' . czechia_analytics_table('hits') . '
         WHERE created_at >= %s AND created_at < %s
         GROUP BY week_start',
        (int) get_option('start_of_week', 1),
        czechia_analytics_sql_date($from),
        czechia_analytics_sql_date($to)
    ), ARRAY_A);
    return array_map('intval', array_column($rows, 'visitors', 'week_start'));
}

function czechia_analytics_month_names($short = false) {
    return $short
        ? ['led', 'úno', 'bře', 'dub', 'kvě', 'čvn', 'čvc', 'srp', 'zář', 'říj', 'lis', 'pro']
        : ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];
}

function czechia_analytics_bucket_label($view, DateTimeImmutable $start, $first) {
    if ($view === 'month') {
        $month = (int) $start->format('n');
        $label = czechia_analytics_month_names(true)[$month - 1];
        return ($first || $month === 1) ? $label . ' ' . $start->format('y') : $label;
    }
    return $start->format('j. n.');
}

function czechia_analytics_bucket_title($view, DateTimeImmutable $start) {
    if ($view === 'month') {
        return czechia_analytics_month_names()[(int) $start->format('n') - 1] . ' ' . $start->format('Y');
    }
    if ($view === 'week') {
        return $start->format('j. n.') . ' – ' . $start->modify('+6 days')->format('j. n. Y');
    }
    $days = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];
    return $days[(int) $start->format('w')] . ' ' . $start->format('j. n. Y');
}

function czechia_analytics_top_pages(DateTimeInterface $from, DateTimeInterface $to, $limit = 5) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT path, MAX(post_id) AS post_id, MAX(title) AS title, COUNT(*) AS pageviews, COUNT(DISTINCT visitor_hash) AS visitors
         FROM ' . czechia_analytics_table('hits') . '
         WHERE created_at >= %s AND created_at < %s AND is_404 = 0
         GROUP BY path
         ORDER BY pageviews DESC
         LIMIT %d',
        czechia_analytics_sql_date($from),
        czechia_analytics_sql_date($to),
        $limit
    ), ARRAY_A);
}

function czechia_analytics_breakdown($dimension, DateTimeInterface $from, DateTimeInterface $to, $limit = 5) {
    global $wpdb;
    $columns = [
        'source' => 'source',
        'device' => 'device',
        'browser' => 'browser',
        'os' => 'os',
        'screen' => "CONCAT(screen_w, ' × ', screen_h)",
    ];
    if (!isset($columns[$dimension])) return [];

    $extra = $dimension === 'screen' ? ' AND screen_w > 0' : '';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT {$columns[$dimension]} AS label, COUNT(*) AS visits
         FROM " . czechia_analytics_table('hits') . "
         WHERE is_entry = 1 AND created_at >= %s AND created_at < %s{$extra}
         GROUP BY label
         ORDER BY visits DESC
         LIMIT %d",
        czechia_analytics_sql_date($from),
        czechia_analytics_sql_date($to),
        $limit
    ), ARRAY_A);
}

function czechia_analytics_not_found(DateTimeInterface $from, DateTimeInterface $to, $limit = 10) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT path, COUNT(*) AS hits, MAX(created_at) AS last_seen,
                SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(referrer, '') ORDER BY id DESC SEPARATOR ' '), ' ', 1) AS referrer
         FROM " . czechia_analytics_table('hits') . "
         WHERE is_404 = 1 AND created_at >= %s AND created_at < %s
         GROUP BY path
         ORDER BY hits DESC, last_seen DESC
         LIMIT %d",
        czechia_analytics_sql_date($from),
        czechia_analytics_sql_date($to),
        $limit
    ), ARRAY_A);
}

function czechia_analytics_active_visitors() {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(DISTINCT visitor_hash) FROM ' . czechia_analytics_table('hits') . ' WHERE created_at >= %s',
        czechia_analytics_sql_date(czechia_analytics_now()->modify('-30 minutes'))
    ));
}

function czechia_analytics_nice_max($value) {
    if ($value <= 4) return 4;
    $raw = $value / 4;
    $magnitude = pow(10, floor(log10($raw)));
    foreach ([1, 2, 5, 10] as $step) {
        if ($step * $magnitude >= $raw) {
            return (int) ($step * $magnitude * 4);
        }
    }
    return (int) ceil($value);
}
