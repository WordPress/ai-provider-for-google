<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\unit\Provider;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\GoogleAiProvider\Models\GoogleEmbeddingGenerationModel;
use WordPress\GoogleAiProvider\Models\GoogleImageGenerationModel;
use WordPress\GoogleAiProvider\Models\GoogleTextGenerationModel;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

/**
 * @covers \WordPress\GoogleAiProvider\Provider\GoogleProvider
 */
class GoogleProviderTest extends TestCase
{
    public function testCreatesEmbeddingModelForEmbeddingCapability(): void
    {
        $model = $this->createModel(
            new ModelMetadata(
                'text-embedding-004',
                'text-embedding-004',
                [CapabilityEnum::embeddingGeneration()],
                []
            )
        );

        $this->assertInstanceOf(GoogleEmbeddingGenerationModel::class, $model);
    }

    public function testCreatesTextModelForTextCapability(): void
    {
        $model = $this->createModel(
            new ModelMetadata(
                'gemini-2.5-flash',
                'gemini-2.5-flash',
                [CapabilityEnum::textGeneration()],
                []
            )
        );

        $this->assertInstanceOf(GoogleTextGenerationModel::class, $model);
    }

    public function testCreatesImageModelForImageCapability(): void
    {
        $model = $this->createModel(
            new ModelMetadata(
                'imagen-3.0-generate-002',
                'imagen-3.0-generate-002',
                [CapabilityEnum::imageGeneration()],
                []
            )
        );

        $this->assertInstanceOf(GoogleImageGenerationModel::class, $model);
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
