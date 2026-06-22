<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Provider;

use Exception;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Class to check availability for the Google provider.
 *
 * Used as a fallback when ListModelsApiBasedProviderAvailability is not available in the version of the php-ai-client
 * library bundled with WordPress. Checks valid API access by attempting to list models.
 *
 * @since 1.2.0
 */
class GoogleProviderAvailability implements ProviderAvailabilityInterface
{
    /**
     * @var ModelMetadataDirectoryInterface The model metadata directory to use for checking availability.
     */
    private ModelMetadataDirectoryInterface $modelMetadataDirectory;

    /**
     * Constructor.
     *
     * @since 1.2.0
     *
     * @param ModelMetadataDirectoryInterface $modelMetadataDirectory The model metadata directory to use for checking
     *                                                                availability.
     */
    public function __construct(ModelMetadataDirectoryInterface $modelMetadataDirectory)
    {
        $this->modelMetadataDirectory = $modelMetadataDirectory;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function isConfigured(): bool
    {
        try {
            // Attempt to list models to check if the provider is available.
            $this->modelMetadataDirectory->listModelMetadata();
            return true;
        } catch (Exception $e) {
            // If an exception occurs, the provider is not available.
            return false;
        }
    }
}
