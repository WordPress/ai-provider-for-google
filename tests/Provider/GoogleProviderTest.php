<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Provider;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\GoogleAiProvider\Models\GoogleEmbeddingGenerationModel;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

/**
 * Tests for the Google provider.
 *
 * @since 1.2.0
 */
class GoogleProviderTest extends TestCase
{
    /**
     * Tests provider availability against the WordPress 7.0 PHP AI Client baseline.
     *
     * @since 1.2.0
     */
    public function testProviderAvailabilitySupportsPhpAiClient131(): void
    {
        $this->assertTrue(version_compare(AiClient::VERSION, '1.3.1', '>='));

        $method = new ReflectionMethod(GoogleProvider::class, 'createProviderAvailability');
        $method->setAccessible(true);

        $this->assertInstanceOf(
            ListModelsApiBasedProviderAvailability::class,
            $method->invoke(null)
        );
    }

    /**
     * Tests that an embedding-capable model is created as an embedding generation model.
     *
     * @since 1.3.0
     */
    public function testCreatesEmbeddingModelForEmbeddingCapability(): void
    {
        if (!interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('Embedding generation requires PHP AI Client 1.4.0 or later.');
        }

        $model = $this->createModel($this->createEmbeddingModelMetadata());

        $this->assertInstanceOf(GoogleEmbeddingGenerationModel::class, $model);
    }

    /**
     * Tests that embedding models are rejected when the PHP AI Client lacks embedding support.
     *
     * @since 1.3.0
     */
    public function testRejectsEmbeddingCapabilityWithoutClientSupport(): void
    {
        if (interface_exists(EmbeddingGenerationModelInterface::class)) {
            $this->markTestSkipped('The installed PHP AI Client supports embedding generation.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported model capabilities: embedding_generation');
        $this->createModel($this->createEmbeddingModelMetadata());
    }

    /**
     * Creates model metadata for an embedding-capable model.
     *
     * @return ModelMetadata The model metadata.
     */
    private function createEmbeddingModelMetadata(): ModelMetadata
    {
        return new ModelMetadata(
            'text-embedding-004',
            'text-embedding-004',
            [CapabilityEnum::embeddingGeneration()],
            []
        );
    }

    /**
     * Invokes the provider's protected createModel() factory for the given model metadata.
     *
     * @param ModelMetadata $modelMetadata The model metadata.
     * @return ModelInterface The created model.
     */
    private function createModel(ModelMetadata $modelMetadata): ModelInterface
    {
        $provider = new class extends GoogleProvider {
            public static function exposeCreateModel(
                ModelMetadata $modelMetadata,
                ProviderMetadata $providerMetadata
            ): ModelInterface {
                return static::createModel($modelMetadata, $providerMetadata);
            }
        };

        $providerClass = get_class($provider);

        return $providerClass::exposeCreateModel(
            $modelMetadata,
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        );
    }
}
