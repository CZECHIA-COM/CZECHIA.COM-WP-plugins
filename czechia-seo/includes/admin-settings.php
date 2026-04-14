<?php
defined('ABSPATH') || exit;

function czechia_seo_render_settings() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['czechia_seo_nonce']) && wp_verify_nonce($_POST['czechia_seo_nonce'], 'czechia_seo_save')) {
        $old = czechia_seo_get_settings();

        $post_types = isset($_POST['czechia_seo_post_types']) && is_array($_POST['czechia_seo_post_types'])
            ? array_map('sanitize_text_field', $_POST['czechia_seo_post_types'])
            : [];

        $new = [
            'title_separator' => sanitize_text_field($_POST['title_separator'] ?? '–'),
            'title_format' => sanitize_text_field($_POST['title_format'] ?? 'post_first'),
            'blogname_override' => sanitize_text_field($_POST['blogname_override'] ?? ''),
            'homepage_title' => sanitize_text_field($_POST['homepage_title'] ?? ''),
            'homepage_description' => sanitize_textarea_field($_POST['homepage_description'] ?? ''),
            'og_default_image' => absint($_POST['og_default_image'] ?? 0),
            'enabled_post_types' => $post_types,
            'enable_sitemap' => isset($_POST['enable_sitemap']) ? 1 : 0,
        ];

        update_option('czechia_seo_settings', $new);

        if ($new['enable_sitemap'] !== ($old['enable_sitemap'] ?? 1)) {
            flush_rewrite_rules();
        }

        echo '<div class="notice notice-success"><p>Nastavení bylo uloženo.</p></div>';
    }

    $s = czechia_seo_get_settings();
    $logo = plugins_url('img/logo.svg', dirname(__FILE__));
    $separators = ['–', '|', '-', '·', ':', '/'];
    $all_types = get_post_types(['public' => true], 'objects');
    $conflicts = czechia_seo_get_conflicts();
    $og_image_url = '';
    if ($s['og_default_image']) {
        $og_image_url = wp_get_attachment_image_url($s['og_default_image'], 'medium');
    }
    ?>

    <div class="wrap">
        <div style="float:right; text-align:center; margin:15px 0 10px 20px;">
            <img src="<?php echo esc_url($logo); ?>" alt="Logo" width="120">
        </div>

        <h1>CZECHIA - SEO nastavení</h1>
        <p>Nastavte základní SEO parametry webu.</p>

        <?php if (!empty($conflicts)): ?>
        <div class="notice notice-warning" style="border-left-color:#ffb900;">
            <p><strong>⚠️ Detekované SEO pluginy:</strong> <?php echo esc_html(implode(', ', $conflicts)); ?></p>
            <p>Doporučujeme používat pouze jeden SEO plugin.</p>
        </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('czechia_seo_save', 'czechia_seo_nonce'); ?>

            <h2>Titulek stránky</h2>
            <table class="form-table">
                <tr>
                    <th>Oddělovač</th>
                    <td>
                        <?php foreach ($separators as $sep): ?>
                        <label style="margin-right:16px; font-size:18px; cursor:pointer;">
                            <input type="radio" name="title_separator" value="<?php echo esc_attr($sep); ?>" <?php checked($s['title_separator'], $sep); ?>>
                            <?php echo esc_html($sep); ?>
                        </label>
                        <?php endforeach; ?>
                        <p class="description">Oddělovač mezi názvem příspěvku a názvem webu.</p>
                    </td>
                </tr>
                <tr>
                    <th>Formát titulku</th>
                    <td>
                        <label style="margin-right:20px;">
                            <input type="radio" name="title_format" value="post_first" <?php checked($s['title_format'], 'post_first'); ?>>
                            Název příspěvku <?php echo esc_html($s['title_separator']); ?> Název webu
                        </label>
                        <label>
                            <input type="radio" name="title_format" value="blog_first" <?php checked($s['title_format'], 'blog_first'); ?>>
                            Název webu <?php echo esc_html($s['title_separator']); ?> Název příspěvku
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Název webu (pro SEO)</th>
                    <td>
                        <input type="text" name="blogname_override" class="regular-text" value="<?php echo esc_attr($s['blogname_override']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <p class="description">Ponechte prázdné pro použití názvu webu z Nastavení → Obecné.</p>
                    </td>
                </tr>
            </table>

            <h2>Domovská stránka</h2>
            <table class="form-table">
                <tr>
                    <th>SEO titulek</th>
                    <td>
                        <input type="text" name="homepage_title" class="large-text czechia-seo-input" value="<?php echo esc_attr($s['homepage_title']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" data-counter="homepage-title-counter" data-min="30" data-max="60" data-warn-max="70">
                        <div class="czechia-seo-counter" id="homepage-title-counter">
                            <div class="czechia-seo-counter-bar"><div class="czechia-seo-counter-fill"></div></div>
                            <span class="czechia-seo-counter-text"></span>
                        </div>
                        <p class="description">Doporučená délka: 30–60 znaků.</p>
                    </td>
                </tr>
                <tr>
                    <th>SEO popis</th>
                    <td>
                        <textarea name="homepage_description" class="large-text czechia-seo-input" rows="3" data-counter="homepage-desc-counter" data-min="120" data-max="160" data-warn-max="200" placeholder="Popis domovské stránky pro vyhledávače..."><?php echo esc_textarea($s['homepage_description']); ?></textarea>
                        <div class="czechia-seo-counter" id="homepage-desc-counter">
                            <div class="czechia-seo-counter-bar"><div class="czechia-seo-counter-fill"></div></div>
                            <span class="czechia-seo-counter-text"></span>
                        </div>
                        <p class="description">Doporučená délka: 120–160 znaků.</p>
                    </td>
                </tr>
            </table>

            <h2>Open Graph</h2>
            <table class="form-table">
                <tr>
                    <th>Výchozí OG obrázek</th>
                    <td>
                        <div id="czechia-seo-og-preview" style="margin-bottom:10px;">
                            <?php if ($og_image_url): ?>
                            <img src="<?php echo esc_url($og_image_url); ?>" style="max-width:300px; height:auto; border:1px solid #ccc; border-radius:4px;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="og_default_image" id="czechia-seo-og-image-id" value="<?php echo esc_attr($s['og_default_image']); ?>">
                        <button type="button" class="button" id="czechia-seo-og-select">Vybrat obrázek</button>
                        <button type="button" class="button" id="czechia-seo-og-remove" <?php if (!$s['og_default_image']) echo 'style="display:none;"'; ?>>Odebrat</button>
                        <p class="description">Doporučené rozměry: <strong>1200 × 630 px</strong>. Tento obrázek se použije, pokud příspěvek nemá náhledový obrázek.</p>
                    </td>
                </tr>
            </table>

            <h2>Typy obsahu</h2>
            <table class="form-table">
                <tr>
                    <th>SEO meta box zobrazit pro</th>
                    <td>
                        <?php foreach ($all_types as $pt): ?>
                        <?php if (in_array($pt->name, ['attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'wp_global_styles'])) continue; ?>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="czechia_seo_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $s['enabled_post_types'])); ?>>
                            <?php echo esc_html($pt->label); ?> <code><?php echo esc_html($pt->name); ?></code>
                        </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <h2>XML Sitemap</h2>
            <table class="form-table">
                <tr>
                    <th>Povolit sitemap</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_sitemap" value="1" <?php checked($s['enable_sitemap'], 1); ?>>
                            Generovat XML sitemap
                        </label>
                        <?php if ($s['enable_sitemap']): ?>
                        <p class="description"><a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank"><?php echo esc_html(home_url('/sitemap.xml')); ?></a></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="Uložit nastavení">
            </p>
        </form>
    </div>

    <script>
    (function(){
        // OG image picker
        var selectBtn = document.getElementById('czechia-seo-og-select');
        var removeBtn = document.getElementById('czechia-seo-og-remove');
        var imageId = document.getElementById('czechia-seo-og-image-id');
        var preview = document.getElementById('czechia-seo-og-preview');

        if (selectBtn) {
            selectBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: 'Vyberte OG obrázek',
                    button: { text: 'Použít obrázek' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    imageId.value = attachment.id;
                    var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    preview.innerHTML = '<img src="' + url + '" style="max-width:300px; height:auto; border:1px solid #ccc; border-radius:4px;">';

                    var w = attachment.width || 0;
                    var h = attachment.height || 0;
                    if (w < 1200 || h < 630) {
                        preview.innerHTML += '<p style="color:#d63638; margin-top:6px;">⚠️ Obrázek má rozměry ' + w + '×' + h + ' px. Doporučeno je minimálně 1200×630 px.</p>';
                    }

                    removeBtn.style.display = '';
                });
                frame.open();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                imageId.value = '0';
                preview.innerHTML = '';
                removeBtn.style.display = 'none';
            });
        }

        document.querySelectorAll('.czechia-seo-input').forEach(function(input) {
            var counterId = input.getAttribute('data-counter');
            if (!counterId) return;
            var counter = document.getElementById(counterId);
            if (!counter) return;

            var min = parseInt(input.getAttribute('data-min')) || 0;
            var max = parseInt(input.getAttribute('data-max')) || 60;
            var warnMax = parseInt(input.getAttribute('data-warn-max')) || max + 10;
            var fill = counter.querySelector('.czechia-seo-counter-fill');
            var text = counter.querySelector('.czechia-seo-counter-text');

            function update() {
                var len = input.value.length;
                var pct = Math.min(len / warnMax * 100, 100);
                fill.style.width = pct + '%';

                if (len === 0) {
                    fill.className = 'czechia-seo-counter-fill';
                    text.textContent = 'Zatím prázdné';
                } else if (len >= min && len <= max) {
                    fill.className = 'czechia-seo-counter-fill czechia-seo-good';
                    text.textContent = len + ' znaků – ideální délka';
                } else if (len < min) {
                    fill.className = 'czechia-seo-counter-fill czechia-seo-warn';
                    text.textContent = len + ' znaků – příliš krátké (min. ' + min + ')';
                } else if (len > max && len <= warnMax) {
                    fill.className = 'czechia-seo-counter-fill czechia-seo-warn';
                    text.textContent = len + ' znaků – mírně dlouhé (max. doporučeno ' + max + ')';
                } else {
                    fill.className = 'czechia-seo-counter-fill czechia-seo-bad';
                    text.textContent = len + ' znaků – příliš dlouhé!';
                }
            }

            input.addEventListener('input', update);
            update();
        });
    })();
    </script>
    <?php
}
