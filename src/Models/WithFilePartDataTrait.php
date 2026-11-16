<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Models;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Files\DTO\File;

/**
 * Trait for transforming files into Google API request parts.
 *
 * @since n.e.x.t
 */
trait WithFilePartDataTrait
{
    /**
     * Returns the Google API specific part data for a file.
     *
     * @since n.e.x.t
     *
     * @param File $file The file to get the part data for.
     * @return array<string, mixed> The part data for the file.
     * @throws RuntimeException If the file is missing its URL or base64 data.
     */
    protected function getFilePartData(File $file): array
    {
        if ($file->isRemote()) {
            $fileUrl = $file->getUrl();
            if (!$fileUrl) {
                // This should be impossible due to class internals, but still needs to be checked.
                throw new RuntimeException(
                    'The remote file must contain a URL.'
                );
            }
            // Special case for YouTube video URLs.
            if (preg_match('/^https?:\/\/(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/)/', $fileUrl)) {
                return [
                    'fileData' => [
                        'fileUri' => $fileUrl,
                    ],
                ];
            }
            return [
                'fileData' => [
                    'mimeType' => $file->getMimeType(),
                    'fileUri' => $fileUrl,
                ],
            ];
        }
        // Else, it is an inline file.
        $fileBase64Data = $file->getBase64Data();
        if (!$fileBase64Data) {
            // This should be impossible due to class internals, but still needs to be checked.
            throw new RuntimeException(
                'The inline file must contain base64 data.'
            );
        }
        return [
            'inlineData' => [
                'mimeType' => $file->getMimeType(),
                'data' => $fileBase64Data,
            ],
        ];
    }
}
