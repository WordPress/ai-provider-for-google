<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\unit\Metadata;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\GoogleAiProvider\Metadata\GoogleModelMetadataDirectory;

/**
 * @covers \WordPress\GoogleAiProvider\Metadata\GoogleModelMetadataDirectory
 */
class GoogleModelMetadataDirectoryTest extends TestCase
{
    public function testEmbeddingModelsAdvertiseEmbeddingCapability(): void
    {
        $models = $this->parseModels([
            'models' => [
                [
                    'name' => 'models/text-embedding-004',
                    'displayName' => 'Text Embedding 004',
                    'supportedGenerationMethods' => ['embedContent', 'countTextTokens'],
                ],
                [
                    'name' => 'models/gemini-2.5-flash',
                    'displayName' => 'Gemini 2.5 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]);

        $embeddingModel = $this->findModel($models, 'text-embedding-004');
        $this->assertInstanceOf(ModelMetadata::class, $embeddingModel);

        $capabilities = $embeddingModel->getSupportedCapabilities();
        $this->assertCount(1, $capabilities);
        $this->assertTrue($capabilities[0]->isEmbeddingGeneration());

        $options = $embeddingModel->getSupportedOptions();
        $this->assertTrue($options[0]->getName()->isInputModalities());
        $this->assertTrue($options[1]->getName()->isDimensions());
        $this->assertTrue($options[2]->getName()->isCustomOptions());
    }

    public function testTextModelsAreUnaffected(): void
    {
        $models = $this->parseModels([
            'models' => [
                [
                    'name' => 'models/text-embedding-004',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
                [
                    'name' => 'models/gemini-2.5-flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]);

        $textModel = $this->findModel($models, 'gemini-2.5-flash');
        $this->assertInstanceOf(ModelMetadata::class, $textModel);

        $isTextGeneration = false;
        $isEmbeddingGeneration = false;
        foreach ($textModel->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                $isTextGeneration = true;
            }
            if ($capability->isEmbeddingGeneration()) {
                $isEmbeddingGeneration = true;
            }
        }

        $this->assertTrue($isTextGeneration);
        $this->assertFalse($isEmbeddingGeneration);
    }

    /**
     * Parses a models response payload into a list of model metadata.
     *
     * @param array<string, mixed> $payload The response payload.
     * @return list<ModelMetadata> The parsed model metadata.
     */
    private function parseModels(array $payload): array
    {
        $directory = new class extends GoogleModelMetadataDirectory {
            /**
             * @return list<ModelMetadata> The parsed model metadata.
             */
            public function exposeParseResponseToModelMetadataList(Response $response): array
            {
                return $this->parseResponseToModelMetadataList($response);
            }
        };

        return $directory->exposeParseResponseToModelMetadataList(
            new Response(200, [], json_encode($payload))
        );
    }

    /**
     * Finds a model by ID within a list of model metadata.
     *
     * @param list<ModelMetadata> $models The model metadata list.
     * @param string $id The model ID to find.
     * @return ModelMetadata|null The matching model metadata, or null if not found.
     */
    private function findModel(array $models, string $id): ?ModelMetadata
    {
        foreach ($models as $model) {
            if ($model->getId() === $id) {
                return $model;
            }
        }

        return null;
    }
}
