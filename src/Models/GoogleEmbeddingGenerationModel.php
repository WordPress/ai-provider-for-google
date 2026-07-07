<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
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
 * @phpstan-type ResponseData array{embeddings?: list<EmbeddingData>}
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
     * @param list<list<Message>> $prompts The prompts to generate embeddings for, one message list per prompt.
     * @return EmbeddingResult The embedding result.
     */
    public function generateEmbeddingResult(array $prompts): EmbeddingResult
    {
        $httpTransporter = $this->getHttpTransporter();

        $params = $this->prepareGenerateEmbeddingsParams($prompts);

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
     * Prepares the given prompts and the model configuration into parameters for the API request.
     *
     * @since n.e.x.t
     *
     * @param list<list<Message>> $prompts The prompts to generate embeddings for, one message list per prompt.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareGenerateEmbeddingsParams(array $prompts): array
    {
        if (!array_is_list($prompts)) {
            throw new InvalidArgumentException('Embedding input must be provided as a list of prompts.');
        }

        if (empty($prompts)) {
            throw new InvalidArgumentException('The API requires at least one prompt.');
        }

        $modelName = 'models/' . $this->metadata()->getId();
        $dimensions = $this->getConfig()->getDimensions();
        $customOptions = $this->getConfig()->getCustomOptions();

        $requests = [];
        foreach ($prompts as $messages) {
            $requestEntry = [
                'model' => $modelName,
                'content' => [
                    'parts' => [
                        ['text' => $this->preparePromptInput($messages)],
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
     * Prepares a single prompt (a list of messages) into one embeddings input string.
     *
     * @since n.e.x.t
     *
     * @param list<Message> $messages The messages that make up one embedding input.
     * @return string The prompt text.
     */
    protected function preparePromptInput(array $messages): string
    {
        if (!array_is_list($messages) || empty($messages)) {
            throw new InvalidArgumentException('Each embedding prompt must be a non-empty list of messages.');
        }

        $textParts = [];
        foreach ($messages as $message) {
            $textParts[] = $this->prepareMessageInput($message);
        }

        return implode("\n", $textParts);
    }

    /**
     * Prepares a single message for the embeddings input parameter.
     *
     * @since n.e.x.t
     *
     * @param Message $message The message for one embedding input.
     * @return string The prompt text.
     */
    protected function prepareMessageInput(Message $message): string
    {
        $textParts = [];
        foreach ($message->getParts() as $part) {
            $text = $part->getText();
            if ($text !== null) {
                $textParts[] = $text;
            }
        }

        if (empty($textParts)) {
            throw new InvalidArgumentException('The API requires text content to generate embeddings.');
        }

        return implode("\n", $textParts);
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

        // The Google API does not return usage metadata for embeddings.
        $tokenUsage = new TokenUsage(0, 0, 0);

        $additionalData = $responseData;
        unset($additionalData['embeddings']);

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
