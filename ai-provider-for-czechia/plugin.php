<?php

/**
 * Plugin Name: Zoner AI - provider
 * Description: AI konektor CZECHIA pro WordPress – generování textů a obrázků pro zákazníky CZECHIA zdarma.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.2
 * Author: ZONER a.s.
 * Text Domain: ai-provider-for-czechia
 *
 * @package Czechia\AiProvider
 */

declare(strict_types=1);

namespace Czechia\AiProvider;

use WordPress\AiClient\AiClient;
use Czechia\AiProvider\Provider\CzechiaProvider;

if (!defined('ABSPATH')) {
    return;
}

define('CZECHIA_AI_DIR', plugin_dir_path(__FILE__));
define('CZECHIA_AI_URL', plugin_dir_url(__FILE__));

/*
 * Předvolené API klíče – lze přepsat konstantou ve wp-config.php.
 */
if (!defined('CZECHIA_API_KEY')) {
    define('CZECHIA_API_KEY', 'BWRWi45UezGGZgieHGxlT7M17FlAwuio');
}
if (!defined('CZECHIA_AI_IMAGE_API_KEY')) {
    define('CZECHIA_AI_IMAGE_API_KEY', '46D3426B-4693-472A-9FBA-96BDB51F8FFD');
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Returns the API key for the CZECHIA (Zoner AI) image generation API.
 *
 * @since 1.0.0
 *
 * @return string The API key.
 */
function czechia_ai_get_image_api_key(): string
{
    /**
     * Filters the API key used for the CZECHIA (Zoner AI) image generation API.
     *
     * @since 1.0.0
     *
     * @param string $api_key The API key.
     */
    return (string) apply_filters('czechia_ai_image_api_key', CZECHIA_AI_IMAGE_API_KEY);
}

/**
 * Registers the AI Provider for CZECHIA with the AI Client.
 *
 * The bundled CZECHIA_API_KEY constant is picked up automatically by the
 * AI Client registry during registration, so the provider is configured
 * without any user input.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(CzechiaProvider::class)) {
        return;
    }

    $registry->registerProvider(CzechiaProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);
