<?php
/*
Plugin Name: Zoner AI
Description: Využívejte umělou inteligenci od Zoneru.
Version: 1.0
Author: Zoner a.s.
*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Zoner AI - UI
 */
add_action( 'post-upload-ui', 'zoner_ai_main' );

function zoner_ai_main()
{
    $nonce = wp_create_nonce("zonerai-generate");
    ?>
    <div class="zonerai-wrap" style="text-align: center;">
        <div style="margin-bottom: 15px;">nebo</div>
        <button type="button" class="button button-secondary zonerai-open-modal">🪄 Zoner AI</button>
    </div>

    <div id="zonerai-modal" style="display:none;position:fixed;left:0;top:0;width:100vw;height:100vh;z-index:100000;align-items:flex-start;justify-content:center;background:rgba(0,0,0,0.4);">
        <div style="background:#fff;max-width:700px;margin:60px auto 0 auto;padding:24px 28px 28px 28px;border-radius:10px;box-shadow:0 6px 32px #2222;position:relative;">
            <a href="#" id="zonerai-close" style="position:absolute;right:14px;top:10px;font-size:32px;text-decoration:none;">&times;</a>

            <h2 style="margin-top:10px; margin-bottom: 20px;">Zoner AI – generování obrázku</h2>

            <div style="margin-bottom:16px;display:flex;align-items:center;">
                <input type="text" id="zonerai-prompt" placeholder="Zadejte popis obrázku..." style="width:56%;margin-bottom:10px;" />
                <button id="zonerai-generate" class="button button-primary" style="margin-left:8px;">Vygenerovat</button>
                <button id="zonerai-reload" class="button" style="margin-left:8px;opacity:0.5;pointer-events:none;" title="Znovu vygenerovat další 3 varianty">
                    <span class="dashicons dashicons-update" style="vertical-align:middle;font-size:19px;"></span>
                </button>
            </div>

            <div id="zonerai-advanced-toggle" style="margin-bottom:8px;font-size:14px; color:#2271b1; cursor:pointer; display:inline-block;">+ Rozšířené možnosti</div>

            <div id="zonerai-advanced" style="display:none;margin-bottom:8px;">
                <label style="margin-right:18px;"><input type="checkbox" id="zonerai-removebg" style="vertical-align:-2px;"/> Odstranit pozadí</label>
                <label style="margin-right:12px;">
                    Jazyk:
                    <select id="zonerai-language" style="margin-left:3px;">
                        <option value="ces_Latn">Čeština</option>
                        <option value="eng_Latn">Angličtina</option>
                    </select>
                </label>

                <label>
                    Velikost:
                    <select id="zonerai-size" style="margin-left:3px;">
                        <option value="1920x1080">1920x1080 (FullHD 16:9)</option>
                        <option value="1024x1024">1024x1024 (1:1)</option>
                        <option value="1408x1408">1408x1408 (1:1)</option>
                        <option value="1152x896">1152x896 (4:3)</option>
                        <option value="1664x1216">1664x1216 (4:3)</option>
                        <option value="1344x768">1344x768 (16:9)</option>
                        <option value="3840x2160">3840x2160 (4K 16:9)</option>
                        <option value="1536x640">1536x640 (21:9)</option>
                        <option value="2176x960">2176x960 (21:9)</option>
                        <option value="1728x1152">1728x1152 (3:2)</option>
                        <option value="1216x832">1216x832 (3:2)</option>
                        <option value="832x1216">832x1216 (2:3)</option>
                    </select>
                </label>
            </div>

            <div id="zonerai-info" style="font-size:13px;color:#888;margin-bottom:10px;display:none;">
                Kliknutím na libovolný náhled jej uložíte do knihovny médií.
            </div>

            <div id="zonerai-spinner" style="margin:18px 0 10px 0;display:none;">
                <span class="spinner is-active"></span> Generuji...
            </div>

            <div id="zonerai-preview" style="margin-top:12px;display:flex;flex-direction:column;gap:20px;"></div>
        </div>
    </div>

    <style>
        .zonerai-variant {
            width: 320px !important;
            height: auto !important;
            max-width: 99%;
            border: 2px solid #eee;
            border-radius: 9px;
            box-shadow: 0 2px 9px #0001;
            cursor: pointer;
            transition: 0.2s;
            margin-bottom: 2px;
            margin-right: 10px;
            position: relative;
            background: #fafaff;
        }
        .zonerai-group {
            display:flex;
            gap:18px;
            justify-content:center;
            margin-bottom:2px;
        }
        .zonerai-upload-overlay {
            position:absolute;
            left:0;top:0;right:0;bottom:0;
            display:flex;
            align-items:center;justify-content:center;
            background:rgba(255,255,255,0.7);
            font-size:17px;color:#333;
            border-radius:9px;
        }
    </style>

    <script>
    jQuery(function($){
        let lastPrompt = "";
        let lastOptions = {};

        function getOptions() {
            return {
                removebg: $('#zonerai-removebg').is(':checked'),
                language: $('#zonerai-language').val(),
                size: $('#zonerai-size').val()
            };
        }

        $('.zonerai-open-modal').on('click', function(){
            $('#zonerai-modal').fadeIn(120);
        });

        $('#zonerai-close').on('click', function(e){
            e.preventDefault(); $('#zonerai-modal').fadeOut(120);
        });

        $(document).on('click', function(e){
            if(e.target.id === 'zonerai-modal') $('#zonerai-modal').fadeOut(120);
        });

        $('#zonerai-advanced-toggle').on('click', function() {
            $('#zonerai-advanced').slideDown();
            $(this).hide();
        });

        function enableReload() {
            $('#zonerai-reload').css({opacity:1, pointerEvents:'auto'});
        }
        function disableReload() {
            $('#zonerai-reload').css({opacity:0.5, pointerEvents:'none'});
        }

        function generateVariants(prompt, options) {
            $('#zonerai-spinner').show();
            $('#zonerai-info').hide();

            let loaded = 0;
            var $group = $('<div class="zonerai-group"></div>');

            function showAll() {
                $('#zonerai-spinner').hide();
                $('#zonerai-info').show();
                $('#zonerai-preview').prepend($group);
                enableReload();
            }

            for(let i=0;i<3;i++) {
                $.post(ajaxurl, {
                    action: 'zonerai_generate_preview',
                    prompt: prompt,
                    _ajax_nonce: '<?php echo $nonce; ?>',
                    RemoveBg: options.removebg ? 'True' : 'False',
                    Language: options.language,
                    Size: options.size
                }, function(res){
                    if(res.success && res.data && res.data.base64)
                    {
                        var $imgWrap = $('<div style="position:relative;display:inline-block;"></div>');
                        var $img = $('<img class="zonerai-variant" title="Klikni pro uložení do knihovny médií" />');
                        $img.attr('src', res.data.base64);
                        $img.attr('data-prompt', prompt);

                        $img.on('mouseenter', function(){
                            $(this).css('border-color','#21759b');
                        }).on('mouseleave', function(){
                            $(this).css('border-color','#eee');
                        });

                        $img.on('click', function()
                        {
                            $imgWrap.find('.zonerai-upload-overlay').remove();

                            var imgdata = $(this).attr('src');
                            var promptText = $(this).attr('data-prompt');
                            var $orig = $(this);

                            var $overlay = $('<div class="zonerai-upload-overlay">Ukládám...</div>');
                            $imgWrap.append($overlay);

                            $.post(ajaxurl, {
                                action: 'zonerai_upload_selected',
                                prompt: promptText,
                                imgdata: imgdata,
                                _ajax_nonce: '<?php echo $nonce; ?>',
                                RemoveBg: options.removebg ? 'True' : 'False',
                                Language: options.language,
                                Size: options.size
                            }, function(resp){
                                $imgWrap.find('.zonerai-upload-overlay').remove();
                                if(resp.success && resp.data && resp.data.url){
                                    $imgWrap.append('<div class="zonerai-upload-overlay" style="background:rgba(240,255,240,0.9);color:green;">✔ Uloženo!</div>');
                                    setTimeout(function(){
                                        $imgWrap.find('.zonerai-upload-overlay').fadeOut(400, function(){ $(this).remove(); });
                                    }, 1200);

                                    if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                                        if(wp.media.frame.content.get() !== null) {
                                            wp.media.frame.content.mode('browse').get().collection.props.set({ ignore: (+ new Date()) });
                                            wp.media.frame.state().get('selection').add(wp.media.attachment(resp.data.id));
                                        }
                                    }
                                } else {
                                    $imgWrap.append('<div class="zonerai-upload-overlay" style="background:rgba(255,230,230,0.95);color:red;">Chyba při nahrání.</div>');
                                    setTimeout(function(){
                                        $imgWrap.find('.zonerai-upload-overlay').fadeOut(400, function(){ $(this).remove(); });
                                    }, 2000);
                                }
                            });
                        });

                        $imgWrap.append($img);
                        $group.append($imgWrap);
                    }
                    loaded++;
                    if(loaded===3) showAll();
                });
            }
        }

        $('#zonerai-generate').on('click', function(){
            var prompt = $('#zonerai-prompt').val();
            if(!prompt) return alert('Zadejte popis obrázku.');
            lastPrompt = prompt;
            lastOptions = getOptions();
            generateVariants(prompt, lastOptions);
        });

        $('#zonerai-reload').on('click', function(){
            if(!lastPrompt) return;
            disableReload();
            generateVariants(lastPrompt, lastOptions);
        });
    });
    </script>
    <?php
}

/**
 * Generate preview
 */
add_action( 'wp_ajax_zonerai_generate_preview', 'zoner_ai_generate_preview' );

function zoner_ai_generate_preview()
{
    check_ajax_referer( 'zonerai-generate' );

    $prompt = sanitize_text_field( isset( $_POST['prompt'] ) ? $_POST['prompt'] : '' );
    if( !$prompt )
    {
        wp_send_json_error( 'Zadejte prosím popis obrázku, který chcete vygenerovat.' );
    }

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, 'https://ai.zoner.com/console/api/wordpress/Txt2Img' );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, [
        'Prompt'    => $prompt,
        'apiKey'    => '46D3426B-4693-472A-9FBA-96BDB51F8FFD',
        'Language'  => sanitize_text_field( isset( $_POST['Language'] ) ? $_POST['Language'] : 'ces_Latn' ),
        'RemoveBg'  => sanitize_text_field( isset( $_POST['RemoveBg'] ) ? $_POST['RemoveBg'] : 'False' ),
        'Size'      => sanitize_text_field( isset( $_POST['Size'] ) ? $_POST['Size'] : '1920x1080' )
    ]);

    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $img = curl_exec( $ch );
    curl_close( $ch );

    if ( !$img ) wp_send_json_error( 'Nepodařilo se získat obrázek ze služby Zoner AI.' );

    $b64 = 'data:image/png;base64,' . base64_encode( $img );
    wp_send_json_success( [ 'base64' => $b64 ] );
}

/**
 * Handle image upload
 */
add_action( 'wp_ajax_zonerai_upload_selected', 'zoner_ai_handle_upload' );

function zoner_ai_handle_upload()
{
    check_ajax_referer( 'zonerai-generate' );

    if ( empty( $_POST['imgdata'] ) || empty( $_POST['prompt'] ) )
    {
        wp_send_json_error( 'Chybí obrázek nebo popis obrázku.' );
    }

    $imgdata = $_POST['imgdata'];
    $prompt = sanitize_text_field( $_POST['prompt'] );

    $imgdata = preg_replace( '#^data:image/\w+;base64,#i', '', $imgdata );
    $bin = base64_decode( $imgdata );

    if ( !$bin ) wp_send_json_error('Chyba při dekódování obrázku.');

    $upload_dir = wp_upload_dir();
    $filename = 'zonerai-' . uniqid() . '.png';
    $file_path = $upload_dir['path'] . '/' . $filename;
    file_put_contents( $file_path, $bin );

    $attachment = [
        'post_mime_type' => 'image/png',
        'post_title'     => $prompt,
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];

    $attach_id = wp_insert_attachment( $attachment, $file_path );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    $src = wp_get_attachment_url( $attach_id );

    wp_send_json_success([ 'id' => $attach_id, 'url' => $src ]);
}

/**
 * Zoner AI - submenu media
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'upload.php',
        'Zoner AI generátor',
        'Zoner AI',
        'upload_files',
        'zoner-ai-generator',
        'zoner_ai_page'
    );
});

function zoner_ai_page() {
    echo '<div class="wrap" style="max-width:900px">';
    echo '<h1>Zoner AI generátor obrázků</h1>';
    echo '<button type="button" class="button button-primary zonerai-open-modal" style="margin:30px 0 0 0;">🪄 Otevřít AI generátor</button>';
    echo '</div>';
    echo '<style>.zonerai-wrap { display: none; }</style>';
}

add_action('admin_footer-media_page_zoner-ai-generator', 'zoner_ai_main');

/**
 * Zoner AI - shortcut
 */
add_action('admin_footer', function() {
    ?>
        <script>
            jQuery(function($){
                function debounce(func, wait) {
                    var timeout;
                    return function() {
                        clearTimeout(timeout);
                        timeout = setTimeout(func, wait);
                    };
                }

                function addZonerAiShortcut() {
                    $('.media-modal .attachments-browser .media-toolbar-primary.search-form').each(function(){
                        var $searchForm = $(this);
                        var $toolbar = $searchForm.closest('.media-toolbar');
                        if($toolbar.find('.zoner-ai-shortcut').length === 0) {
                            var $btn = $('<div class="media-toolbar-primary zoner-ai-shortcut-wrapper" style="margin-top: 30px;"><button type="button" class="button zoner-ai-shortcut" style="margin-right:10px;">🪄 Zoner AI</button></div>');
                            $searchForm.after($btn);
                        }
                    });
                    console.log('Zoner AI shortcut check');
                }

                addZonerAiShortcut();

                var debouncedAddShortcut = debounce(addZonerAiShortcut, 800);

                if(window.MutationObserver) {
                    var observer = new MutationObserver(function() {
                        debouncedAddShortcut();
                    });
                    observer.observe(document.body, { childList: true, subtree: true });
                } else {
                    setInterval(addZonerAiShortcut, 1200);
                }

                $(document).on('click', '.zoner-ai-shortcut', function(e){
                    e.preventDefault();
                    $('#menu-item-upload').trigger('click');
                    $('#zonerai-modal').fadeIn(120);
                });
            });
        </script>
    <?php
});
