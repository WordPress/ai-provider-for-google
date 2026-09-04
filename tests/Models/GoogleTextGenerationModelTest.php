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
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WordPress\GoogleAiProvider\Models\GoogleTextGenerationModel;

/**
 * Tests for the Google text generation model.
 *
 * @since n.e.x.t
 */
class GoogleTextGenerationModelTest extends TestCase
{
    /**
     * Tests that function parameters are forwarded as standard JSON Schema.
     *
     * @since n.e.x.t
     */
    public function testFunctionParametersUseJsonSchemaField(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'value' => [
                    'type' => ['string', 'array'],
                    'items' => ['type' => 'integer'],
                ],
                'metadata' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'priorities' => [
                    'type' => 'array',
                    'uniqueItems' => true,
                    'items' => [
                        'type' => 'integer',
                        'enum' => [1, 2],
                    ],
                ],
            ],
            'required' => ['value'],
        ];
        $model = new class (
            new ModelMetadata('gemini-test', 'Gemini test', [CapabilityEnum::textGeneration()], []),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextGenerationModel {
            /**
             * Prepares function declarations for a Google request.
             *
             * @param list<FunctionDeclaration> $declarations Function declarations.
             * @return list<array<string, mixed>> Prepared declarations.
             */
            public function prepareFunctionDeclarations(array $declarations): array
            {
                return $this->prepareFunctionDeclarationsParam($declarations);
            }
        };

        $prepared = $model->prepareFunctionDeclarations([
            new FunctionDeclaration('complex_tool', 'Accepts a complex schema.', $schema),
            new FunctionDeclaration('parameterless_tool', 'Accepts no parameters.'),
        ]);

        $this->assertSame(
            [
                [
                    'name' => 'complex_tool',
                    'description' => 'Accepts a complex schema.',
                    'parametersJsonSchema' => $schema,
                ],
                [
                    'name' => 'parameterless_tool',
                    'description' => 'Accepts no parameters.',
                ],
            ],
            $prepared
        );
    }

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
     * Tests that Gemini function call IDs survive the response and request round trip.
     *
     * @since n.e.x.t
     */
    public function testFunctionCallIdRoundTrip(): void
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
                                'functionCall' => [
                                    'name' => 'lookup_post',
                                    'args' => ['id' => 42],
                                    'id' => 'call_123',
                                ],
                                'thoughtSignature' => 'sig-456',
                            ],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ], JSON_THROW_ON_ERROR)));

        $part = $result->getCandidates()[0]->getMessage()->getParts()[0];
        $functionCall = $part->getFunctionCall();

        $this->assertNotNull($functionCall);
        $this->assertSame('call_123', $functionCall->getId());
        $this->assertSame(
            [
                'functionCall' => [
                    'name' => 'lookup_post',
                    'id' => 'call_123',
                    'args' => ['id' => 42],
                ],
                'thoughtSignature' => 'sig-456',
            ],
            $model->preparePart($part)
        );
        $this->assertSame(
            [
                'functionResponse' => [
                    'name' => 'lookup_post',
                    'response' => [
                        'name' => 'lookup_post',
                        'content' => ['title' => 'Hello world'],
                    ],
                    'id' => 'call_123',
                ],
            ],
            $model->preparePart(
                new MessagePart(
                    new FunctionResponse('call_123', 'lookup_post', ['title' => 'Hello world'])
                )
            )
        );
        $this->assertSame(
            [
                'functionCall' => [
                    'name' => 'lookup_post',
                    'args' => ['id' => 42],
                ],
            ],
            $model->preparePart(
                new MessagePart(new FunctionCall('', 'lookup_post', ['id' => 42]))
            )
        );
        $this->assertSame(
            [
                'functionResponse' => [
                    'name' => 'lookup_post',
                    'response' => [
                        'name' => 'lookup_post',
                        'content' => ['title' => 'Hello world'],
                    ],
                ],
            ],
            $model->preparePart(
                new MessagePart(
                    new FunctionResponse('', 'lookup_post', ['title' => 'Hello world'])
                )
            )
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
