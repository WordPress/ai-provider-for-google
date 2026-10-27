<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Util;

use PHPUnit\Framework\TestCase;
use WordPress\GoogleAiProvider\Util\ResponseFileValidator;

/**
 * Tests for ResponseFileValidator.
 *
 * @since 1.2.1
 */
class ResponseFileValidatorTest extends TestCase
{
    /**
     * Tests http(s) URL validation.
     *
     * @dataProvider httpUrlProvider
     *
     * @since 1.2.1
     *
     * @param string $value The value to check.
     * @param bool $expected The expected result.
     */
    public function testIsHttpUrl(string $value, bool $expected): void
    {
        $this->assertSame($expected, ResponseFileValidator::isHttpUrl($value));
    }

    /**
     * Provides URL test cases.
     *
     * @since 1.2.1
     *
     * @return array<string, array{string, bool}>
     */
    public function httpUrlProvider(): array
    {
        return [
            'https' => ['https://example.com/a.png', true],
            'http' => ['http://example.com/a.png', true],
            'upper-case scheme' => ['HTTPS://example.com/a.png', true],
            'with query' => ['https://example.com/a.png?x=1&y=2', true],
            'scheme only' => ['http://', false],
            'space in host' => ['http://exam ple.com/a.png', false],
            'trailing newline' => ["https://example.com/a.png\n", false],
            'local path' => [__FILE__, false],
            'file wrapper' => ['file://' . __FILE__, false],
            'ftp wrapper' => ['ftp://127.0.0.1:1/x', false],
            'gs wrapper' => ['gs://bucket/object.txt', false],
            'data uri' => ['data:image/png;base64,iVBORw0KGgo=', false],
            'empty' => ['', false],
        ];
    }

    /**
     * Tests base64 validation.
     *
     * @dataProvider base64Provider
     *
     * @since 1.2.1
     *
     * @param string $value The value to check.
     * @param bool $expected The expected result.
     */
    public function testIsBase64(string $value, bool $expected): void
    {
        $this->assertSame($expected, ResponseFileValidator::isBase64($value));
    }

    /**
     * Provides base64 test cases.
     *
     * @since 1.2.1
     *
     * @return array<string, array{string, bool}>
     */
    public function base64Provider(): array
    {
        return [
            'padded' => ['iVBORw0KGgo=', true],
            'double padded' => ['iVBORw0KGg==', true],
            'unpadded' => ['iVBORw0KGgo', true],
            'jpeg prefix' => ['/9j/4AAQSkZJRg', true],
            'empty' => ['', true],
            'trailing newline' => ["iVBORw0KGgo=\n", false],
            'data uri' => ['data:image/png;base64,iVBORw0KGgo=', false],
            'local path with extension' => [__FILE__, false],
            'triple padding' => ['iVBO===', false],
            'url-safe alphabet' => ['iVBO-w0K_go=', false],
            'whitespace' => ['iVBO Rw0K', false],
        ];
    }

    /**
     * Tests MIME type validation.
     *
     * @dataProvider mimeTypeProvider
     *
     * @since 1.2.1
     *
     * @param string $value The value to check.
     * @param bool $expected The expected result.
     */
    public function testIsMimeType(string $value, bool $expected): void
    {
        $this->assertSame($expected, ResponseFileValidator::isMimeType($value));
    }

    /**
     * Provides MIME type test cases.
     *
     * @since 1.2.1
     *
     * @return array<string, array{string, bool}>
     */
    public function mimeTypeProvider(): array
    {
        return [
            'image/png' => ['image/png', true],
            'mixed case' => ['Image/PNG', true],
            'vendor tree' => ['application/vnd.ms-excel', true],
            'suffix' => ['image/svg+xml', true],
            'no slash' => ['nope', false],
            'empty subtype' => ['image/', false],
            'empty type' => ['/png', false],
            'trailing newline' => ["image/png\n", false],
            'with parameter' => ['audio/L16;codec=pcm', false],
            'empty' => ['', false],
        ];
    }
}
