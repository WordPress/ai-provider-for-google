<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Provider;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
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
}
