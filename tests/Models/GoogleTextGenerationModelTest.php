<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Models;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
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
                'thoughtsTokenCount' => 3,
                'totalTokenCount' => 41,
            ],
        ]);

        $tokenUsage = $result->getTokenUsage();

        $this->assertSame(26, $tokenUsage->getPromptTokens());
        $this->assertSame(14, $tokenUsage->getCompletionTokens());
        $this->assertSame(41, $tokenUsage->getTotalTokens());
        $this->assertSame(3, $tokenUsage->getThoughtTokens());
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

        $tokenUsage = $result->getTokenUsage();

        $this->assertSame(14, $tokenUsage->getCompletionTokens());
        $this->assertSame(40, $tokenUsage->getTotalTokens());
        $this->assertSame(3, $tokenUsage->getThoughtTokens());
    }

    /**
     * Tests that thought signatures on text parts survive a response and request round trip.
     *
     * @since n.e.x.t
     */
    public function testTextPartThoughtSignatureRoundTrip(): void
    {
        $model = new class (
            new ModelMetadata('gemini-test', 'Gemini test', [CapabilityEnum::textGeneration()], []),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextGenerationModel {
            /**
             * Parses an HTTP response.
             *
             * @param Response $response HTTP response.
             * @return GenerativeAiResult Parsed result.
             */
            public function parseResponse(Response $response): GenerativeAiResult
            {
                return $this->parseResponseToGenerativeAiResult($response);
            }

            /**
             * Prepares a message part for a Google request.
             *
             * @param MessagePart $part Message part.
             * @return array<string, mixed> Prepared part data.
             */
            public function preparePart(MessagePart $part): array
            {
                return $this->getMessagePartData($part) ?? [];
            }
        };
        $result = $model->parseResponse(new Response(200, [], json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => 'The answer.',
                                'thoughtSignature' => 'sig-123',
                            ],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ], JSON_THROW_ON_ERROR)));

        $part = $result->getCandidates()[0]->getMessage()->getParts()[0];

        $this->assertSame('sig-123', $part->getThoughtSignature());
        $this->assertSame(
            [
                'text' => 'The answer.',
                'thoughtSignature' => 'sig-123',
            ],
            $model->preparePart($part)
        );
    }

    /**
     * Parses a response through the model's protected response parser.
     *
     * @param array<string, mixed> $data Response data.
     * @return GenerativeAiResult Parsed result.
     */
    private function parseResponse(array $data): GenerativeAiResult
    {
        $model = new class (
            new ModelMetadata('gemini-test', 'Gemini test', [CapabilityEnum::textGeneration()], []),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextGenerationModel {
            /**
             * Parses an HTTP response.
             *
             * @param Response $response HTTP response.
             * @return GenerativeAiResult Parsed result.
             */
            public function parseResponse(Response $response): GenerativeAiResult
            {
                return $this->parseResponseToGenerativeAiResult($response);
            }
        };

        return $model->parseResponse(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
    }
}
