<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\GoogleAiProvider\Authentication\GoogleApiKeyRequestAuthentication;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

/**
 * Class for a Google embedding generation model using the batchEmbedContents endpoint.
 *
 * @since n.e.x.t
 *
 * @phpstan-type EmbeddingData array{values?: list<float|int>}
 * @phpstan-type UsageMetadata array{promptTokenCount?: int}
 * @phpstan-type ResponseData array{embeddings?: list<EmbeddingData>, usageMetadata?: UsageMetadata}
 */
class GoogleEmbeddingGenerationModel extends AbstractApiBasedModel implements EmbeddingGenerationModelInterface
{
    /**
     * {@inheritDoc}
     *
     * @since n.e.x.t
     */
    public function getRequestAuthentication(): RequestAuthenticationInterface
    {
        /*
         * Since we're calling the Google API here, we need to use the Google specific
         * API key authentication class.
         */
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
     *
     * @param list<MessagePart> $input The inputs to generate embeddings for, one embedding per input.
     * @return EmbeddingResult The embedding result.
     */
    public function generateEmbeddingResult(array $input): EmbeddingResult
    {
        $httpTransporter = $this->getHttpTransporter();

        $params = $this->prepareGenerateEmbeddingsParams($input);

        $request = new Request(
            HttpMethodEnum::POST(),
            GoogleProvider::url("models/{$this->metadata()->getId()}:batchEmbedContents"),
            ['Content-Type' => 'application/json'],
            $params,
            $this->getRequestOptions()
        );

        // Add authentication credentials to the request.
        $request = $this->getRequestAuthentication()->authenticateRequest($request);

        // Send and process the request.
        $response = $httpTransporter->send($request);
        ResponseUtil::throwIfNotSuccessful($response);
        return $this->parseResponseToEmbeddingResult($response);
    }

    /**
     * Prepares the given inputs and the model configuration into parameters for the API request.
     *
     * @since n.e.x.t
     *
     * @param list<MessagePart> $input The inputs to generate embeddings for, one embedding per input.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareGenerateEmbeddingsParams(array $input): array
    {
        if (!array_is_list($input)) {
            throw new InvalidArgumentException('Embedding input must be provided as a list of message parts.');
        }

        if (empty($input)) {
            throw new InvalidArgumentException('The API requires at least one input.');
        }

        $modelName = 'models/' . $this->metadata()->getId();
        $dimensions = $this->getConfig()->getDimensions();
        $customOptions = $this->getConfig()->getCustomOptions();

        $requests = [];
        foreach ($input as $index => $part) {
            $requestEntry = [
                'model' => $modelName,
                'content' => [
                    'parts' => [
                        ['text' => $this->preparePartInput($part, $index)],
                    ],
                ],
            ];

            if ($dimensions !== null) {
                $requestEntry['outputDimensionality'] = $dimensions;
            }

            foreach ($customOptions as $key => $value) {
                if (isset($requestEntry[$key])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'The custom option "%s" conflicts with an existing parameter.',
                            $key
                        )
                    );
                }
                $requestEntry[$key] = $value;
            }

            $requests[] = $requestEntry;
        }

        return ['requests' => $requests];
    }

    /**
     * Prepares a single input part into one embeddings input string.
     *
     * @since n.e.x.t
     *
     * @param mixed $part  The message part that makes up one embedding input.
     * @param int   $index The index of the part within the input list, used for error messages.
     * @return string The embedding input text.
     * @throws InvalidArgumentException If the part is not a non-empty text message part.
     */
    protected function preparePartInput($part, int $index): string
    {
        if (!$part instanceof MessagePart) {
            throw new InvalidArgumentException(
                sprintf('Embedding input at index %d must be a MessagePart.', $index)
            );
        }

        if (!$part->getType()->isText()) {
            throw new InvalidArgumentException(
                sprintf('Google embedding input at index %d must be a text part.', $index)
            );
        }

        $text = $part->getText();
        if ($text === null || trim($text) === '') {
            throw new InvalidArgumentException(
                sprintf('Google embedding input at index %d must contain non-empty text.', $index)
            );
        }

        return $text;
    }

    /**
     * Parses the response from the API endpoint to an embedding result.
     *
     * @since n.e.x.t
     *
     * @param Response $response The response from the API endpoint.
     * @return EmbeddingResult The parsed embedding result.
     */
    protected function parseResponseToEmbeddingResult(Response $response): EmbeddingResult
    {
        /** @var ResponseData $responseData */
        $responseData = $response->getData();

        if (!isset($responseData['embeddings']) || !$responseData['embeddings']) {
            throw ResponseException::fromMissingData($this->providerMetadata()->getName(), 'embeddings');
        }
        if (!is_array($responseData['embeddings']) || !array_is_list($responseData['embeddings'])) {
            throw ResponseException::fromInvalidData(
                $this->providerMetadata()->getName(),
                'embeddings',
                'The value must be an indexed array.'
            );
        }

        $embeddings = [];
        foreach ($responseData['embeddings'] as $index => $embeddingData) {
            if (
                !is_array($embeddingData) ||
                !isset($embeddingData['values']) ||
                !is_array($embeddingData['values'])
            ) {
                throw ResponseException::fromInvalidData(
                    $this->providerMetadata()->getName(),
                    "embeddings[{$index}].values",
                    'The value must be an embedding vector.'
                );
            }
            $embeddings[] = new Embedding(
                $embeddingData['values'],
                count($embeddingData['values'])
            );
        }

        /*
         * Newer models return usage metadata for embeddings, while older models do not.
         */
        $promptTokens = 0;
        if (
            isset($responseData['usageMetadata']['promptTokenCount']) &&
            is_int($responseData['usageMetadata']['promptTokenCount'])
        ) {
            $promptTokens = $responseData['usageMetadata']['promptTokenCount'];
        }
        $tokenUsage = new TokenUsage($promptTokens, 0, $promptTokens);

        $additionalData = $responseData;
        unset($additionalData['embeddings'], $additionalData['usageMetadata']);

        return new EmbeddingResult(
            '',
            $embeddings,
            count($embeddings[0]->getValues()),
            $tokenUsage,
            $this->providerMetadata(),
            $this->metadata(),
            $additionalData
        );
    }
}
