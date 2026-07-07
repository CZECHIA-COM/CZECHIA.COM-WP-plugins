<?php
/*
Plugin Name: CZECHIA
Description: Nejrychlejší hosting pro WordPress v Česku.
Version: 1.1
Author: ZONER a.s.
*/

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'index.php') {
        wp_enqueue_style('czechia-dashboard-css', false);
        add_action('admin_head', function() {
            ?>
            <style>
                .czechia-dashboard {
                    background: #0171E3;
                    color: #fff;
                    max-width: calc( 100% - 20px);
                    padding: 48px 0 28px 0;
					margin-top: 40px;
					position: relative;
                }
				.czechia-dashboard__logo {
					max-width: 220px;
				}
				.czechia-dashboard__h1 {
					color: white;
					line-height: 60px;
					font-size: 45px;
				}
				.czechia-dashboard__text {
					color: white;
					font-size: 20px;
					margin-bottom: 40px;
				}
                .czechia-dashboard__inner {
                    margin: 0 auto;
                    padding: 0 20px;
                }
                .czechia-dashboard__intro {
                    max-width: 50%;
					padding-left: 35px;
					position: relative;
                }
				.czechia-dashboard__img {
					position: absolute;
					right: 0;
					bottom: 0;
					max-width: 580px;
				}
				@media ( max-width: 1400px ) {
					.czechia-dashboard__img {
						max-width: 400px;
					}
				}
				@media ( max-width: 1125px ) {
					.czechia-dashboard__img {
						display: none;
					}

					.czechia-dashboard__intro {
						max-width: 100%;
					}
				}
                .czechia-dashboard__buttons a {
                    display: inline-block;
                    padding: 20px 45px;
                    border-radius: 8px;
                    font-weight: bold;
                    text-decoration: none;
                    margin-right: 14px;
					font-size: 18px;
                    margin-bottom: 10px;
                    transition: all .2s;
                }
                .czechia-dashboard__buttons .czechia-btn-main {
                    background: #ffb600;
                    color: white;
                    border: none;
                }
                .czechia-dashboard__buttons .czechia-btn-alt {
                    background: none;
                    border: 1px solid #fff;
                    color: #fff;
                }

                .czechia-dashboard-status {
                    background: #fff;
                    padding: 40px 10px 50px 10px;
                    max-width: calc( 100% - 20px );
                    box-sizing: border-box;
                }
                .czechia-dashboard-status__title {
                    text-align: center;
                    color: #12375c;
                    font-size: 32px;
                    font-weight: bold;
                    margin-bottom: 38px;
                    letter-spacing: 0.02em;
                }
                .czechia-dashboard-status__grid {
                    display: flex;
                    gap: 32px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .czechia-dashboard-status__item {
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 2px 8px rgba(30,60,90,0.07);
                    padding: 32px 24px 24px 24px;
                    min-width: 220px;
                    max-width: 290px;
                    text-align: center;
                    flex: 1 1 260px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    transition: box-shadow .2s;
                }
                .czechia-dashboard-status__item:hover {
                    box-shadow: 0 6px 20px rgba(30,60,90,0.16);
                }
                .czechia-dashboard-status__icon {
                    margin-bottom: 18px;
                }
                .czechia-dashboard-status__icon--ok svg {
                    display: block;
                }
                .czechia-dashboard-status__icon--fail svg {
                    display: block;
                }
                .czechia-dashboard-status__name {
                    font-size: 18px;
                    margin-bottom: 12px;
                    color: #111;
                }
                .czechia-dashboard-status__links {
                    font-size: 15px;
                }
                .czechia-dashboard-status__links a {
                    color: #0872dd;
                    text-decoration: underline;
                    margin: 0 6px;
                    font-weight: 500;
                    transition: color .18s;
                }
                .czechia-dashboard-status__links a:hover {
                    color: #12375c;
                }

                @media (max-width: 850px) {
                    .czechia-dashboard-status__grid {
                        flex-direction: column;
                        gap: 22px;
                        align-items: center;
                    }
                    .czechia-dashboard-status__item {
                        width: 80%;
                        min-width: 0;
                        max-width: 400px;
                    }

                    .czechia-dashboard-status__item {
                        flex: 1 1 150px;
                    }
                }


                .czechia-dashboard-help {
                    max-width: calc( 100% - 20px );
                    background: #1878e8;
                    padding: 32px 0 30px 0;
                    color: #fff;
                }
                .czechia-dashboard-help__inner {
                    max-width: 1100px;
                    margin: 0 auto;
                }
                .czechia-dashboard-help__title {
                    font-size: 25px;
                    line-height: 30px;
                    color: white;
                    padding: 0 20px;
                    font-weight: bold;
                    margin: 0 0 50px 0;
                    text-align: center;
                    letter-spacing: 0.01em;
                }
                .czechia-dashboard-help__tips {
                    display: flex;
                    justify-content: center;
                    gap: 20%;
                    font-size: 18px;
                    font-weight: bold;
                }
                .czechia-dashboard-help__tips a {
                    color: #fff;
                    text-decoration: none;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    transition: color 0.2s;
                }
                .czechia-dashboard-help__tips a:hover {
                    color: #ffb600;
                }
                @media (max-width: 850px) {
                    .czechia-dashboard-help__tips {
                        flex-direction: column;
                        gap: 16px;
                        font-size: 15px;
                        align-items: center;
                    }
                    .czechia-dashboard-help__title {
                        font-size: 22px;
                    }
                }

                .czechia-container {
                    max-width: 1080px;
                    margin: 0 auto;
                }

                .czechia-dashboard-articles {
                    box-sizing: border-box;
                    max-width: calc( 100% - 20px );
                    background-color: white;
                    padding: 50px 10px 30px 10px;
                }
                .czechia-dashboard-articles__title {
                    font-size: 22px;
                    font-weight: bold;
                    margin: 0 0 35px 0;
                    color: #232c41;
                }
                .czechia-dashboard-articles__grid {
                    display: flex;
                    gap: 26px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .czechia-dashboard-articles__item {
                    background: #fff;
                    border-radius: 16px;
                    box-shadow: 0 2px 15px rgba(30,60,90,0.12);
                    overflow: hidden;
                    max-width: 33%;
                    flex: 1 1 30%;
                    min-width: 220px;
                    display: flex;
                    flex-direction: column;
                    text-decoration: none;
                }
                .czechia-dashboard-articles__item:hover .czechia-dashboard-articles__item-title {
                    text-decoration: underline;
                }
                .czechia-dashboard-articles__item img {
                    width: 100%;
                    aspect-ratio: 3/2;
                    object-fit: cover;
                    border-radius: 16px 16px 0 0;
                }
                .czechia-dashboard-articles__item-content {
                    padding: 22px 18px 18px 18px;
                }
                .czechia-dashboard-articles__item-title {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 8px;
                    color: #232c41;
                }
                .czechia-dashboard-articles__item-text {
                    font-size: 15px;
                    color: #474747;
                }
                @media (max-width: 850px) {
                    .czechia-dashboard-articles__grid {
                        flex-direction: column;
                        gap: 18px;
                        align-items: center;
                    }
                }

                .czechia-dashboard-plugins {
                    box-sizing: border-box;
                    background-color: white;
                    max-width: calc( 100% - 20px );
                    padding: 50px 10px 50px 10px;
                }
                .czechia-dashboard-plugins__title {
                    font-size: 22px;
                    font-weight: bold;
                    margin: 0 0 30px 0;
                    color: #232c41;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .czechia-dashboard-plugins__grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    grid-auto-flow: dense;
                    gap: 24px;
                }
                .czechia-dashboard-plugins__item {
                    border-radius: 16px;
                    min-height: 270px;
                    box-shadow: 0 2px 8px rgba(30,60,90,0.09);
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    position: relative;
                    text-decoration: none;
                }
                .czechia-dashboard-plugins__item--bg {
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                    position: relative;
                }
                .czechia-dashboard-plugins__overlay {
                    position: absolute;
                    inset: 0;
                    z-index: 1;
                    background: linear-gradient(180deg, rgba(0, 113, 227, 0) 0%, rgba(0, 113, 227, 1) 100%);
                    opacity: 0.9;
                    pointer-events: none;
                }
                .czechia-dashboard-plugins__item-content {
                    position: relative;
                    z-index: 2;
                    padding: 36px 24px 24px 24px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                    min-height: 110px;
                    justify-content: flex-end;
                    height: 100%;
                }
                .czechia-dashboard-plugins__item-label {
                    font-size: 13px;
                    color: #fff;
                    font-weight: 500;
                    opacity: 0.88;
                    letter-spacing: 1px;
                    margin-bottom: 6px;
                    text-shadow: 0 2px 10px rgba(0,0,0,0.10);
                }
                .czechia-dashboard-plugins__item-title {
                    font-size: 20px;
                    font-weight: bold;
                    margin-bottom: 7px;
                    color: #fff;
                    text-shadow: 0 2px 12px rgba(40,60,120,0.11);
                }
                .czechia-dashboard-plugins__item:not(.czechia-dashboard-plugins__item--coming):hover .czechia-dashboard-plugins__item-title {
                    text-decoration: underline;
                }
                .czechia-dashboard-plugins__item-text {
                    font-size: 13px;
                    color: #fff;
                    font-weight: 400;
                    line-height: 1.35;
                    text-shadow: 0 1px 9px rgba(40,40,60,0.11);
                    opacity: 0.99;
                }

                .czechia-dashboard-plugins__item--coming {
                    background: linear-gradient(180deg, rgba(0, 113, 227, 0) 0%, rgba(0, 113, 227, 1) 100%);
                    min-height: 100%;
                    grid-row: span 2;
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    justify-content: space-between;
                    padding: 0;
                    overflow: hidden;
                }

                .czechia-dashboard-plugins__coming-top {
                    display: flex;
                    align-items: flex-end;
                    justify-content: center;
                    min-height: 120px;
                    padding-top: 36px;
                    padding-bottom: 0;
                }

                .czechia-dashboard-plugins__item-title--soon {
                    font-size: 2.25rem;
                    color: #46547d;
                    font-weight: 700;
                    margin-bottom: 0;
                    text-align: center;
                    width: 100%;
                }

                .czechia-dashboard-plugins__coming-bottom {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-end;
                    padding-bottom: 32px;
                    padding-top: 0;
                    width: 90%;
                    margin: 0 auto;
                    text-align: center;
                    gap: 6px;
                }

                @media (max-width: 850px) {
                    .czechia-dashboard-plugins__item--coming {
                        grid-row: auto;
                        min-height: 210px;
                    }
                    .czechia-dashboard-plugins__coming-top {
                        min-height: 70px;
                        padding-top: 20px;
                    }
                    .czechia-dashboard-plugins__item-title--soon {
                        font-size: 1.4rem;
                    }

                    .czechia-dashboard-plugins__grid {
                        grid-template-columns: 1fr;
                    }
                    .czechia-dashboard-plugins__item,
                    .czechia-dashboard-plugins__item--bg {
                        min-height: 210px;
                        grid-row: auto;
                    }
                    .czechia-dashboard-plugins__item-title--soon {
                        font-size: 25px;
                    }
                }

                @media (max-width: 800px) {
                    .czechia-dashboard, .czechia-dashboard-status, .czechia-dashboard-help, .czechia-dashboard-articles, .czechia-dashboard-plugins {
                        max-width: 100%;
                    }

                    .czechia-dashboard__h1 {
                        font-size: 35px;
                        line-height: 45px;
                    }

                    .czechia-dashboard-articles__item {
                        max-width: 100%;
                    }
                }
            </style>
            <?php
        });
    }
});

function czechia_get_wp_update_count() {
    $core_updates   = get_site_transient('update_core');
    $plugin_updates = get_site_transient('update_plugins');
    $theme_updates  = get_site_transient('update_themes');

    $count = 0;

    if (!empty($core_updates->updates)) {
        foreach ($core_updates->updates as $update) {
            if ($update->response == 'upgrade') {
                $count++;
            }
        }
    }

    if (!empty($plugin_updates->response)) {
        $count += count($plugin_updates->response);
    }

    if (!empty($theme_updates->response)) {
        $count += count($theme_updates->response);
    }

    return $count;
}

add_action('admin_notices', function() {
    $screen = get_current_screen();
	$path = plugin_dir_url( __FILE__ );

    // is https enabled?
    $https = false;
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $https = true;
    }

    // updates count
    $updatesCount = czechia_get_wp_update_count();

    if ($screen && $screen->id === 'dashboard') {
        ?>

        <div class="czechia-dashboard">
            <div class="czechia-dashboard__inner">
                <div class="czechia-dashboard__intro">
					<div>
						<img src="<?php echo $path; ?>static/logo.svg" alt="Czechia Logo" class="czechia-dashboard__logo">
					</div>
                    <h1 class="czechia-dashboard__h1">Vítejte ve WordPressu<br>na <strong>CZECHIA.COM</strong></h1>

                    <p class="czechia-dashboard__text">👋 Jsme rádi, že jste zvolili právě nás – nejlepší WP hosting v Česku.</p>

                    <div class="czechia-dashboard__buttons">
                        <a href="#czechia-status" class="czechia-btn-main">Stav webu</a>
                        <a href="#czechia-help" class="czechia-btn-alt">Nápověda</a>
                    </div>
                </div>
            </div>

			<img src="<?php echo $path; ?>static/welcome.png" class="czechia-dashboard__img" alt="Czechia">
        </div>

        <div class="czechia-dashboard-status" id="czechia-status">
            <h2 class="czechia-dashboard-status__title">Stav webu</h2>
            <div class="czechia-dashboard-status__grid">
                <div class="czechia-dashboard-status__item">
                    <div class="czechia-dashboard-status__icon czechia-dashboard-status__icon--<?php if ( $https ) { echo 'ok'; } else { echo 'fail'; } ?>">
                        <?php if ( $https ) : ?>
                            <svg width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="none" stroke="#0872dd" stroke-width="3"/><polyline points="20 34 30 44 46 24" fill="none" stroke="#0872dd" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php else: ?>
                            <svg width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="none" stroke="#d6d6d6" stroke-width="3"/><line x1="20" y1="20" x2="44" y2="44" stroke="#df1919" stroke-width="3" stroke-linecap="round"/><line x1="44" y1="20" x2="20" y2="44" stroke="#df1919" stroke-width="3" stroke-linecap="round"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="czechia-dashboard-status__name"><b>SSL certifikát</b></div>
                    <div class="czechia-dashboard-status__links">
                        <a href="https://napoveda.czechia.com/rubrika/zabezpeceni-ssl/" target="_blank">Více o certifikátech</a>
                        <a href="https://sslmarket.cz/?utm_source=czechia_plugin" target="_blank">SSL Market</a>
                    </div>
                </div>

                <div class="czechia-dashboard-status__item">
                    <div class="czechia-dashboard-status__icon czechia-dashboard-status__icon--<?php if ( $updatesCount === 0 ) { echo 'ok'; } else { echo 'fail'; } ?>">
                        <?php if ( $updatesCount == 0 ) : ?>
                            <svg width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="none" stroke="#0872dd" stroke-width="3"/><polyline points="20 34 30 44 46 24" fill="none" stroke="#0872dd" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php else: ?>
                            <svg width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="none" stroke="#d6d6d6" stroke-width="3"/><line x1="20" y1="20" x2="44" y2="44" stroke="#df1919" stroke-width="3" stroke-linecap="round"/><line x1="44" y1="20" x2="20" y2="44" stroke="#df1919" stroke-width="3" stroke-linecap="round"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="czechia-dashboard-status__name"><b>Aktuální WP a pluginy</b></div>
                    <div class="czechia-dashboard-status__links">
                        <a href="<?php echo admin_url( 'update-core.php' ); ?>">Aktualizace (<?php echo $updatesCount; ?>)</a>
                        <a href="https://napoveda.czechia.com/rubrika/wordpress/" target="_blank">Doporučení</a>
                    </div>
                </div>

                <div class="czechia-dashboard-status__item">
                    <div class="czechia-dashboard-status__icon czechia-dashboard-status__icon--fail">
                        <svg width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="28" fill="none" stroke="#0872dd" stroke-width="3"/><polyline points="20 34 30 44 46 24" fill="none" stroke="#0872dd" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="czechia-dashboard-status__name"><b>Zálohování od CZECHIA</b></div>
                    <div class="czechia-dashboard-status__links">
                        <a href="https://napoveda.czechia.com/clanek/linux/" target="_blank">Informace o zálohování</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Potřebujete pomoc -->
        <div class="czechia-dashboard-help" id="czechia-help">
            <div class="czechia-dashboard-help__inner">
                <h2 class="czechia-dashboard-help__title">Potřebujete pomoc? Zde je pár tipů:</h2>
                <div class="czechia-dashboard-help__tips">
                    <a href="https://napoveda.czechia.com/rubrika/wordpress/" target="_blank"><span>📑</span> Návody</a>
                    <a href="#"><span>🛠️</span> Úprava vzhledu</a>
                    <a href="https://www.czechia.com/kontakty" target="_blank"><span>🤝</span> Podpora</a>
                </div>
            </div>
        </div>

        <!-- Doporučujeme přečíst -->
        <div class="czechia-dashboard-articles">
            <div class="czechia-container">
                <h2 class="czechia-dashboard-articles__title">Doporučujeme přečíst</h2>

                <div class="czechia-dashboard-articles__grid">
                    <a href="https://napoveda.czechia.com/clanek/prvni-kroky-po-instalaci/" target="_blank" class="czechia-dashboard-articles__item">
                        <img src="<?php echo $path; ?>static/article-1.png" alt="První kroky">
                        <div class="czechia-dashboard-articles__item-content">
                            <div class="czechia-dashboard-articles__item-title">První kroky po instalaci</div>
                            <div class="czechia-dashboard-articles__item-text">Začněte správnou nohou. V tomto článku vám poradíme, jak začít s WordPressem.</div>
                        </div>
                    </a>

                    <a href="https://napoveda.czechia.com/clanek/jak-vytvaret-obsah-a-upravit-design/" target="_blank" class="czechia-dashboard-articles__item">
                        <img src="<?php echo $path; ?>static/article-2.png" alt="Obsah a design">
                        <div class="czechia-dashboard-articles__item-content">
                            <div class="czechia-dashboard-articles__item-title">Jak vytvářet obsah a upravit design</div>
                            <div class="czechia-dashboard-articles__item-text">Publikujte příspěvky, vytvářejte stránky, upravujte příspěvky – jednoduše.</div>
                        </div>
                    </a>

                    <a href="https://napoveda.czechia.com/clanek/wordpress-a-seo/" target="_blank" class="czechia-dashboard-articles__item">
                        <img src="<?php echo $path; ?>static/article-3.png" alt="SEO">
                        <div class="czechia-dashboard-articles__item-content">
                            <div class="czechia-dashboard-articles__item-title">WordPress a SEO</div>
                            <div class="czechia-dashboard-articles__item-text">Jak optimalizovat váš WordPress, aby byl vidět ve vyhledávačích? Připravili jsme několik tipů.</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="czechia-dashboard-plugins">
            <div class="czechia-container">
                <h2 class="czechia-dashboard-plugins__title"><span>💡</span> Doporučené pluginy</h2>

                <div class="czechia-dashboard-plugins__grid">
                    <!-- Elementor -->
                    <a href="https://wordpress.org/plugins/elementor/" target="_blank" class="czechia-dashboard-plugins__item czechia-dashboard-plugins__item--bg"
                        style="background-image: url('<?php echo $path; ?>static/plugin-elementor.png');">
                        <div class="czechia-dashboard-plugins__overlay"></div>
                        <div class="czechia-dashboard-plugins__item-content">
                            <div class="czechia-dashboard-plugins__item-label">PLUGIN</div>
                            <div class="czechia-dashboard-plugins__item-title czechia-dashboard-plugins__item-title--blue">Elementor</div>
                            <div class="czechia-dashboard-plugins__item-text">Výkonný blokový editor pro pokročilé vizuální úpravy stránek.</div>
                        </div>
                    </a>

                    <!-- Yoast SEO -->
                    <a href="https://wordpress.org/plugins/wordpress-seo/" target="_blank" class="czechia-dashboard-plugins__item czechia-dashboard-plugins__item--bg"
                        style="background-image: url('<?php echo $path; ?>static/plugin-yoast.png');">
                        <div class="czechia-dashboard-plugins__overlay"></div>
                        <div class="czechia-dashboard-plugins__item-content">
                            <div class="czechia-dashboard-plugins__item-label">PLUGIN</div>
                            <div class="czechia-dashboard-plugins__item-title czechia-dashboard-plugins__item-title--blue">Yoast SEO</div>
                            <div class="czechia-dashboard-plugins__item-text">Nástroj, který vám pomůže s optimalizací webu pro vyhledávače.</div>
                        </div>
                    </a>

                    <!-- Coming soon přes dva řádky -->
                    <div class="czechia-dashboard-plugins__item czechia-dashboard-plugins__item--coming">
                        <div class="czechia-dashboard-plugins__coming-top">
                            <span class="czechia-dashboard-plugins__item-title czechia-dashboard-plugins__item-title--soon">Coming soon…</span>
                        </div>
                        <div class="czechia-dashboard-plugins__coming-bottom">
                            <span class="czechia-dashboard-plugins__item-label">PŘIPRAVUJEME</span>
                            <span class="czechia-dashboard-plugins__item-title czechia-dashboard-plugins__item-title--blue">ZONER AI plugin</span>
                            <span class="czechia-dashboard-plugins__item-text">Kombinace WordPressu a umělé inteligence od ZONER AI.</span>
                        </div>
                    </div>

                    <!-- Contact Form 7 -->
                    <a href="https://wordpress.org/plugins/contact-form-7/" target="_blank" class="czechia-dashboard-plugins__item czechia-dashboard-plugins__item--bg"
                        style="background-image: url('<?php echo $path; ?>static/plugin-cf7.png');">
                        <div class="czechia-dashboard-plugins__overlay"></div>
                        <div class="czechia-dashboard-plugins__item-content">
                            <div class="czechia-dashboard-plugins__item-label">PLUGIN</div>
                            <div class="czechia-dashboard-plugins__item-title czechia-dashboard-plugins__item-title--blue">Contact Form 7</div>
                            <div class="czechia-dashboard-plugins__item-text">Oblíbený plugin pro vytváření kontaktních formulářů.</div>
                        </div>
                    </a>

                    <!-- WooCommerce -->
                    <a href="https://wordpress.org/plugins/woocommerce/" target="_blank" class="czechia-dashboard-plugins__item czechia-dashboard-plugins__item--bg"
                        style="background-image: url('<?php echo $path; ?>static/plugin-woo.png');">
                        <div class="czechia-dashboard-plugins__overlay"></div>
                        <div class="czechia-dashboard-plugins__item-content">
                            <div class="czechia-dashboard-plugins__item-label">PLUGIN</div>
                            <div class="czechia-dashboard-plugins__item-title czechia-dashboard-plugins__item-title--blue">WooCommerce</div>
                            <div class="czechia-dashboard-plugins__item-text">WooCommerce vám umožní snadno vytvořit a spravovat plně funkční e-shop.</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
});
