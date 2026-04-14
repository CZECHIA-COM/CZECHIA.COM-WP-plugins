<?php
defined('ABSPATH') || exit;

function czechia_seo_document_title_parts($title_parts) {
    $s = czechia_seo_get_settings();
    $blogname = czechia_seo_get_blogname();

    if (is_front_page() || is_home()) {
        if (!empty($s['homepage_title'])) {
            $title_parts['title'] = $s['homepage_title'];
            unset($title_parts['tagline']);
            unset($title_parts['site']);
            return $title_parts;
        }
    }

    if (is_singular()) {
        $custom_title = get_post_meta(get_the_ID(), '_czechia_seo_title', true);
        if (!empty($custom_title)) {
            $title_parts['title'] = $custom_title;
        }
    }

    unset($title_parts['tagline']);
    $title_parts['site'] = $blogname;

    return $title_parts;
}
add_filter('document_title_parts', 'czechia_seo_document_title_parts', 20);

function czechia_seo_document_title_separator($sep) {
    $s = czechia_seo_get_settings();
    return $s['title_separator'];
}
add_filter('document_title_separator', 'czechia_seo_document_title_separator', 20);

function czechia_seo_document_title($title) {
    $s = czechia_seo_get_settings();
    if ($s['title_format'] !== 'blog_first') return $title;
    if (is_front_page() || is_home()) {
        if (!empty($s['homepage_title'])) return $title;
    }

    $blogname = czechia_seo_get_blogname();
    $sep = $s['title_separator'];

    $post_part = $title;
    $suffix = ' ' . $sep . ' ' . $blogname;
    if (substr($title, -strlen($suffix)) === $suffix) {
        $post_part = substr($title, 0, -strlen($suffix));
    }

    return $blogname . ' ' . $sep . ' ' . $post_part;
}
add_filter('document_title', 'czechia_seo_document_title', 20);

function czechia_seo_wp_head() {
    $s = czechia_seo_get_settings();
    $blogname = czechia_seo_get_blogname();

    $description = '';
    $og_title = '';
    $og_desc = '';
    $og_url = '';
    $og_type = 'website';
    $og_image = '';
    $og_image_w = '';
    $og_image_h = '';
    $robots = [];

    if (is_front_page() || is_home()) {
        $description = !empty($s['homepage_description']) ? $s['homepage_description'] : get_bloginfo('description');
        $og_title = !empty($s['homepage_title']) ? $s['homepage_title'] : $blogname;
        $og_desc = $description;
        $og_url = home_url('/');

        if ($s['og_default_image']) {
            $og_image = wp_get_attachment_url($s['og_default_image']);
            $meta = wp_get_attachment_metadata($s['og_default_image']);
            if ($meta) {
                $og_image_w = $meta['width'] ?? '';
                $og_image_h = $meta['height'] ?? '';
            }
        }
    } elseif (is_singular()) {
        $post_id = get_the_ID();
        $custom_title = get_post_meta($post_id, '_czechia_seo_title', true);
        $custom_desc = get_post_meta($post_id, '_czechia_seo_description', true);
        $noindex = get_post_meta($post_id, '_czechia_seo_noindex', true);
        $nofollow = get_post_meta($post_id, '_czechia_seo_nofollow', true);

        if (!empty($custom_desc)) {
            $description = $custom_desc;
        } else {
            $post = get_post($post_id);
            $description = $post->post_excerpt ?: wp_trim_words(strip_tags($post->post_content), 25, '…');
        }

        $post_title_part = !empty($custom_title) ? $custom_title : get_the_title($post_id);
        $sep = $s['title_separator'];
        if ($s['title_format'] === 'blog_first') {
            $og_title = $blogname . ' ' . $sep . ' ' . $post_title_part;
        } else {
            $og_title = $post_title_part . ' ' . $sep . ' ' . $blogname;
        }

        $og_desc = $description;
        $og_url = get_permalink($post_id);
        $og_type = (get_post_type($post_id) === 'post') ? 'article' : 'website';

        if (has_post_thumbnail($post_id)) {
            $thumb_id = get_post_thumbnail_id($post_id);
            $og_image = wp_get_attachment_url($thumb_id);
            $meta = wp_get_attachment_metadata($thumb_id);
            if ($meta) {
                $og_image_w = $meta['width'] ?? '';
                $og_image_h = $meta['height'] ?? '';
            }
        } elseif ($s['og_default_image']) {
            $og_image = wp_get_attachment_url($s['og_default_image']);
            $meta = wp_get_attachment_metadata($s['og_default_image']);
            if ($meta) {
                $og_image_w = $meta['width'] ?? '';
                $og_image_h = $meta['height'] ?? '';
            }
        }

        if ($noindex === '1') $robots[] = 'noindex';
        if ($nofollow === '1') $robots[] = 'nofollow';
    } elseif (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term && !empty($term->description)) {
            $description = wp_trim_words(strip_tags($term->description), 25, '…');
        }
        $og_title = single_term_title('', false) . ' ' . $s['title_separator'] . ' ' . $blogname;
        $og_desc = $description;
        $og_url = get_term_link($term);
        if (is_wp_error($og_url)) $og_url = home_url('/');
    }

    echo "\n<!-- CZECHIA SEO -->\n";

    if (!empty($description)) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    if (!empty($robots)) {
        echo '<meta name="robots" content="' . esc_attr(implode(', ', $robots)) . '">' . "\n";
    }

    if (is_singular()) {
        echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">' . "\n";
    } elseif (is_front_page()) {
        echo '<link rel="canonical" href="' . esc_url(home_url('/')) . '">' . "\n";
    }

    if (!empty($og_title)) {
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
    }
    if (!empty($og_desc)) {
        echo '<meta property="og:description" content="' . esc_attr($og_desc) . '">' . "\n";
    }
    if (!empty($og_type)) {
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
    }
    if (!empty($og_url)) {
        echo '<meta property="og:url" content="' . esc_url($og_url) . '">' . "\n";
    }
    echo '<meta property="og:site_name" content="' . esc_attr($blogname) . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";

    if (!empty($og_image)) {
        echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        if ($og_image_w) echo '<meta property="og:image:width" content="' . esc_attr($og_image_w) . '">' . "\n";
        if ($og_image_h) echo '<meta property="og:image:height" content="' . esc_attr($og_image_h) . '">' . "\n";
    }

    $twitter_card = !empty($og_image) ? 'summary_large_image' : 'summary';
    echo '<meta name="twitter:card" content="' . esc_attr($twitter_card) . '">' . "\n";
    if (!empty($og_title)) {
        echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";
    }
    if (!empty($og_desc)) {
        echo '<meta name="twitter:description" content="' . esc_attr($og_desc) . '">' . "\n";
    }
    if (!empty($og_image)) {
        echo '<meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";
    }

    echo "<!-- /CZECHIA SEO -->\n\n";
}
add_action('wp_head', 'czechia_seo_wp_head', 1);

function czechia_seo_remove_wp_default_robots() {
    remove_filter('wp_robots', 'wp_robots_max_image_preview_large');
}
add_action('template_redirect', 'czechia_seo_remove_wp_default_robots');
