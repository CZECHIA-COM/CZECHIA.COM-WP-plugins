<?php

declare(strict_types=1);

namespace Czechia\AiProvider\Provider;

use Czechia\AiProvider\Metadata\CzechiaModelMetadataDirectory;
use Czechia\AiProvider\Models\CzechiaImageGenerationModel;
use Czechia\AiProvider\Models\CzechiaTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the CZECHIA provider.
 *
 * Text generation is served by the CZECHIA LLM API (OpenAI-compatible),
 * image generation by the Zoner AI image API. API keys are bundled with
 * the plugin, so no authentication setup is required from users.
 *
 * @since 1.0.0
 */
class CzechiaProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        return 'https://llm.airgpt.cz/v1';
    }

    /**
     * Returns the URL of the Zoner AI image generation endpoint.
     *
     * @since 1.0.0
     *
     * @return string The endpoint URL.
     */
    public static function imageApiUrl(): string
    {
        return 'https://ai.zoner.com/console/api/wordpress/Txt2Img';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isImageGeneration()) {
                return new CzechiaImageGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isTextGeneration()) {
                return new CzechiaTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities: ' . implode(', ', $capabilities)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        /*
         * The API key ships with the plugin as the CZECHIA_API_KEY constant.
         * Both the AI Client registry and the WordPress connectors UI resolve
         * the key from that constant automatically, so users never enter it.
         */
        $providerMetadataArgs = [
            'czechia',
            'CZECHIA',
            ProviderTypeEnum::cloud(),
            null,
            RequestAuthenticationMethod::apiKey(),
        ];
        // Provider description support was added in 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            if (function_exists('__')) {
                $providerMetadataArgs[] = __(
                    'Generování textů a obrázků s CZECHIA AI.',
                    'ai-provider-for-czechia'
                );
            } else {
                $providerMetadataArgs[] = 'Generování textů a obrázků s CZECHIA AI.';
            }
        }
        // Provider logoPath support was added in 1.3.0.
        if (version_compare(AiClient::VERSION, '1.3.0', '>=')) {
            $providerMetadataArgs[] = dirname(__DIR__, 2) . '/assets/images/czechia.svg';
        }
        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Check valid API access by attempting to list models.
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new CzechiaModelMetadataDirectory();
    }
}
