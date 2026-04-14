<?php
defined('ABSPATH') || exit;

function czechia_seo_get_conflicts() {
    $known = [
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'seo-by-rank-math/rank-math.php' => 'Rank Math',
        'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
        'autodescription/autodescription.php' => 'The SEO Framework',
        'flavor/flavor.php' => 'flavor',
        'flavflavor-flavor/flavor.php' => 'flavor',
        'flavorseo/flavorseo.php' => 'FlavorSEO',
    ];

    $active = [];
    foreach ($known as $plugin => $name) {
        if (is_plugin_active($plugin)) {
            $active[$plugin] = $name;
        }
    }
    return $active;
}

function czechia_seo_conflict_notice() {
    $conflicts = czechia_seo_get_conflicts();
    if (empty($conflicts)) return;

    $names = implode(', ', $conflicts);
    echo '<div class="notice notice-warning" style="border-left-color:#ffb900;">';
    echo '<p><strong>⚠️ [CZECHIA SEO]</strong> ';
    echo 'Byl detekován aktivní SEO plugin: <strong>' . esc_html($names) . '</strong>. ';
    echo 'Doporučujeme používat pouze jeden SEO plugin, aby nedocházelo ke kolizím v meta tazích a titulcích.';
    echo '</p></div>';
}
add_action('admin_notices', 'czechia_seo_conflict_notice');
