<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\MessagePart;

/**
 * Trait for transforming message parts into Google API request parts.
 *
 * @since n.e.x.t
 */
trait WithMessagePartDataTrait
{
    /**
     * Returns the Google API specific data for a message part.
     *
     * @since n.e.x.t
     *
     * @param MessagePart $part The message part to get the data for.
     * @return ?array<string, mixed> The data for the message part, or null if not applicable.
     * @throws InvalidArgumentException If the message part type or data is unsupported.
     */
    protected function getMessagePartData(MessagePart $part): ?array
    {
        $type = $part->getType();
        if ($type->isText()) {
            if ($part->getChannel()->isThought()) {
                return [
                    'text'    => $part->getText(),
                    'thought' => true,
                ];
            }
            return [
                'text' => $part->getText(),
            ];
        }
        if ($type->isFile()) {
            $file = $part->getFile();
            if (!$file) {
                // This should be impossible due to class internals, but still needs to be checked.
                throw new RuntimeException(
                    'The file typed message part must contain a file.'
                );
            }
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
        if ($type->isFunctionCall()) {
            $functionCall = $part->getFunctionCall();
            if (!$functionCall) {
                // This should be impossible due to class internals, but still needs to be checked.
                throw new RuntimeException(
                    'The function_call typed message part must contain a function call.'
                );
            }
            $functionCallData = [
                'name' => $functionCall->getName(),
            ];
            // Only include args if present; Google's API accepts omitting args for no-argument functions.
            $args = $functionCall->getArgs();
            if ($args !== null) {
                $functionCallData['args'] = $args;
            }
            $partData = [
                'functionCall' => $functionCallData,
            ];
            /*
             * Thinking models attach a thought signature to every function call part, and the
             * Google AI API requires it to be sent back unchanged on all following turns of the
             * same conversation. Without it a multi-turn tool call fails with "Function call is
             * missing a thought_signature in functionCall parts".
             */
            $thoughtSignature = $this->getMessagePartThoughtSignature($part);
            if ($thoughtSignature !== null) {
                $partData['thoughtSignature'] = $thoughtSignature;
            }
            return $partData;
        }
        if ($type->isFunctionResponse()) {
            $functionResponse = $part->getFunctionResponse();
            if (!$functionResponse) {
                // This should be impossible due to class internals, but still needs to be checked.
                throw new RuntimeException(
                    'The function_response typed message part must contain a function response.'
                );
            }
            return [
                'functionResponse' => [
                    'name' => $functionResponse->getName(),

                    /*
                     * The Google AI API requires function responses to be objects.
                     * See also https://ai.google.dev/gemini-api/docs/function-calling#multi-turn-example-1
                     */
                    'response' => [
                        'name' => $functionResponse->getName(),
                        'content' => $functionResponse->getResponse(),
                    ],
                ],
            ];
        }
        throw new InvalidArgumentException(
            sprintf(
                'Unsupported message part type "%s".',
                $type
            )
        );
    }

    /**
     * Returns the thought signature of a message part, if it carries one.
     *
     * @since n.e.x.t
     *
     * @param MessagePart $part The message part to get the thought signature for.
     * @return string|null The thought signature, or null if there is none.
     */
    protected function getMessagePartThoughtSignature(MessagePart $part): ?string
    {
        $thoughtSignature = $part->getThoughtSignature();

        return $thoughtSignature !== null && $thoughtSignature !== '' ? $thoughtSignature : null;
    }
}
