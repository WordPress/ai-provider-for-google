<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Util;

/**
 * Validates file-related values received in provider responses before they are turned into File objects.
 *
 * Response data is untrusted. These checks guarantee that a value accepted here is interpreted by the SDK's
 * File class exactly as intended (remote URL or inline base64 data) and never as something else.
 *
 * @since 1.2.1
 */
final class ResponseFileValidator
{
    /**
     * Pattern for an http(s) URL scheme (case-insensitive).
     *
     * @since 1.2.1
     */
    private const HTTP_URL_PATTERN = '/^https?:\/\//i';

    /**
     * Pattern for plain base64 data (no data URI prefix). An empty payload is allowed.
     *
     * @since 1.2.1
     */
    private const BASE64_PATTERN = '/^[A-Za-z0-9+\/]*={0,2}$/D';

    /**
     * Pattern for a MIME type (type/subtype), matching the SDK's own MIME type grammar.
     *
     * @since 1.2.1
     */
    private const MIME_TYPE_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9!#$&\-\^_+.]*\/[a-zA-Z0-9][a-zA-Z0-9!#$&\-\^_+.]*$/D';

    /**
     * Prevents instantiation.
     *
     * @since 1.2.1
     */
    private function __construct()
    {
    }

    /**
     * Checks whether the given value is a well-formed http(s) URL.
     *
     * Mirrors the SDK's own URL detection so that a value accepted here is never re-interpreted
     * by the File constructor as anything other than a remote URL.
     *
     * @since 1.2.1
     *
     * @param string $value The value to check.
     * @return bool True if the value is a valid http(s) URL.
     */
    public static function isHttpUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && preg_match(self::HTTP_URL_PATTERN, $value) === 1;
    }

    /**
     * Checks whether the given value is plain base64 data.
     *
     * @since 1.2.1
     *
     * @param string $value The value to check.
     * @return bool True if the value only contains base64 characters and padding.
     */
    public static function isBase64(string $value): bool
    {
        return preg_match(self::BASE64_PATTERN, $value) === 1;
    }

    /**
     * Checks whether the given value is a MIME type of the form type/subtype.
     *
     * @since 1.2.1
     *
     * @param string $value The value to check.
     * @return bool True if the value is a syntactically valid MIME type.
     */
    public static function isMimeType(string $value): bool
    {
        return preg_match(self::MIME_TYPE_PATTERN, $value) === 1;
    }
}
