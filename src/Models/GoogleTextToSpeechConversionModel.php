<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

/**
 * Class for a Google text-to-speech conversion model.
 *
 * Uses the Gemini `:generateContent` endpoint with an AUDIO response modality
 * (models such as `gemini-2.5-flash-preview-tts` and `gemini-3.1-flash-tts-preview`).
 * Gemini returns raw PCM audio, which this class wraps into a WAV container so the
 * result is a usable, inline `audio/wav` file.
 *
 * @since n.e.x.t
 *
 * @phpstan-type InlineData array{data?: string, mimeType?: string}
 * @phpstan-type PartData array{inlineData?: InlineData}
 * @phpstan-type MessageData array{parts?: list<PartData>}
 * @phpstan-type CandidateData array{content?: MessageData}
 * @phpstan-type UsageData array{
 *     promptTokenCount?: int,
 *     candidatesTokenCount?: int,
 *     totalTokenCount?: int
 * }
 * @phpstan-type ResponseData array{
 *     candidates?: list<CandidateData>,
 *     usageMetadata?: UsageData
 * }
 * @phpstan-type InlineAudioData array{data: string, mimeType: string}
 */
class GoogleTextToSpeechConversionModel extends AbstractApiBasedModel implements
    TextToSpeechConversionModelInterface
{
    /**
     * Default prebuilt Gemini voice used when none is configured.
     */
    private const DEFAULT_VOICE = 'Kore';

    /**
     * Default PCM sample rate (Hz) assumed when the response MIME omits it.
     */
    private const DEFAULT_SAMPLE_RATE = 24000;

    /**
     * {@inheritDoc}
     *
     * Since we call the Google API, the API key must be wrapped in the Google
     * specific authentication class.
     *
     * @since n.e.x.t
     */
    public function getRequestAuthentication(): RequestAuthenticationInterface
    {
        $requestAuthentication = parent::getRequestAuthentication();
        if (!$requestAuthentication instanceof ApiKeyRequestAuthentication) {
            return $requestAuthentication;
        }
        return new GoogleApiKeyRequestAuthentication($requestAuthentication->getApiKey());
    }

    /**
     * {@inheritDoc}
     *
     * @since n.e.x.t
     */
    public function convertTextToSpeechResult(array $prompt): GenerativeAiResult
    {
        $httpTransporter = $this->getHttpTransporter();

        $params = $this->prepareConvertParams($prompt);

        $request = new Request(
            HttpMethodEnum::POST(),
            GoogleProvider::url("models/{$this->metadata()->getId()}:generateContent"),
            ['Content-Type' => 'application/json'],
            $params,
            $this->getRequestOptions()
        );

        // Add authentication credentials to the request.
        $request = $this->getRequestAuthentication()->authenticateRequest($request);

        // Send and process the request.
        $response = $httpTransporter->send($request);
        ResponseUtil::throwIfNotSuccessful($response);

        return $this->parseResponseToGenerativeAiResult($response);
    }

    /**
     * Prepares the request parameters for the Gemini generateContent endpoint.
     *
     * @since n.e.x.t
     *
     * @param list<Message> $prompt The prompt messages containing the text.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareConvertParams(array $prompt): array
    {
        $config = $this->getConfig();

        $generationConfig = [
            'responseModalities' => ['AUDIO'],
            'speechConfig'       => [
                'voiceConfig' => [
                    'prebuiltVoiceConfig' => [
                        'voiceName' => $this->prepareVoice(),
                    ],
                ],
            ],
        ];

        $params = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->preparePromptText($prompt)],
                    ],
                ],
            ],
        ];

        /*
         * Custom options are merged into generationConfig when prefixed with
         * "generationConfig.", otherwise at the top level. This mirrors the
         * behavior of GoogleTextGenerationModel. The generationConfig is built
         * as a separate array and assigned last to keep offset access typed.
         */
        $customOptions = $config->getCustomOptions();
        foreach ($customOptions as $key => $value) {
            if (str_starts_with($key, 'generationConfig.')) {
                $subKey = (string) substr($key, strlen('generationConfig.'));
                if (isset($generationConfig[$subKey])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The custom generationConfig option "%s" conflicts with an existing parameter.',
                            $subKey
                        )
                    );
                }
                $generationConfig[$subKey] = $value;
                continue;
            }

            if (isset($params[$key])) {
                throw new InvalidArgumentException(
                    sprintf('The custom option "%s" conflicts with an existing parameter.', $key)
                );
            }
            $params[$key] = $value;
        }

        $params['generationConfig'] = $generationConfig;

        return $params;
    }

    /**
     * Extracts the plain text to synthesize from the prompt.
     *
     * @since n.e.x.t
     *
     * @param list<Message> $prompt The prompt messages.
     * @return string The text to convert to speech.
     * @throws InvalidArgumentException If no text is found in the prompt.
     */
    protected function preparePromptText(array $prompt): string
    {
        $text = '';
        foreach ($prompt as $message) {
            foreach ($message->getParts() as $part) {
                $partText = $part->getText();
                if ($partText !== null) {
                    $text .= ('' === $text ? '' : "\n") . $partText;
                }
            }
        }

        if ('' === $text) {
            throw new InvalidArgumentException(
                'The prompt must contain text to convert to speech.'
            );
        }

        return $text;
    }

    /**
     * Resolves the voice to use, falling back to the default.
     *
     * @since n.e.x.t
     *
     * @return string The prebuilt voice name.
     */
    protected function prepareVoice(): string
    {
        $voice = $this->getConfig()->getOutputSpeechVoice();

        return ($voice === null || '' === $voice) ? self::DEFAULT_VOICE : $voice;
    }

    /**
     * Parses the response, wrapping the returned PCM audio into a WAV file.
     *
     * @since n.e.x.t
     *
     * @param Response $response The HTTP response.
     * @return GenerativeAiResult The generative AI result containing the audio file.
     * @throws ResponseException If the response is missing the expected audio data.
     */
    protected function parseResponseToGenerativeAiResult(Response $response): GenerativeAiResult
    {
        /** @var ResponseData|null $data */
        $data = $response->getData();

        $inlineData = $this->extractInlineAudioData($data);

        $pcm = base64_decode($inlineData['data'], true);
        if ($pcm === false) {
            throw ResponseException::fromInvalidData(
                $this->providerMetadata()->getName(),
                'candidates[0].content.parts[0].inlineData.data',
                'The audio data could not be base64-decoded.'
            );
        }

        $sampleRate = $this->parseSampleRate($inlineData['mimeType']);
        $wav        = $this->wrapPcmInWav($pcm, $sampleRate);

        $audioFile = new File(base64_encode($wav), 'audio/wav');

        $message   = new Message(MessageRoleEnum::model(), [new MessagePart($audioFile)]);
        $candidate = new Candidate($message, FinishReasonEnum::stop());

        $tokenUsage = $this->parseTokenUsage($data);

        return new GenerativeAiResult(
            $this->generateResultId(),
            [$candidate],
            $tokenUsage,
            $this->providerMetadata(),
            $this->metadata()
        );
    }

    /**
     * Extracts the inline audio data (base64 + MIME) from the response.
     *
     * @since n.e.x.t
     *
     * @param ResponseData|null $data The decoded response data.
     * @return InlineAudioData The inline audio data.
     * @throws ResponseException If the audio part is missing or malformed.
     */
    protected function extractInlineAudioData(?array $data): array
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            throw ResponseException::fromMissingData(
                $this->providerMetadata()->getName(),
                'candidates[0].content.parts'
            );
        }

        foreach ($parts as $part) {
            if (
                is_array($part) &&
                isset($part['inlineData']['data']) &&
                is_string($part['inlineData']['data'])
            ) {
                $mimeType = isset($part['inlineData']['mimeType']) && is_string($part['inlineData']['mimeType'])
                    ? $part['inlineData']['mimeType']
                    : 'audio/L16;codec=pcm;rate=' . self::DEFAULT_SAMPLE_RATE;

                return [
                    'data'     => $part['inlineData']['data'],
                    'mimeType' => $mimeType,
                ];
            }
        }

        throw ResponseException::fromMissingData(
            $this->providerMetadata()->getName(),
            'candidates[0].content.parts[].inlineData'
        );
    }

    /**
     * Parses the PCM sample rate from a Gemini audio MIME type.
     *
     * Example input: "audio/L16;codec=pcm;rate=24000".
     *
     * @since n.e.x.t
     *
     * @param string $mimeType The Gemini inline audio MIME type.
     * @return int The sample rate in Hz.
     */
    protected function parseSampleRate(string $mimeType): int
    {
        if (preg_match('/rate=(\d+)/', $mimeType, $matches)) {
            return (int) $matches[1];
        }

        return self::DEFAULT_SAMPLE_RATE;
    }

    /**
     * Wraps raw signed 16-bit little-endian PCM data in a WAV container.
     *
     * @since n.e.x.t
     *
     * @param string $pcm           The raw PCM audio bytes.
     * @param int    $sampleRate    The sample rate in Hz.
     * @param int    $channels      The number of channels. Default 1 (mono).
     * @param int    $bitsPerSample The bit depth. Default 16.
     * @return string The WAV-formatted audio bytes.
     */
    protected function wrapPcmInWav(
        string $pcm,
        int $sampleRate,
        int $channels = 1,
        int $bitsPerSample = 16
    ): string {
        $blockAlign = (int) ($channels * ($bitsPerSample / 8));
        $byteRate   = $sampleRate * $blockAlign;
        $dataLength = strlen($pcm);

        $header = 'RIFF'
            . pack('V', 36 + $dataLength)
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)               // Subchunk1Size for PCM.
            . pack('v', 1)                // AudioFormat = PCM.
            . pack('v', $channels)
            . pack('V', $sampleRate)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', $bitsPerSample)
            . 'data'
            . pack('V', $dataLength);

        return $header . $pcm;
    }

    /**
     * Parses token usage from the response, defaulting to zeros.
     *
     * @since n.e.x.t
     *
     * @param ResponseData|null $data The decoded response data.
     * @return TokenUsage The token usage.
     */
    protected function parseTokenUsage(?array $data): TokenUsage
    {
        $usage = $data['usageMetadata'] ?? null;
        if (!is_array($usage)) {
            return new TokenUsage(0, 0, 0);
        }

        return new TokenUsage(
            isset($usage['promptTokenCount']) && is_int($usage['promptTokenCount']) ? $usage['promptTokenCount'] : 0,
            isset($usage['candidatesTokenCount']) && is_int($usage['candidatesTokenCount'])
                ? $usage['candidatesTokenCount']
                : 0,
            isset($usage['totalTokenCount']) && is_int($usage['totalTokenCount']) ? $usage['totalTokenCount'] : 0
        );
    }

    /**
     * Generates a result identifier (the endpoint does not return one for audio).
     *
     * @since n.e.x.t
     *
     * @return string The result identifier.
     */
    protected function generateResultId(): string
    {
        return uniqid('google-tts-', true);
    }
}
