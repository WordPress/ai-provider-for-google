<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Models;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
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
 * @since 1.2.0
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
                'has_published_posts' => [
                    'type' => 'boolean',
                    'enum' => [true],
                ],
            ],
            'required' => ['value'],
        ];
        $prepared = $this->prepareFunctionDeclarations([
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
            json_decode(json_encode($prepared, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Tests that empty object keywords and empty sub-schemas are sent as JSON objects.
     *
     * @since n.e.x.t
     */
    public function testFunctionParametersEncodeEmptyMapsAsObjects(): void
    {
        $prepared = $this->prepareFunctionDeclarations([
            new FunctionDeclaration(
                'empty_tool',
                'Accepts no parameters.',
                ['type' => 'object', 'properties' => []]
            ),
            new FunctionDeclaration(
                'nested_tool',
                'Accepts nested schemas.',
                [
                    'type' => 'object',
                    'properties' => [
                        'options' => ['type' => 'object', 'properties' => []],
                        'value' => [],
                        'list' => ['type' => 'array', 'items' => []],
                        'choice' => [
                            'anyOf' => [
                                ['type' => 'object', 'properties' => []],
                                ['type' => 'string'],
                            ],
                        ],
                    ],
                    '$defs' => [],
                ]
            ),
        ]);

        $this->assertSame(
            '[{"name":"empty_tool","description":"Accepts no parameters.",'
            . '"parametersJsonSchema":{"type":"object","properties":{}}},'
            . '{"name":"nested_tool","description":"Accepts nested schemas.",'
            . '"parametersJsonSchema":{"type":"object","properties":{'
            . '"options":{"type":"object","properties":{}},'
            . '"value":{},'
            . '"list":{"type":"array","items":{}},'
            . '"choice":{"anyOf":[{"type":"object","properties":{}},{"type":"string"}]}'
            . '},"$defs":{}}}]',
            json_encode($prepared, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Tests that numeric schema map keys remain JSON object keys while literal arrays remain lists.
     *
     * @since n.e.x.t
     */
    public function testFunctionParametersEncodeNumericMapsAsObjects(): void
    {
        $prepared = $this->prepareFunctionDeclarations([
            new FunctionDeclaration('numeric_tool', 'Accepts numeric property names.', [
                'type' => 'object',
                'properties' => [
                    '0' => [
                        'type' => 'object',
                        'properties' => ['0' => ['type' => 'string']],
                    ],
                    '1' => ['type' => 'array', 'default' => [], 'examples' => [[]]],
                ],
                '$defs' => ['0' => ['type' => 'string']],
                'required' => ['0'],
                'additionalProperties' => false,
            ]),
        ]);

        $this->assertSame(
            '{"type":"object","properties":{'
            . '"0":{"type":"object","properties":{"0":{"type":"string"}}},'
            . '"1":{"type":"array","default":[],"examples":[[]]}},'
            . '"$defs":{"0":{"type":"string"}},"required":["0"],"additionalProperties":false}',
            json_encode($prepared[0]['parametersJsonSchema'], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Tests that function declaration fields unknown to the Google AI API are not sent.
     *
     * @since n.e.x.t
     */
    public function testFunctionDeclarationOmitsAnnotations(): void
    {
        if (!method_exists(FunctionDeclaration::class, 'getAnnotations')) {
            $this->markTestSkipped('Function declaration annotations require php-ai-client 1.5.0 or later.');
        }

        $prepared = $this->prepareFunctionDeclarations([
            new FunctionDeclaration(
                'annotated_tool',
                'Has annotations.',
                ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ['readOnlyHint' => true]
            ),
        ]);

        $this->assertSame(
            [
                [
                    'name' => 'annotated_tool',
                    'description' => 'Has annotations.',
                    'parametersJsonSchema' => [
                        'type' => 'object',
                        'properties' => ['id' => ['type' => 'integer']],
                    ],
                ],
            ],
            json_decode(json_encode($prepared, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Tests that Google's total token count is preserved when supplied.
     *
     * @since 1.2.0
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
     * @since 1.2.0
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
     * @since 1.2.0
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
                new MessagePart(new FunctionCall(null, 'lookup_post', ['id' => 42]))
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
                    new FunctionResponse(null, 'lookup_post', ['title' => 'Hello world'])
                )
            )
        );
    }

    /**
     * Tests that absent and explicit null function call IDs are accepted.
     *
     * @since n.e.x.t
     */
    public function testFunctionCallWithoutId(): void
    {
        foreach ([[], ['id' => null]] as $idData) {
            $result = $this->parseResponse([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'functionCall' => array_merge(['name' => 'lookup_post', 'args' => ['id' => 42]], $idData),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
            ]);
            $call = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFunctionCall();

            $this->assertNotNull($call);
            $this->assertNull($call->getId());
            $this->assertSame('lookup_post', $call->getName());
            $this->assertSame(['id' => 42], $call->getArgs());
        }
    }

    /**
     * Tests that non-string function call IDs produce a response validation error.
     *
     * @since n.e.x.t
     * @dataProvider invalidFunctionCallIds
     *
     * @param mixed $id Invalid ID received from the API.
     */
    public function testFunctionCallRejectsInvalidId($id): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Part has an invalid functionCall shape.');

        $this->parseResponse([
            'candidates' => [[
                'content' => ['parts' => [[
                    'functionCall' => ['name' => 'lookup_post', 'id' => $id],
                ]]],
                'finishReason' => 'STOP',
            ]],
        ]);
    }

    /**
     * Provides non-string JSON values that cannot be function call IDs.
     *
     * @return array<string, array{mixed}> Invalid IDs.
     */
    public static function invalidFunctionCallIds(): array
    {
        return [
            'integer' => [42],
            'float' => [1.5],
            'boolean' => [false],
            'array' => [[]],
            'object' => [new \stdClass()],
        ];
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

    /**
     * Prepares function declarations through the model's protected method.
     *
     * @param list<FunctionDeclaration> $declarations Function declarations.
     * @return list<array<string, mixed>> Prepared declarations.
     */
    private function prepareFunctionDeclarations(array $declarations): array
    {
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

        return $model->prepareFunctionDeclarations($declarations);
    }

    /**
     * Tests that inline response data is accepted as base64 and never treated as a local file path.
     *
     * @since n.e.x.t
     */
    public function testInlineDataPartIsParsedAsBase64(): void
    {
        $result = $this->parseResponse([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => 'iVBORw0KGgo=']]],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1, 'totalTokenCount' => 2],
        ]);

        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);
        $this->assertTrue($file->isInline());
        $this->assertSame('iVBORw0KGgo=', $file->getBase64Data());
        $this->assertSame('image/png', $file->getMimeType());
    }

    /**
     * Tests that inline response data naming a local file is rejected and the file is not read.
     *
     * @since n.e.x.t
     */
    public function testInlineDataPartRejectsLocalFilePath(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('inlineData.data is not valid base64 data');

        $this->parseResponse([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['inlineData' => ['mimeType' => 'text/plain', 'data' => __FILE__]]],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);
    }

    /**
     * Tests that inline response data without a MIME type is rejected.
     *
     * @since n.e.x.t
     */
    public function testInlineDataPartRequiresMimeType(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('missing or invalid mimeType');

        $this->parseResponse([
            'candidates' => [
                [
                    'content' => ['parts' => [['inlineData' => ['data' => 'iVBORw0KGgo=']]]],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);
    }

    /**
     * Tests that file references in responses must be http(s) URLs.
     *
     * @dataProvider nonHttpFileUriProvider
     *
     * @since n.e.x.t
     *
     * @param string $fileUri The response-supplied file URI.
     */
    public function testFileDataPartRejectsNonHttpUri(string $fileUri): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('fileData.fileUri must be an http(s) URL');

        $this->parseResponse([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['fileData' => ['mimeType' => 'text/plain', 'fileUri' => $fileUri]]],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);
    }

    /**
     * Provides file URIs that must be rejected.
     *
     * @since n.e.x.t
     *
     * @return array<string, array{string}>
     */
    public function nonHttpFileUriProvider(): array
    {
        return [
            'local path' => [__FILE__],
            'file wrapper' => ['file://' . __FILE__],
            'ftp wrapper' => ['ftp://127.0.0.1:1/x'],
            'gs wrapper' => ['gs://bucket/object.txt'],
        ];
    }

    /**
     * Tests that http(s) file references are kept as remote files.
     *
     * @since n.e.x.t
     */
    public function testFileDataPartAcceptsHttpUri(): void
    {
        $result = $this->parseResponse([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['fileData' => ['mimeType' => 'image/png', 'fileUri' => 'https://example.com/a.png']],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ]);

        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);
        $this->assertTrue($file->isRemote());
        $this->assertSame('https://example.com/a.png', $file->getUrl());
    }
}
