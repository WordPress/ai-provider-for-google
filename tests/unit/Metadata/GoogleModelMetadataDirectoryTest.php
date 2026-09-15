<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\unit\Metadata;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\GoogleAiProvider\Metadata\GoogleModelMetadataDirectory;

/**
 * @covers \WordPress\GoogleAiProvider\Metadata\GoogleModelMetadataDirectory
 */
class GoogleModelMetadataDirectoryTest extends TestCase
{
    public function testEmbeddingModelsAdvertiseEmbeddingCapability(): void
    {
        if (!interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('Embedding generation requires PHP AI Client 1.4.0 or later.');
        }

        $embeddingModel = $this->parseEmbeddingModel();

        $capabilities = $embeddingModel->getSupportedCapabilities();
        $this->assertCount(1, $capabilities);
        $this->assertTrue($capabilities[0]->isEmbeddingGeneration());

        $options = $embeddingModel->getSupportedOptions();
        $this->assertTrue($options[0]->getName()->isInputModalities());
        $this->assertTrue($options[1]->getName()->isDimensions());
        $this->assertTrue($options[2]->getName()->isCustomOptions());

        // Text-only models must advertise exactly one combination, otherwise the model resolver
        // would route image or video inputs to a model that cannot accept them.
        $this->assertSame([['text']], $this->inputModalityCombinations($embeddingModel));
    }

    public function testMultimodalEmbeddingModelsAdvertiseEveryModalityCombination(): void
    {
        if (!interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('Embedding generation requires PHP AI Client 1.4.0 or later.');
        }

        $models = $this->parseModels([
            'models' => [
                [
                    'name' => 'models/gemini-embedding-2',
                    'displayName' => 'Gemini Embedding 2',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ]);

        $model = $this->findModel($models, 'gemini-embedding-2');
        $this->assertInstanceOf(ModelMetadata::class, $model);

        $combinations = $this->inputModalityCombinations($model);

        // Every non-empty subset of the five supported modalities.
        $this->assertCount(31, $combinations);
        $this->assertContains(['text'], $combinations);
        $this->assertContains(['image'], $combinations);
        $this->assertContains(['audio'], $combinations);
        $this->assertContains(['video'], $combinations);
        $this->assertContains(['document'], $combinations);
        $this->assertContains(['text', 'image'], $combinations);
        $this->assertContains(['text', 'image', 'audio', 'video', 'document'], $combinations);
    }

    public function testPreviewMultimodalEmbeddingModelIsTreatedAsMultimodal(): void
    {
        if (!interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('Embedding generation requires PHP AI Client 1.4.0 or later.');
        }

        $models = $this->parseModels([
            'models' => [
                [
                    'name' => 'models/gemini-embedding-2-preview',
                    'supportedGenerationMethods' => ['embedContent'],
                ],
            ],
        ]);

        $model = $this->findModel($models, 'gemini-embedding-2-preview');
        $this->assertInstanceOf(ModelMetadata::class, $model);
        $this->assertContains(['image'], $this->inputModalityCombinations($model));
    }

    /**
     * Returns the advertised input modality combinations as plain string values.
     *
     * @param ModelMetadata $model The model metadata to inspect.
     * @return list<list<string>> The advertised input modality combinations.
     */
    private function inputModalityCombinations(ModelMetadata $model): array
    {
        foreach ($model->getSupportedOptions() as $option) {
            if (!$option->getName()->isInputModalities()) {
                continue;
            }

            $combinations = [];
            foreach ((array) $option->getSupportedValues() as $combination) {
                $combinations[] = array_map(
                    static function ($modality): string {
                        return $modality->value;
                    },
                    $combination
                );
            }
            return $combinations;
        }

        return [];
    }

    public function testEmbeddingModelsAdvertiseNoCapabilitiesWithoutClientSupport(): void
    {
        if (interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('The installed PHP AI Client supports embedding generation.');
        }

        $embeddingModel = $this->parseEmbeddingModel();

        $this->assertSame([], $embeddingModel->getSupportedCapabilities());
        $this->assertSame([], $embeddingModel->getSupportedOptions());
    }

    /**
     * Parses a models response payload and returns the embedding model's metadata.
     *
     * @return ModelMetadata The embedding model metadata.
     */
    private function parseEmbeddingModel(): ModelMetadata
    {
        $models = $this->parseModels([
            'models' => [
                [
                    'name' => 'models/gemini-embedding-001',
                    'displayName' => 'Gemini Embedding 001',
                    'supportedGenerationMethods' => ['embedContent', 'countTextTokens'],
                ],
                [
                    'name' => 'models/gemini-2.5-flash',
                    'displayName' => 'Gemini 2.5 Flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ],
            ],
        ]);

        $embeddingModel = $this->findModel($models, 'gemini-embedding-001');
        $this->assertInstanceOf(ModelMetadata::class, $embeddingModel);

        return $embeddingModel;
    }

    public function testTextModelsAreUnaffected(): void
    {
        $models = $this->parseModels([
            'models' => [
                [
                    'name' => 'models/gemini-embedding-001',
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
