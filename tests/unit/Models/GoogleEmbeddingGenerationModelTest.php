<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\unit\Models;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\GoogleAiProvider\Models\GoogleEmbeddingGenerationModel;

/**
 * @covers \WordPress\GoogleAiProvider\Models\GoogleEmbeddingGenerationModel
 */
class GoogleEmbeddingGenerationModelTest extends TestCase
{
    /**
     * Skips the tests unless the installed PHP AI Client supports embedding generation.
     *
     * Embedding generation support was added in PHP AI Client 1.4.0, while the plugin
     * still supports 1.3.1 as bundled with WordPress 7.0.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('Embedding generation requires PHP AI Client 1.4.0 or later.');
        }
    }

    public function testPrepareParamsBuildsBatchEmbedContentsRequest(): void
    {
        $model = $this->createExposedModel();

        $params = $model->exposePrepareGenerateEmbeddingsParams([new MessagePart('Search text')]);

        $this->assertArrayHasKey('requests', $params);
        $this->assertCount(1, $params['requests']);
        $this->assertEquals('models/gemini-embedding-2', $params['requests'][0]['model']);
        $this->assertEquals('Search text', $params['requests'][0]['content']['parts'][0]['text']);
        $this->assertArrayNotHasKey('outputDimensionality', $params['requests'][0]);
    }

    public function testPrepareParamsIncludesDimensionsWhenConfigured(): void
    {
        $model = $this->createExposedModel();
        $model->setConfig(ModelConfig::fromArray(['dimensions' => 3]));

        $params = $model->exposePrepareGenerateEmbeddingsParams([new MessagePart('Search text')]);

        $this->assertEquals(3, $params['requests'][0]['outputDimensionality']);
    }

    public function testPrepareParamsMergesCustomOptions(): void
    {
        $model = $this->createExposedModel();
        $model->setConfig(ModelConfig::fromArray([
            'customOptions' => ['taskType' => 'RETRIEVAL_QUERY'],
        ]));

        $params = $model->exposePrepareGenerateEmbeddingsParams([new MessagePart('Search text')]);

        $this->assertEquals('RETRIEVAL_QUERY', $params['requests'][0]['taskType']);
    }

    public function testPrepareParamsBuildsBatchRequestInOrder(): void
    {
        $model = $this->createExposedModel();

        $params = $model->exposePrepareGenerateEmbeddingsParams([
            new MessagePart('First'),
            new MessagePart('Second'),
        ]);

        $this->assertCount(2, $params['requests']);
        $this->assertEquals('First', $params['requests'][0]['content']['parts'][0]['text']);
        $this->assertEquals('Second', $params['requests'][1]['content']['parts'][0]['text']);
    }

    public function testGenerateEmbeddingResultParsesResponse(): void
    {
        $model = new GoogleEmbeddingGenerationModel(
            $this->createModelMetadata(),
            $this->createProviderMetadata()
        );
        $httpTransporter = $this->createMock(HttpTransporterInterface::class);
        $requestAuthentication = $this->createMock(RequestAuthenticationInterface::class);

        $requestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $httpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn(new Response(
                200,
                [],
                json_encode([
                    'embeddings' => [
                        ['values' => [0.1, 0.2, 0.3]],
                    ],
                ])
            ));

        $model->setHttpTransporter($httpTransporter);
        $model->setRequestAuthentication($requestAuthentication);

        $result = $model->generateEmbeddingResult([new MessagePart('Search text')]);

        $this->assertCount(1, $result->getEmbeddings());
        $this->assertEquals([0.1, 0.2, 0.3], $result->getEmbedding()->getValues());
        $this->assertEquals(3, $result->getDimensions());
        $this->assertEquals(0, $result->getTokenUsage()->getTotalTokens());
    }

    public function testGenerateEmbeddingResultParsesBatchResponseInOrder(): void
    {
        $model = new GoogleEmbeddingGenerationModel(
            $this->createModelMetadata(),
            $this->createProviderMetadata()
        );
        $httpTransporter = $this->createMock(HttpTransporterInterface::class);
        $requestAuthentication = $this->createMock(RequestAuthenticationInterface::class);

        $requestAuthentication->method('authenticateRequest')->willReturnArgument(0);
        $httpTransporter
            ->method('send')
            ->willReturn(new Response(
                200,
                [],
                json_encode([
                    'embeddings' => [
                        ['values' => [0.1, 0.2, 0.3]],
                        ['values' => [0.4, 0.5, 0.6]],
                    ],
                ])
            ));

        $model->setHttpTransporter($httpTransporter);
        $model->setRequestAuthentication($requestAuthentication);

        $result = $model->generateEmbeddingResult([
            new MessagePart('First'),
            new MessagePart('Second'),
        ]);

        $embeddings = $result->getEmbeddings();
        $this->assertCount(2, $embeddings);
        $this->assertEquals([0.1, 0.2, 0.3], $embeddings[0]->getValues());
        $this->assertEquals([0.4, 0.5, 0.6], $embeddings[1]->getValues());
    }

    public function testPrepareParamsBuildsInlineDataForInlineFileParts(): void
    {
        $model = $this->createExposedModel();

        $params = $model->exposePrepareGenerateEmbeddingsParams([
            new MessagePart(new File('data:image/png;base64,iVBORw0KGgo=')),
        ]);

        $part = $params['requests'][0]['content']['parts'][0];
        $this->assertArrayHasKey('inlineData', $part);
        $this->assertEquals('image/png', $part['inlineData']['mimeType']);
        $this->assertEquals('iVBORw0KGgo=', $part['inlineData']['data']);
        $this->assertArrayNotHasKey('text', $part);
    }

    public function testPrepareParamsBuildsFileDataForRemoteFileParts(): void
    {
        $model = $this->createExposedModel();

        $params = $model->exposePrepareGenerateEmbeddingsParams([
            new MessagePart(new File('https://example.com/photo.jpg', 'image/jpeg')),
        ]);

        $part = $params['requests'][0]['content']['parts'][0];
        $this->assertArrayHasKey('fileData', $part);
        $this->assertEquals('image/jpeg', $part['fileData']['mimeType']);
        $this->assertEquals('https://example.com/photo.jpg', $part['fileData']['fileUri']);
    }

    public function testPrepareParamsBuildsMixedTextAndFileBatchInOrder(): void
    {
        $model = $this->createExposedModel();

        $params = $model->exposePrepareGenerateEmbeddingsParams([
            new MessagePart('Some text'),
            new MessagePart(new File('https://example.com/clip.mp4', 'video/mp4')),
            new MessagePart('More text'),
        ]);

        $this->assertCount(3, $params['requests']);
        $this->assertEquals('Some text', $params['requests'][0]['content']['parts'][0]['text']);
        $this->assertEquals(
            'https://example.com/clip.mp4',
            $params['requests'][1]['content']['parts'][0]['fileData']['fileUri']
        );
        $this->assertEquals('More text', $params['requests'][2]['content']['parts'][0]['text']);
    }

    public function testPrepareParamsAppliesDimensionsToEveryInputInBatch(): void
    {
        $model = $this->createExposedModel();
        $model->setConfig(ModelConfig::fromArray(['dimensions' => 768]));

        $params = $model->exposePrepareGenerateEmbeddingsParams([
            new MessagePart('First'),
            new MessagePart(new File('https://example.com/photo.jpg', 'image/jpeg')),
        ]);

        $this->assertEquals(768, $params['requests'][0]['outputDimensionality']);
        $this->assertEquals(768, $params['requests'][1]['outputDimensionality']);
    }

    public function testPrepareParamsRejectsConflictingCustomOption(): void
    {
        $model = $this->createExposedModel();
        $model->setConfig(ModelConfig::fromArray([
            'customOptions' => ['content' => 'clobbered'],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflicts with an existing parameter');
        $model->exposePrepareGenerateEmbeddingsParams([new MessagePart('Search text')]);
    }

    public function testGenerateEmbeddingResultReportsPromptTokensWhenPresent(): void
    {
        $model = new GoogleEmbeddingGenerationModel(
            $this->createModelMetadata(),
            $this->createProviderMetadata()
        );
        $httpTransporter = $this->createMock(HttpTransporterInterface::class);
        $requestAuthentication = $this->createMock(RequestAuthenticationInterface::class);

        $requestAuthentication->method('authenticateRequest')->willReturnArgument(0);
        $httpTransporter
            ->method('send')
            ->willReturn(new Response(
                200,
                [],
                json_encode([
                    'embeddings' => [['values' => [0.1, 0.2]]],
                    'usageMetadata' => ['promptTokenCount' => 258],
                ])
            ));

        $model->setHttpTransporter($httpTransporter);
        $model->setRequestAuthentication($requestAuthentication);

        $result = $model->generateEmbeddingResult([new MessagePart('Search text')]);

        $this->assertEquals(258, $result->getTokenUsage()->getPromptTokens());
        $this->assertEquals(258, $result->getTokenUsage()->getTotalTokens());
    }

    public function testGenerateEmbeddingResultSendsRequestToBatchEndpoint(): void
    {
        $model = new GoogleEmbeddingGenerationModel(
            $this->createModelMetadata(),
            $this->createProviderMetadata()
        );
        $httpTransporter = $this->createMock(HttpTransporterInterface::class);
        $requestAuthentication = $this->createMock(RequestAuthenticationInterface::class);

        $requestAuthentication->method('authenticateRequest')->willReturnArgument(0);
        $httpTransporter
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($request): bool {
                $this->assertStringEndsWith(
                    'models/gemini-embedding-2:batchEmbedContents',
                    $request->getUri()
                );
                $this->assertTrue($request->getMethod()->isPost());
                return true;
            }))
            ->willReturn(new Response(
                200,
                [],
                json_encode(['embeddings' => [['values' => [0.1, 0.2]]]])
            ));

        $model->setHttpTransporter($httpTransporter);
        $model->setRequestAuthentication($requestAuthentication);

        $model->generateEmbeddingResult([new MessagePart('Search text')]);
    }

    /**
     * @dataProvider invalidInputs
     *
     * @param array<mixed> $input   The invalid embedding input.
     * @param string       $message The expected exception message.
     */
    public function testPrepareParamsRejectsInvalidInputs(array $input, string $message): void
    {
        $model = $this->createExposedModel();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $model->exposePrepareGenerateEmbeddingsParams($input);
    }

    /**
     * @return array<string, array{0: array<mixed>, 1: string}>
     */
    public function invalidInputs(): array
    {
        return [
            'empty list' => [[], 'The API requires at least one input.'],
            'non-list array' => [['first' => new MessagePart('Search text')], 'list of message parts'],
            'non-message part' => [[1], 'index 0 must be a MessagePart'],
            'function call part' => [
                [new MessagePart(new FunctionCall('call-1', 'doThing'))],
                'index 0 must be a text or file part',
            ],
            'blank text part' => [[new MessagePart('   ')], 'index 0 must contain non-empty text'],
        ];
    }

    public function testGenerateEmbeddingResultThrowsWhenResponseMissingEmbeddings(): void
    {
        $model = new GoogleEmbeddingGenerationModel(
            $this->createModelMetadata(),
            $this->createProviderMetadata()
        );
        $httpTransporter = $this->createMock(HttpTransporterInterface::class);
        $requestAuthentication = $this->createMock(RequestAuthenticationInterface::class);

        $requestAuthentication->method('authenticateRequest')->willReturnArgument(0);
        $httpTransporter
            ->method('send')
            ->willReturn(new Response(200, [], json_encode(['embeddings' => []])));

        $model->setHttpTransporter($httpTransporter);
        $model->setRequestAuthentication($requestAuthentication);

        $this->expectException(ResponseException::class);
        $model->generateEmbeddingResult([new MessagePart('Search text')]);
    }

    /**
     * Creates a model subclass exposing the protected params-preparation method.
     *
     * @return GoogleEmbeddingGenerationModel The exposed model.
     */
    private function createExposedModel(): GoogleEmbeddingGenerationModel
    {
        return new class (
            $this->createModelMetadata(),
            $this->createProviderMetadata()
        ) extends GoogleEmbeddingGenerationModel {
            /**
             * @param list<MessagePart> $input The inputs.
             * @return array<string, mixed> The prepared params.
             */
            public function exposePrepareGenerateEmbeddingsParams(array $input): array
            {
                return $this->prepareGenerateEmbeddingsParams($input);
            }
        };
    }

    private function createModelMetadata(): ModelMetadata
    {
        return new ModelMetadata(
            'gemini-embedding-2',
            'gemini-embedding-2',
            [CapabilityEnum::embeddingGeneration()],
            []
        );
    }

    private function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud());
    }
}
