<?php
defined('ABSPATH') || exit;

function czechia_seo_add_meta_box() {
    $s = czechia_seo_get_settings();
    $post_types = !empty($s['enabled_post_types']) ? $s['enabled_post_types'] : ['post', 'page'];

    foreach ($post_types as $pt) {
        add_meta_box(
            'czechia-seo-meta-box',
            'CZECHIA – SEO',
            'czechia_seo_render_meta_box',
            $pt,
            'normal',
            'high',
            [
                '__back_compat_meta_box' => true,
            ]
        );
    }
}
add_action('add_meta_boxes', 'czechia_seo_add_meta_box');

function czechia_seo_render_meta_box($post) {
    $s = czechia_seo_get_settings();
    $title = get_post_meta($post->ID, '_czechia_seo_title', true);
    $desc = get_post_meta($post->ID, '_czechia_seo_description', true);
    $noindex = get_post_meta($post->ID, '_czechia_seo_noindex', true);
    $nofollow = get_post_meta($post->ID, '_czechia_seo_nofollow', true);

    $blogname = czechia_seo_get_blogname();
    $sep = $s['title_separator'];
    $post_title_part = $post->post_title ?: 'Název příspěvku';
    $title_suffix = ' ' . $sep . ' ' . $blogname;

    if ($s['title_format'] === 'blog_first') {
        $title_prefix = $blogname . ' ' . $sep . ' ';
        $title_suffix_display = '';
        $full_title = $title_prefix . ($title ?: $post_title_part);
    } else {
        $title_prefix = '';
        $title_suffix_display = $title_suffix;
        $full_title = ($title ?: $post_title_part) . $title_suffix;
    }

    $placeholder_desc = $post->post_excerpt ?: wp_trim_words(strip_tags($post->post_content), 25, '…');
    if (empty($placeholder_desc)) $placeholder_desc = 'Popis příspěvku…';

    wp_nonce_field('czechia_seo_meta_box', 'czechia_seo_meta_nonce');
    ?>
    <div class="czechia-seo-metabox">
        <input type="hidden" id="czechia-seo-blogname" value="<?php echo esc_attr($blogname); ?>">
        <input type="hidden" id="czechia-seo-separator" value="<?php echo esc_attr($sep); ?>">
        <input type="hidden" id="czechia-seo-format" value="<?php echo esc_attr($s['title_format']); ?>">
        <input type="hidden" id="czechia-seo-post-title" value="<?php echo esc_attr($post_title_part); ?>">

        <input type="hidden" id="czechia-seo-ajax-url" value="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>">
        <input type="hidden" id="czechia-seo-ai-nonce" value="<?php echo esc_attr(wp_create_nonce('czechia_seo_ai')); ?>">

        <p class="czechia-seo-ai-intro">Správně nastavené SEO meta tagy jsou klíčové pro čitelnost vašeho webu v Googlu či Seznamu. Titulek říká, o čem stránka je, a popisek motivuje k prokliku. Vygenerujte si je pomocí ZONER AI: hlídá technické limity délky: texty generuje tak, aby se vešly do vymezeného prostoru a ve vyhledávačích se zobrazovaly celé a srozumitelné.</p>

        <div class="czechia-seo-field">
            <label for="czechia-seo-title"><strong>SEO titulek</strong></label>
            <div class="czechia-seo-title-wrapper">
                <?php if ($s['title_format'] === 'blog_first') : ?>
                    <span class="czechia-seo-title-affix czechia-seo-title-prefix"><?php echo esc_html($title_prefix); ?></span>
                <?php endif; ?>
                <input type="text" id="czechia-seo-title" name="_czechia_seo_title" class="czechia-seo-title-input czechia-seo-input" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr($post_title_part); ?>" data-counter="czechia-seo-title-counter" data-min="30" data-max="60" data-warn-max="70">
                <?php if ($s['title_format'] !== 'blog_first') : ?>
                    <span class="czechia-seo-title-affix czechia-seo-title-suffix"><?php echo esc_html($title_suffix_display); ?></span>
                <?php endif; ?>
            </div>
            <div class="czechia-seo-counter" id="czechia-seo-title-counter">
                <div class="czechia-seo-counter-bar"><div class="czechia-seo-counter-fill"></div></div>
                <span class="czechia-seo-counter-text"></span>
            </div>
        </div>

        <div class="czechia-seo-field">
            <label for="czechia-seo-description"><strong>SEO popis</strong></label>
            <textarea id="czechia-seo-description" name="_czechia_seo_description" class="large-text czechia-seo-input" rows="3" placeholder="<?php echo esc_attr($placeholder_desc); ?>" data-counter="czechia-seo-desc-counter" data-min="120" data-max="160" data-warn-max="200"><?php echo esc_textarea($desc); ?></textarea>
            <div class="czechia-seo-counter" id="czechia-seo-desc-counter">
                <div class="czechia-seo-counter-bar"><div class="czechia-seo-counter-fill"></div></div>
                <span class="czechia-seo-counter-text"></span>
            </div>
        </div>

        <div style="margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
            <button type="button" class="czechia-seo-ai-btn" id="czechia-seo-ai-generate">
                <span class="czechia-seo-ai-btn-icon dashicons dashicons-format-status"></span>
                <span class="czechia-seo-ai-btn-spinner" style="display:none;"></span>
                <span class="czechia-seo-ai-btn-text">Vygenerovat přes AI</span>
            </button>
            <span class="czechia-seo-ai-error" id="czechia-seo-ai-error" style="display:none;"></span>
        </div>

        <div class="czechia-seo-field czechia-seo-robots">
            <label style="margin-right:20px;">
                <input type="checkbox" name="_czechia_seo_noindex" value="1" <?php checked($noindex, '1'); ?>>
                Noindex <span class="description">(skrýt z vyhledávačů)</span>
            </label>
            <label>
                <input type="checkbox" name="_czechia_seo_nofollow" value="1" <?php checked($nofollow, '1'); ?>>
                Nofollow <span class="description">(nesledovat odkazy)</span>
            </label>
        </div>

        <div class="czechia-seo-serp-preview">
            <p><strong>Náhled ve vyhledávači:</strong></p>
            <div class="czechia-seo-serp">
                <div class="czechia-seo-serp-title" id="czechia-seo-serp-title"><?php echo esc_html($full_title); ?></div>
                <div class="czechia-seo-serp-url"><?php echo esc_html(get_permalink($post->ID) ?: home_url('/')); ?></div>
                <div class="czechia-seo-serp-desc" id="czechia-seo-serp-desc"><?php echo esc_html($desc ?: $placeholder_desc); ?></div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var titleInput = document.getElementById('czechia-seo-title');
        var descInput = document.getElementById('czechia-seo-description');
        var serpTitle = document.getElementById('czechia-seo-serp-title');
        var serpDesc = document.getElementById('czechia-seo-serp-desc');
        var seoSep = document.getElementById('czechia-seo-separator').value;
        var seoBlogname = document.getElementById('czechia-seo-blogname').value;
        var seoFormat = document.getElementById('czechia-seo-format').value;
        var seoPostTitle = document.getElementById('czechia-seo-post-title').value;

        /* Listen for WP post title changes (Classic Editor) */
        var wpTitleInput = document.getElementById('title');
        if (wpTitleInput) {
            wpTitleInput.addEventListener('input', function() {
                seoPostTitle = wpTitleInput.value || 'Název příspěvku';
                document.getElementById('czechia-seo-post-title').value = seoPostTitle;
                if (titleInput) titleInput.placeholder = seoPostTitle;
                updateSerp();
                updateTitleCounter();
            });
        }

        function buildFullTitle(titlePart) {
            var part = titlePart || seoPostTitle;
            if (seoFormat === 'blog_first') {
                return seoBlogname + ' ' + seoSep + ' ' + part;
            }
            return part + ' ' + seoSep + ' ' + seoBlogname;
        }

        function updateSerp() {
            serpTitle.textContent = buildFullTitle(titleInput.value);
            serpDesc.textContent = descInput.value || descInput.placeholder;
        }

        if (titleInput) titleInput.addEventListener('input', updateSerp);
        if (descInput) descInput.addEventListener('input', updateSerp);

        /* Title counter – counts full title length (input or post title + affix) */
        function updateTitleCounter() {
            var counterId = titleInput.getAttribute('data-counter');
            if (!counterId) return;
            var counter = document.getElementById(counterId);
            if (!counter) return;

            var min = parseInt(titleInput.getAttribute('data-min')) || 0;
            var max = parseInt(titleInput.getAttribute('data-max')) || 60;
            var warnMax = parseInt(titleInput.getAttribute('data-warn-max')) || max + 10;
            var fill = counter.querySelector('.czechia-seo-counter-fill');
            var text = counter.querySelector('.czechia-seo-counter-text');

            var titlePart = titleInput.value || seoPostTitle;
            var fullLen = buildFullTitle(titleInput.value).length;
            var pct = Math.min(fullLen / warnMax * 100, 100);
            fill.style.width = pct + '%';

            if (fullLen >= min && fullLen <= max) {
                fill.className = 'czechia-seo-counter-fill czechia-seo-good';
                text.textContent = fullLen + ' znaků – ideální délka';
            } else if (fullLen < min) {
                fill.className = 'czechia-seo-counter-fill czechia-seo-warn';
                text.textContent = fullLen + ' znaků – příliš krátké (min. ' + min + ')';
            } else if (fullLen > max && fullLen <= warnMax) {
                fill.className = 'czechia-seo-counter-fill czechia-seo-warn';
                text.textContent = fullLen + ' znaků – mírně dlouhé (max. doporučeno ' + max + ')';
            } else {
                fill.className = 'czechia-seo-counter-fill czechia-seo-bad';
                text.textContent = fullLen + ' znaků – příliš dlouhé!';
            }
        }

        if (titleInput) {
            titleInput.addEventListener('input', updateTitleCounter);
            updateTitleCounter();
        }

        /* Description counter – unchanged logic */
        document.querySelectorAll('.czechia-seo-metabox .czechia-seo-input:not(#czechia-seo-title)').forEach(function(input) {
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
                var len = input.value.length || input.placeholder.length;
                var pct = Math.min(len / warnMax * 100, 100);
                fill.style.width = pct + '%';

                if (len >= min && len <= max) {
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

        /* ─── AI generation ─── */
        var aiBtn = document.getElementById('czechia-seo-ai-generate');
        var aiError = document.getElementById('czechia-seo-ai-error');
        var aiAjaxUrl = document.getElementById('czechia-seo-ajax-url').value;
        var aiNonce = document.getElementById('czechia-seo-ai-nonce').value;

        function stripHtmlText(html) {
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            return (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
        }

        function getEditorContent() {
            /* 1. Gutenberg / Block Editor – read from wp.data store */
            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                var text = '';
                /* Try serializing blocks first */
                try {
                    var blocks = wp.data.select('core/block-editor').getBlocks();
                    if (blocks && blocks.length && wp.blocks && wp.blocks.serialize) {
                        text = stripHtmlText(wp.blocks.serialize(blocks));
                    }
                } catch(e) {}
                /* Fallback to content attribute */
                if (!text) {
                    try {
                        var raw = wp.data.select('core/editor').getEditedPostAttribute('content');
                        if (raw) text = stripHtmlText(raw);
                    } catch(e) {}
                }
                if (text) return text;
            }

            /* 2. Classic Editor – TinyMCE (Visual tab) */
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                var ed = tinymce.get('content');
                if (!ed.isHidden()) {
                    return ed.getContent({ format: 'text' }).replace(/\s+/g, ' ').trim();
                }
            }
            /* 3. Classic Editor – Text tab fallback */
            var textarea = document.getElementById('content');
            if (textarea && textarea.value) {
                return stripHtmlText(textarea.value);
            }
            return '';
        }

        function highlightField(el) {
            el.style.transition = 'background-color 0.3s ease';
            el.style.backgroundColor = '#e8f5e9';
            setTimeout(function() {
                el.style.backgroundColor = '#fff';
            }, 1000);
        }

        if (aiBtn) {
            aiBtn.addEventListener('click', function() {
                var text = getEditorContent();
                if (!text) {
                    aiError.textContent = 'Článek je prázdný – není co odeslat.';
                    aiError.style.display = 'inline';
                    return;
                }

                /* Disable button, show spinner */
                aiBtn.disabled = true;
                aiBtn.classList.add('czechia-seo-ai-loading');
                aiBtn.querySelector('.czechia-seo-ai-btn-icon').style.display = 'none';
                aiBtn.querySelector('.czechia-seo-ai-btn-spinner').style.display = 'inline-block';
                aiBtn.querySelector('.czechia-seo-ai-btn-text').textContent = 'Generuji…';
                aiError.style.display = 'none';

                var formData = new FormData();
                formData.append('action', 'czechia_seo_ai_generate');
                formData.append('nonce', aiNonce);
                formData.append('content', text);

                fetch(aiAjaxUrl, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (resp.success && resp.data) {
                            titleInput.value = resp.data.title;
                            descInput.value = resp.data.description;
                            titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                            descInput.dispatchEvent(new Event('input', { bubbles: true }));
                            highlightField(titleInput);
                            highlightField(descInput);
                        } else {
                            var msg = (resp.data && typeof resp.data === 'string') ? resp.data : 'Nastala chyba. Zkuste to znovu.';
                            aiError.textContent = msg;
                            aiError.style.display = 'inline';
                        }
                    })
                    .catch(function() {
                        aiError.textContent = 'Nastala chyba při komunikaci. Zkuste to znovu.';
                        aiError.style.display = 'inline';
                    })
                    .finally(function() {
                        aiBtn.disabled = false;
                        aiBtn.classList.remove('czechia-seo-ai-loading');
                        aiBtn.querySelector('.czechia-seo-ai-btn-icon').style.display = '';
                        aiBtn.querySelector('.czechia-seo-ai-btn-spinner').style.display = 'none';
                        aiBtn.querySelector('.czechia-seo-ai-btn-text').textContent = 'Vygenerovat přes AI';
                    });
            });
        }
    })();
    </script>
    <?php
}

function czechia_seo_save_meta_box($post_id) {
    if (!isset($_POST['czechia_seo_meta_nonce'])) return;
    if (!wp_verify_nonce($_POST['czechia_seo_meta_nonce'], 'czechia_seo_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = ['_czechia_seo_title', '_czechia_seo_description'];
    foreach ($fields as $key) {
        if (isset($_POST[$key])) {
            $val = sanitize_text_field($_POST[$key]);
            if ($key === '_czechia_seo_description') {
                $val = sanitize_textarea_field($_POST[$key]);
            }
            update_post_meta($post_id, $key, $val);
        }
    }

    $checkboxes = ['_czechia_seo_noindex', '_czechia_seo_nofollow'];
    foreach ($checkboxes as $key) {
        if (isset($_POST[$key]) && $_POST[$key] === '1') {
            update_post_meta($post_id, $key, '1');
        } else {
            delete_post_meta($post_id, $key);
        }
    }
}
add_action('save_post', 'czechia_seo_save_meta_box');
