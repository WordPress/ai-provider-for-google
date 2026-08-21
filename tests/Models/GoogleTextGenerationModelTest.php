<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Models;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\GoogleAiProvider\Models\GoogleTextGenerationModel;

/**
 * Tests for the Google text generation model.
 *
 * @since n.e.x.t
 */
class GoogleTextGenerationModelTest extends TestCase
{
    /**
     * Tests that Google's total token count is preserved when supplied.
     *
     * @since n.e.x.t
     */
    public function testTokenUsageUsesGoogleTotalTokenCount(): void
    {
        $result = $this->parseResponse([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'The answer.']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 26,
                'candidatesTokenCount' => 11,
                'thoughtsTokenCount' => 0,
                'totalTokenCount' => 37,
            ],
        ]);

        $tokenUsage = $result->getTokenUsage();

        $this->assertSame(26, $tokenUsage->getPromptTokens());
        $this->assertSame(11, $tokenUsage->getCompletionTokens());
        $this->assertSame(37, $tokenUsage->getTotalTokens());
    }

    /**
     * Tests the backward-compatible total-token fallback for older responses.
     *
     * @since n.e.x.t
     */
    public function testTokenUsageFallsBackWhenGoogleTotalTokenCountIsMissing(): void
    {
        $result = $this->parseResponse([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'The answer.']],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 26,
                'candidatesTokenCount' => 11,
                'thoughtsTokenCount' => 3,
            ],
        ]);

        $this->assertSame(40, $result->getTokenUsage()->getTotalTokens());
    }

    /**
     * Parses a response through the model's protected response parser.
     *
     * @param array<string, mixed> $data Response data.
     * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Parsed result.
     */
    private function parseResponse(array $data): \WordPress\AiClient\Results\DTO\GenerativeAiResult
    {
        $model = new class (
            new ModelMetadata('gemini-test', 'Gemini test', [CapabilityEnum::textGeneration()], []),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextGenerationModel {
            /**
             * Parses an HTTP response.
             *
             * @param Response $response HTTP response.
             * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Parsed result.
             */
            public function parseResponse(Response $response): \WordPress\AiClient\Results\DTO\GenerativeAiResult
            {
                return $this->parseResponseToGenerativeAiResult($response);
            }
        };

        return $model->parseResponse(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
    }
}
