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
        $this->expectExceptionMessage('The url value must be a valid http(s) URL.');

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


    /**
     * Tests that prediction URLs that are http(s) are kept as remote files, regardless of scheme case.
     *
     * @dataProvider httpUrlProvider
     *
     * @since n.e.x.t
     *
     * @param string $url The response-supplied URL.
     */
    public function testUrlPredictionAcceptsHttpUrl(string $url): void
    {
        $result = $this->parseResponse(['predictions' => [['mimeType' => 'image/png', 'url' => $url]]]);

        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);
        $this->assertTrue($file->isRemote());
        $this->assertSame($url, $file->getUrl());
        $this->assertSame('image/png', $file->getMimeType());
    }

    /**
     * Provides http(s) URLs that must be accepted.
     *
     * @since n.e.x.t
     *
     * @return array<string, array{string}>
     */
    public function httpUrlProvider(): array
    {
        return [
            'https' => ['https://example.com/a.png'],
            'http' => ['http://example.com/a.png'],
            'upper-case scheme' => ['HTTPS://example.com/a.png'],
        ];
    }

    /**
     * Tests that malformed http(s) URLs are rejected with the prediction index.
     *
     * @dataProvider malformedHttpUrlProvider
     *
     * @since n.e.x.t
     *
     * @param string $url The response-supplied URL.
     */
    public function testUrlPredictionRejectsMalformedHttpUrl(string $url): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('predictions[1]');

        $this->parseResponse([
            'predictions' => [
                ['mimeType' => 'image/png', 'bytesBase64Encoded' => 'iVBORw0KGgo='],
                ['mimeType' => 'image/png', 'url' => $url],
            ],
        ]);
    }

    /**
     * Provides http(s)-prefixed values that are not valid URLs.
     *
     * @since n.e.x.t
     *
     * @return array<string, array{string}>
     */
    public function malformedHttpUrlProvider(): array
    {
        return [
            'scheme only' => ['http://'],
            'space in host' => ['http://exam ple.com/a.png'],
            'trailing newline' => ["https://example.com/a.png\n"],
        ];
    }

    /**
     * Tests that an empty prediction payload is accepted as an empty inline file.
     *
     * @since n.e.x.t
     */
    public function testBytesBase64EncodedAcceptsEmptyPayload(): void
    {
        $result = $this->parseResponse(['predictions' => [['mimeType' => 'image/png', 'bytesBase64Encoded' => '']]]);

        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);
        $this->assertTrue($file->isInline());
        $this->assertSame('', $file->getBase64Data());
    }

    /**
     * Tests that base64 payloads with a trailing newline are rejected with the prediction index.
     *
     * @since n.e.x.t
     */
    public function testBytesBase64EncodedRejectsTrailingNewline(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('predictions[0]');

        $this->parseResponse([
            'predictions' => [['mimeType' => 'image/png', 'bytesBase64Encoded' => "iVBORw0KGgo=\n"]],
        ]);
    }

    /**
     * Tests that a prediction naming an existing local file is rejected and the file is not read.
     *
     * @since n.e.x.t
     */
    public function testBytesBase64EncodedDoesNotReadExistingFile(): void
    {
        $dir = sys_get_temp_dir() . '/gimg' . uniqid();
        mkdir($dir);
        $path = $dir . '/secret.txt';
        file_put_contents($path, 'SECRET');

        try {
            $this->expectException(ResponseException::class);
            $this->expectExceptionMessage('not valid base64 data');

            $this->parseResponse(['predictions' => [['mimeType' => 'text/plain', 'bytesBase64Encoded' => $path]]]);
        } finally {
            unlink($path);
            rmdir($dir);
        }
    }

    /**
     * Tests that MIME types are matched case-insensitively.
     *
     * @since n.e.x.t
     */
    public function testMixedCaseMimeTypeIsAccepted(): void
    {
        $result = $this->parseResponse([
            'predictions' => [['mimeType' => 'Image/PNG', 'bytesBase64Encoded' => 'iVBORw0KGgo=']],
        ]);

        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);
        $this->assertSame('iVBORw0KGgo=', $file->getBase64Data());
    }

    /**
     * Tests that a MIME type with a trailing newline is rejected with the prediction index.
     *
     * @since n.e.x.t
     */
    public function testMimeTypeWithTrailingNewlineIsRejected(): void
    {
        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Invalid "predictions[0]"');

        $this->parseResponse([
            'predictions' => [['mimeType' => "image/png\n", 'bytesBase64Encoded' => 'iVBORw0KGgo=']],
        ]);
    }
}
