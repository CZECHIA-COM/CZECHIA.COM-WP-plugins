<?php

declare(strict_types=1);

namespace Czechia\AiProvider\Models;

use Czechia\AiProvider\Provider\CzechiaProvider;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Class for a CZECHIA text generation model.
 *
 * The CZECHIA LLM API is OpenAI-compatible, so the entire request and
 * response handling is inherited from the SDK base class.
 *
 * @since 1.0.0
 */
class CzechiaTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
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
            $data,
            $this->getRequestOptions()
        );
    }
}
