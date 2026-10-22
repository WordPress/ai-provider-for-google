<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Models;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\GoogleAiProvider\Models\GoogleImageGenerationModel;

/**
 * Tests for GoogleImageGenerationModel response parsing.
 *
 * @since n.e.x.t
 */
class GoogleImageGenerationModelTest extends TestCase
{
    /**
     * Tests that base64 prediction bytes are parsed as an inline image.
     *
     * @since n.e.x.t
     */
    public function testBytesBase64EncodedIsParsedAsInlineImage(): void
    {
        $result = $this->parseResponse([
            'predictions' => [['mimeType' => 'image/png', 'bytesBase64Encoded' => 'iVBORw0KGgo=']],
        ]);

        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);
        $this->assertTrue($file->isInline());
        $this->assertSame('iVBORw0KGgo=', $file->getBase64Data());
        $this->assertSame('image/png', $file->getMimeType());
    }

    /**
     * Tests that prediction URLs must be http(s) URLs.
     *
     * @dataProvider nonHttpUrlProvider
     *
     * @since n.e.x.t
     *
     * @param string $url The response-supplied URL.
     */
    public function testUrlPredictionRejectsNonHttpUrl(string $url): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('The url value must be an http(s) URL.');

        $this->parseResponse(['predictions' => [['url' => $url]]]);
    }

    /**
     * Provides URLs that must be rejected.
     *
     * @since n.e.x.t
     *
     * @return array<string, array{string}>
     */
    public function nonHttpUrlProvider(): array
    {
        return [
            'local path' => [__FILE__],
            'file wrapper' => ['file://' . __FILE__],
            'ftp wrapper' => ['ftp://127.0.0.1:1/x'],
        ];
    }

    /**
     * Tests that prediction bytes that are not base64 are rejected.
     *
     * @since n.e.x.t
     */
    public function testBytesBase64EncodedRejectsNonBase64(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('The bytesBase64Encoded value is not valid base64 data.');

        $this->parseResponse(['predictions' => [['bytesBase64Encoded' => __FILE__]]]);
    }

    /**
     * Tests that an invalid prediction MIME type is rejected.
     *
     * @since n.e.x.t
     */
    public function testInvalidMimeTypeIsRejected(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('The mimeType value is invalid.');

        $this->parseResponse(['predictions' => [['mimeType' => 'nope', 'bytesBase64Encoded' => 'iVBORw0KGgo=']]]);
    }

    /**
     * Parses a response array through the model.
     *
     * @since n.e.x.t
     *
     * @param array<string, mixed> $data The response data.
     * @return GenerativeAiResult The parsed result.
     */
    private function parseResponse(array $data): GenerativeAiResult
    {
        $model = new class (
            new ModelMetadata('imagen-test', 'Imagen test', [CapabilityEnum::imageGeneration()], []),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleImageGenerationModel {
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
