<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Tests\Models;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Models\GoogleTextToSpeechConversionModel;

/**
 * Tests for the Google text-to-speech conversion model.
 *
 * @since 1.2.0
 */
class GoogleTextToSpeechConversionModelTest extends TestCase
{
    /**
     * Prepares convert parameters through the model's protected parameter builder.
     *
     * @since 1.2.0
     *
     * @param list<Message> $prompt The prompt messages.
     * @param ModelConfig|null $config Optional model configuration.
     * @return array<string, mixed> Prepared parameters.
     */
    private function prepareConvertParams(array $prompt, ?ModelConfig $config = null): array
    {
        $model = new class (
            new ModelMetadata(
                'gemini-2.5-flash-preview-tts',
                'Gemini TTS',
                [CapabilityEnum::textToSpeechConversion()],
                []
            ),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextToSpeechConversionModel {
            /**
             * Exposes prepareConvertParams for testing.
             *
             * @param list<Message> $prompt The prompt messages.
             * @return array<string, mixed> Prepared parameters.
             */
            public function prepareConvertParams(array $prompt): array
            {
                return parent::prepareConvertParams($prompt);
            }
        };

        if ($config !== null) {
            $model->setConfig($config);
        }

        return $model->prepareConvertParams($prompt);
    }

    /**
     * Prepares prompt text through the model's protected text extractor.
     *
     * @since 1.2.0
     *
     * @param list<Message> $prompt The prompt messages.
     * @return string Extracted prompt text.
     */
    private function preparePromptText(array $prompt): string
    {
        $model = new class (
            new ModelMetadata(
                'gemini-2.5-flash-preview-tts',
                'Gemini TTS',
                [CapabilityEnum::textToSpeechConversion()],
                []
            ),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextToSpeechConversionModel {
            /**
             * Exposes preparePromptText for testing.
             *
             * @param list<Message> $prompt The prompt messages.
             * @return string Extracted prompt text.
             */
            public function preparePromptText(array $prompt): string
            {
                return parent::preparePromptText($prompt);
            }
        };

        return $model->preparePromptText($prompt);
    }

    /**
     * Parses a response through the model's protected response parser.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $data Response data.
     * @return GenerativeAiResult Parsed result.
     */
    private function parseResponse(array $data): GenerativeAiResult
    {
        $model = new class (
            new ModelMetadata(
                'gemini-2.5-flash-preview-tts',
                'Gemini TTS',
                [CapabilityEnum::textToSpeechConversion()],
                []
            ),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextToSpeechConversionModel {
            /**
             * Exposes parseResponseToGenerativeAiResult for testing.
             *
             * @param Response $response The HTTP response.
             * @return GenerativeAiResult Parsed generative AI result.
             */
            public function parseResponse(Response $response): GenerativeAiResult
            {
                return $this->parseResponseToGenerativeAiResult($response);
            }
        };

        return $model->parseResponse(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
    }

    /**
     * Wraps raw PCM data in a WAV container through the model's protected method.
     *
     * @since 1.2.0
     *
     * @param string $pcm The raw PCM bytes.
     * @param int $sampleRate The sample rate in Hz.
     * @param int $channels Channels count.
     * @param int $bitsPerSample Bits per sample.
     * @return string The WAV container bytes.
     */
    private function wrapPcm(
        string $pcm,
        int $sampleRate,
        int $channels = 1,
        int $bitsPerSample = 16
    ): string {
        $model = new class (
            new ModelMetadata(
                'gemini-2.5-flash-preview-tts',
                'Gemini TTS',
                [CapabilityEnum::textToSpeechConversion()],
                []
            ),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        ) extends GoogleTextToSpeechConversionModel {
            /**
             * Exposes wrapPcmInWav for testing.
             *
             * @param string $pcm The raw PCM bytes.
             * @param int $sampleRate The sample rate in Hz.
             * @param int $channels Channels count.
             * @param int $bitsPerSample Bits per sample.
             * @return string The WAV container bytes.
             */
            public function wrapPcm(
                string $pcm,
                int $sampleRate,
                int $channels = 1,
                int $bitsPerSample = 16
            ): string {
                return parent::wrapPcmInWav($pcm, $sampleRate, $channels, $bitsPerSample);
            }
        };

        return $model->wrapPcm($pcm, $sampleRate, $channels, $bitsPerSample);
    }

    /**
     * Tests that prepareConvertParams defaults to Kore voice and AUDIO modality.
     *
     * @since 1.2.0
     */
    public function testPrepareConvertParamsWithDefaultVoice(): void
    {
        $prompt = [
            new Message(MessageRoleEnum::user(), [new MessagePart('Hello world')]),
        ];

        $params = $this->prepareConvertParams($prompt);

        $this->assertSame(
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Hello world'],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => [
                                'voiceName' => 'Kore',
                            ],
                        ],
                    ],
                ],
            ],
            $params
        );
    }

    /**
     * Tests that prepareConvertParams uses configured voice when specified.
     *
     * @since 1.2.0
     */
    public function testPrepareConvertParamsWithConfiguredVoice(): void
    {
        $config = ModelConfig::fromArray(['outputSpeechVoice' => 'Puck']);
        $prompt = [
            new Message(MessageRoleEnum::user(), [new MessagePart('Custom voice test')]),
        ];

        $params = $this->prepareConvertParams($prompt, $config);

        $this->assertSame(
            'Puck',
            $params['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']
        );
    }

    /**
     * Tests that prepareConvertParams properly merges custom options.
     *
     * @since 1.2.0
     */
    public function testPrepareConvertParamsWithCustomOptions(): void
    {
        $config = ModelConfig::fromArray([
            'customOptions' => [
                'generationConfig.temperature' => 0.7,
                'customTopLevel' => 'customValue',
            ],
        ]);
        $prompt = [
            new Message(MessageRoleEnum::user(), [new MessagePart('Options test')]),
        ];

        $params = $this->prepareConvertParams($prompt, $config);

        $this->assertSame(0.7, $params['generationConfig']['temperature']);
        $this->assertSame('customValue', $params['customTopLevel']);
    }

    /**
     * Tests that prepareConvertParams throws exception on generationConfig custom option conflict.
     *
     * @since 1.2.0
     */
    public function testPrepareConvertParamsThrowsOnGenerationConfigConflict(): void
    {
        $config = ModelConfig::fromArray([
            'customOptions' => [
                'generationConfig.responseModalities' => ['TEXT'],
            ],
        ]);
        $prompt = [
            new Message(MessageRoleEnum::user(), [new MessagePart('Conflict test')]),
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The custom generationConfig option "responseModalities" conflicts with an existing parameter.'
        );

        $this->prepareConvertParams($prompt, $config);
    }

    /**
     * Tests that prepareConvertParams throws exception on top-level custom option conflict.
     *
     * @since 1.2.0
     */
    public function testPrepareConvertParamsThrowsOnTopLevelConflict(): void
    {
        $config = ModelConfig::fromArray([
            'customOptions' => [
                'contents' => [],
            ],
        ]);
        $prompt = [
            new Message(MessageRoleEnum::user(), [new MessagePart('Conflict test')]),
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The custom option "contents" conflicts with an existing parameter.');

        $this->prepareConvertParams($prompt, $config);
    }

    /**
     * Tests that preparePromptText concatenates text across multiple messages and parts.
     *
     * @since 1.2.0
     */
    public function testPreparePromptTextConcatenatesMultipleMessageParts(): void
    {
        $prompt = [
            new Message(MessageRoleEnum::user(), [
                new MessagePart('First line of speech.'),
                new MessagePart('Second line of speech.'),
            ]),
            new Message(MessageRoleEnum::user(), [
                new MessagePart('Third line of speech.'),
            ]),
        ];

        $text = $this->preparePromptText($prompt);

        $this->assertSame(
            "First line of speech.
Second line of speech.
Third line of speech.",
            $text
        );
    }

    /**
     * Tests that preparePromptText throws when prompt has no text.
     *
     * @since 1.2.0
     */
    public function testPreparePromptTextThrowsWhenEmpty(): void
    {
        $prompt = [
            new Message(MessageRoleEnum::user(), []),
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The prompt must contain text to convert to speech.');

        $this->preparePromptText($prompt);
    }

    /**
     * Tests response parsing into a GenerativeAiResult with audio WAV file and token usage.
     *
     * @since 1.2.0
     */
    public function testParseResponseToGenerativeAiResult(): void
    {
        $rawPcm = 'dummy-raw-pcm-audio-stream-data';
        $base64Data = base64_encode($rawPcm);

        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => $base64Data,
                                    'mimeType' => 'audio/L16;codec=pcm;rate=24000',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 15,
                'candidatesTokenCount' => 50,
                'totalTokenCount' => 65,
            ],
        ];

        $result = $this->parseResponse($responseData);

        $this->assertInstanceOf(GenerativeAiResult::class, $result);
        $this->assertStringStartsWith('google-tts-', $result->getId());

        $candidates = $result->getCandidates();
        $this->assertCount(1, $candidates);

        $candidate = $candidates[0];
        $this->assertTrue($candidate->getFinishReason()->isStop());

        $parts = $candidate->getMessage()->getParts();
        $this->assertCount(1, $parts);

        $file = $parts[0]->getFile();
        $this->assertNotNull($file);
        $this->assertTrue($file->isInline());
        $this->assertSame('audio/wav', $file->getMimeType());

        $base64Audio = $file->getBase64Data();
        $this->assertNotNull($base64Audio);

        $wavBytes = base64_decode($base64Audio, true);
        $this->assertIsString($wavBytes);
        $this->assertStringStartsWith('RIFF', $wavBytes);
        $this->assertStringContainsString('WAVEfmt ', $wavBytes);
        $this->assertStringContainsString('data', $wavBytes);
        $this->assertStringEndsWith($rawPcm, $wavBytes);

        $tokenUsage = $result->getTokenUsage();
        $this->assertSame(15, $tokenUsage->getPromptTokens());
        $this->assertSame(50, $tokenUsage->getCompletionTokens());
        $this->assertSame(65, $tokenUsage->getTotalTokens());
    }

    /**
     * Tests response parsing extracts sample rate from mimeType correctly.
     *
     * @since 1.2.0
     */
    public function testParseResponseWithCustomSampleRate(): void
    {
        $rawPcm = 'pcm-16khz-sample-data';
        $base64Data = base64_encode($rawPcm);

        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => $base64Data,
                                    'mimeType' => 'audio/L16;codec=pcm;rate=16000',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parseResponse($responseData);
        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);

        $base64Audio = $file->getBase64Data();
        $this->assertNotNull($base64Audio);

        $wavBytes = (string) base64_decode($base64Audio, true);
        $unpacked = unpack('VsampleRate', substr($wavBytes, 24, 4));
        $this->assertIsArray($unpacked);
        $this->assertSame(16000, $unpacked['sampleRate']);
    }

    /**
     * Tests response parsing falls back to default 24000 Hz when mimeType is missing rate parameter.
     *
     * @since 1.2.0
     */
    public function testParseResponseWithDefaultSampleRateFallback(): void
    {
        $rawPcm = 'pcm-default-sample-data';
        $base64Data = base64_encode($rawPcm);

        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => $base64Data,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parseResponse($responseData);
        $file = $result->getCandidates()[0]->getMessage()->getParts()[0]->getFile();
        $this->assertNotNull($file);

        $base64Audio = $file->getBase64Data();
        $this->assertNotNull($base64Audio);

        $wavBytes = (string) base64_decode($base64Audio, true);
        $unpacked = unpack('VsampleRate', substr($wavBytes, 24, 4));
        $this->assertIsArray($unpacked);
        $this->assertSame(24000, $unpacked['sampleRate']);
    }

    /**
     * Tests response parsing throws when candidates parts array is missing.
     *
     * @since 1.2.0
     */
    public function testParseResponseThrowsWhenCandidatesPartsMissing(): void
    {
        $responseData = [
            'candidates' => [
                [
                    'content' => [],
                ],
            ],
        ];

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Missing the "candidates[0].content.parts" key.');

        $this->parseResponse($responseData);
    }

    /**
     * Tests response parsing throws when inlineData is missing in parts.
     *
     * @since 1.2.0
     */
    public function testParseResponseThrowsWhenInlineDataMissing(): void
    {
        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Non-audio text part'],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('Missing the "candidates[0].content.parts[].inlineData" key.');

        $this->parseResponse($responseData);
    }

    /**
     * Tests response parsing throws when inline audio data cannot be base64-decoded.
     *
     * @since 1.2.0
     */
    public function testParseResponseThrowsWhenAudioDataInvalidBase64(): void
    {
        $responseData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => '!!!not-valid-base64!!!',
                                    'mimeType' => 'audio/L16;codec=pcm;rate=24000',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('The audio data could not be base64-decoded.');

        $this->parseResponse($responseData);
    }

    /**
     * Tests wrapPcmInWav outputs standard 44-byte RIFF/WAVE header and PCM data payload.
     *
     * @since 1.2.0
     */
    public function testWrapPcmInWavStructure(): void
    {
        $pcm = '12345678';
        $wav = $this->wrapPcm($pcm, 24000, 1, 16);

        $this->assertSame(52, strlen($wav));
        $this->assertSame('RIFF', substr($wav, 0, 4));

        $riffSize = unpack('Vsize', substr($wav, 4, 4));
        $this->assertIsArray($riffSize);
        $this->assertSame(36 + strlen($pcm), $riffSize['size']);

        $this->assertSame('WAVE', substr($wav, 8, 4));
        $this->assertSame('fmt ', substr($wav, 12, 4));

        $fmtChunk = unpack(
            'Vsubchunk1Size/vaudioFormat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample',
            substr($wav, 16, 20)
        );
        $this->assertIsArray($fmtChunk);
        $this->assertSame(16, $fmtChunk['subchunk1Size']);
        $this->assertSame(1, $fmtChunk['audioFormat']);
        $this->assertSame(1, $fmtChunk['channels']);
        $this->assertSame(24000, $fmtChunk['sampleRate']);
        $this->assertSame(48000, $fmtChunk['byteRate']);
        $this->assertSame(2, $fmtChunk['blockAlign']);
        $this->assertSame(16, $fmtChunk['bitsPerSample']);

        $this->assertSame('data', substr($wav, 36, 4));
        $dataSize = unpack('Vsize', substr($wav, 40, 4));
        $this->assertIsArray($dataSize);
        $this->assertSame(strlen($pcm), $dataSize['size']);
        $this->assertSame($pcm, substr($wav, 44));
    }

    /**
     * Tests that getRequestAuthentication wraps ApiKeyRequestAuthentication in GoogleApiKeyRequestAuthentication.
     *
     * @since 1.2.0
     */
    public function testGetRequestAuthenticationWrapsApiKey(): void
    {
        $model = new GoogleTextToSpeechConversionModel(
            new ModelMetadata(
                'gemini-2.5-flash-preview-tts',
                'Gemini TTS',
                [CapabilityEnum::textToSpeechConversion()],
                []
            ),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        );
        $model->setRequestAuthentication(new ApiKeyRequestAuthentication('test-google-key-123'));

        $auth = $model->getRequestAuthentication();

        $this->assertInstanceOf(GoogleApiKeyRequestAuthentication::class, $auth);
        $this->assertSame('test-google-key-123', $auth->getApiKey());
    }

    /**
     * Tests that getRequestAuthentication preserves non-ApiKey request authentication.
     *
     * @since 1.2.0
     */
    public function testGetRequestAuthenticationPreservesNonApiKeyAuthentication(): void
    {
        $model = new GoogleTextToSpeechConversionModel(
            new ModelMetadata(
                'gemini-2.5-flash-preview-tts',
                'Gemini TTS',
                [CapabilityEnum::textToSpeechConversion()],
                []
            ),
            new ProviderMetadata('google', 'Google', ProviderTypeEnum::cloud())
        );
        $customAuth = $this->createMock(RequestAuthenticationInterface::class);

        $model->setRequestAuthentication($customAuth);

        $auth = $model->getRequestAuthentication();

        $this->assertSame($customAuth, $auth);
    }
}
