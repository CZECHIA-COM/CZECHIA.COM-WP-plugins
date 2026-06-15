<?php

declare(strict_types=1);

namespace Czechia\AiProvider\Models;

use Czechia\AiProvider\Provider\CzechiaProvider;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Class for the CZECHIA (Zoner AI) image generation model.
 *
 * The Zoner AI endpoint accepts a multipart form request and returns the
 * raw image bytes. The API key is bundled with the plugin and sent as a
 * form field.
 *
 * @since 1.0.0
 */
class CzechiaImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface
{
    /**
     * The default size of generated images.
     *
     * @since 1.0.0
     */
    public const DEFAULT_SIZE = '1024x1024';

    /**
     * The maximum number of image candidates generated in one call.
     *
     * The endpoint returns a single image per request, so candidates are
     * generated with sequential requests; this caps the loop.
     *
     * @since 1.0.0
     */
    public const MAX_CANDIDATES = 4;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function generateImageResult(array $prompt): GenerativeAiResult
    {
        $httpTransporter = $this->getHttpTransporter();

        $fields = $this->prepareGenerateImageFields($prompt);

        $config = $this->getConfig();
        $candidateCount = $config->getCandidateCount();
        if ($candidateCount === null) {
            $candidateCount = 1;
        }
        $candidateCount = min(max($candidateCount, 1), self::MAX_CANDIDATES);

        $candidates = [];
        for ($i = 0; $i < $candidateCount; $i++) {
            $request = $this->createMultipartRequest($fields);
            $response = $httpTransporter->send($request);
            ResponseUtil::throwIfNotSuccessful($response);
            $candidates[] = $this->parseResponseToCandidate($response);
        }

        return new GenerativeAiResult(
            '',
            $candidates,
            new TokenUsage(0, 0, 0),
            $this->providerMetadata(),
            $this->metadata(),
            []
        );
    }

    /**
     * Prepares the given prompt and the model configuration into form fields for the API request.
     *
     * @since 1.0.0
     *
     * @param list<Message> $prompt The prompt to generate an image for. The API only supports a single user message.
     * @return array<string, string> The form fields for the API request.
     */
    protected function prepareGenerateImageFields(array $prompt): array
    {
        $config = $this->getConfig();

        if ($config->getOutputFileType() && $config->getOutputFileType()->isRemote()) {
            throw new InvalidArgumentException(
                'Unsupported output file type: Only inline is supported.'
            );
        }

        $fields = [
            'Prompt'   => $this->preparePromptParam($prompt),
            'apiKey'   => \Czechia\AiProvider\czechia_ai_get_image_api_key(),
            'Language' => 'ces_Latn',
            'RemoveBg' => 'False',
            'Size'     => $this->prepareSizeParam(
                $config->getOutputMediaOrientation(),
                $config->getOutputMediaAspectRatio()
            ),
        ];

        /*
         * Custom options allow direct control over the endpoint specific fields,
         * e.g. ['size' => '1920x1080', 'language' => 'eng_Latn', 'remove_bg' => true].
         */
        $customOptions = $config->getCustomOptions();
        if (isset($customOptions['size']) && is_string($customOptions['size'])) {
            $fields['Size'] = $customOptions['size'];
        }
        if (isset($customOptions['language']) && is_string($customOptions['language'])) {
            $fields['Language'] = $customOptions['language'];
        }
        if (isset($customOptions['remove_bg'])) {
            $fields['RemoveBg'] = $customOptions['remove_bg'] ? 'True' : 'False';
        }

        return $fields;
    }

    /**
     * Prepares the prompt parameter for the API request.
     *
     * @since 1.0.0
     *
     * @param list<Message> $messages The messages to prepare. The API only supports a single user message.
     * @return string The prepared prompt parameter.
     */
    protected function preparePromptParam(array $messages): string
    {
        if (count($messages) !== 1) {
            throw new InvalidArgumentException(
                'The API requires a single user message as prompt.'
            );
        }
        $message = $messages[0];
        if (!$message->getRole()->isUser()) {
            throw new InvalidArgumentException(
                'The API requires a user message as prompt.'
            );
        }

        $text = null;
        foreach ($message->getParts() as $part) {
            $text = $part->getText();
            if ($text !== null) {
                break;
            }
        }

        if ($text === null) {
            throw new InvalidArgumentException(
                'The API requires a single text message part as prompt.'
            );
        }

        return $text;
    }

    /**
     * Maps the configured orientation and aspect ratio to a size supported by the API.
     *
     * @since 1.0.0
     *
     * @param MediaOrientationEnum|null $orientation The desired media orientation.
     * @param string|null $aspectRatio The desired media aspect ratio.
     * @return string The size parameter, e.g. "1920x1080".
     */
    protected function prepareSizeParam(?MediaOrientationEnum $orientation, ?string $aspectRatio): string
    {
        $sizeMap = [
            '1:1'  => '1024x1024',
            '16:9' => '1920x1080',
            '4:3'  => '1152x896',
            '3:2'  => '1216x832',
            '21:9' => '1536x640',
            '2:3'  => '832x1216',
            '9:16' => '832x1216',
        ];

        if ($aspectRatio !== null && isset($sizeMap[$aspectRatio])) {
            return $sizeMap[$aspectRatio];
        }

        if ($orientation !== null) {
            if ($orientation->isLandscape()) {
                return '1920x1080';
            }
            if ($orientation->isPortrait()) {
                return '832x1216';
            }
        }

        return self::DEFAULT_SIZE;
    }

    /**
     * Creates a multipart/form-data request for the Zoner AI endpoint.
     *
     * The endpoint expects multipart form fields (matching the original
     * Zoner AI plugin), so the body is built manually.
     *
     * @since 1.0.0
     *
     * @param array<string, string> $fields The form fields.
     * @return Request The prepared request.
     */
    protected function createMultipartRequest(array $fields): Request
    {
        $boundary = '----CzechiaAiBoundary' . bin2hex(random_bytes(16));

        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        return new Request(
            HttpMethodEnum::POST(),
            CzechiaProvider::imageApiUrl(),
            ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
            $body,
            $this->getRequestOptions()
        );
    }

    /**
     * Parses the raw image response from the API endpoint into a candidate.
     *
     * @since 1.0.0
     *
     * @param Response $response The response from the API endpoint.
     * @return Candidate The parsed candidate.
     * @throws RuntimeException If the response does not contain valid image data.
     */
    protected function parseResponseToCandidate(Response $response): Candidate
    {
        $body = $response->getBody();
        if ($body === null || $body === '') {
            throw new RuntimeException(
                'The Zoner AI API returned an empty response.'
            );
        }

        $mimeType = $this->detectImageMimeType($body);
        if ($mimeType === null) {
            throw new RuntimeException(
                sprintf(
                    'The Zoner AI API did not return an image: %s',
                    substr($body, 0, 200)
                )
            );
        }

        $imageFile = new File(base64_encode($body), $mimeType);

        $message = new Message(MessageRoleEnum::model(), [new MessagePart($imageFile)]);

        return new Candidate($message, FinishReasonEnum::stop());
    }

    /**
     * Detects the MIME type of the image from its binary signature.
     *
     * @since 1.0.0
     *
     * @param string $bytes The binary data.
     * @return string|null The MIME type, or null if the data is not a known image format.
     */
    protected function detectImageMimeType(string $bytes): ?string
    {
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'image/png';
        }
        if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }
        if (strncmp($bytes, 'RIFF', 4) === 0 && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return null;
    }
}
