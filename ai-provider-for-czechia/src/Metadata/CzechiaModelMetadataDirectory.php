<?php

declare(strict_types=1);

namespace Czechia\AiProvider\Metadata;

use Czechia\AiProvider\Provider\CzechiaProvider;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Class for the CZECHIA model metadata directory.
 *
 * Text models are discovered live from the OpenAI-compatible `models`
 * endpoint, the Zoner AI image model is appended as a fixed entry.
 *
 * @since 1.0.0
 *
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id: string}>
 * }
 */
class CzechiaModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * The model ID of the Zoner AI image generation model.
     *
     * @since 1.0.0
     */
    public const IMAGE_MODEL_ID = 'zoner-txt2img';

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        return new Request(
            $method,
            CzechiaProvider::url($path),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !$responseData['data']) {
            throw ResponseException::fromMissingData('CZECHIA', 'data');
        }

        // The models endpoint does not return capabilities, so they are hardcoded here.
        $textCapabilities = [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];
        $textOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain']),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];

        $models = array_values(
            array_map(
                static function (array $modelData) use ($textCapabilities, $textOptions): ModelMetadata {
                    return new ModelMetadata(
                        $modelData['id'],
                        $modelData['id'],
                        $textCapabilities,
                        $textOptions
                    );
                },
                (array) $responseData['data']
            )
        );

        usort($models, [$this, 'modelSortCallback']);

        $models[] = $this->createImageModelMetadata();

        return $models;
    }

    /**
     * Creates the metadata for the Zoner AI image generation model.
     *
     * @since 1.0.0
     *
     * @return ModelMetadata The image model metadata.
     */
    protected function createImageModelMetadata(): ModelMetadata
    {
        return new ModelMetadata(
            self::IMAGE_MODEL_ID,
            'Zoner AI (generování obrázků)',
            [CapabilityEnum::imageGeneration()],
            [
                new SupportedOption(OptionEnum::candidateCount()),
                new SupportedOption(OptionEnum::outputMimeType(), ['image/png']),
                // Only inline (base64) output is supported; the endpoint returns raw image bytes.
                new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline()]),
                new SupportedOption(OptionEnum::outputMediaOrientation()),
                new SupportedOption(OptionEnum::outputMediaAspectRatio()),
                new SupportedOption(OptionEnum::customOptions()),
                new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
                new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::image()]]),
            ]
        );
    }

    /**
     * Callback function for sorting models by ID, to be used with `usort()`.
     *
     * Prefers the Gemma model family, which is tuned for Czech content,
     * over other models. Falls back to alphabetical sorting.
     *
     * @since 1.0.0
     *
     * @param ModelMetadata $a First model.
     * @param ModelMetadata $b Second model.
     * @return int Comparison result.
     */
    protected function modelSortCallback(ModelMetadata $a, ModelMetadata $b): int
    {
        $aIsGemma = str_starts_with($a->getId(), 'gemma');
        $bIsGemma = str_starts_with($b->getId(), 'gemma');

        if ($aIsGemma && !$bIsGemma) {
            return -1;
        }
        if ($bIsGemma && !$aIsGemma) {
            return 1;
        }

        return strcmp($a->getId(), $b->getId());
    }
}
