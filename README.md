# AI Provider for Google

A Google AI (Gemini) provider for the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK. Works as both a Composer package and a WordPress plugin.

## Requirements

- PHP 7.4 or higher
- When using with WordPress, requires WordPress 7.0 or higher
    - If using an older WordPress release, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed

## Installation

### As a Composer Package

```bash
composer require wordpress/ai-provider-for-google
```

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/ai-provider-for-google/`
3. Ensure the PHP AI Client plugin is installed and activated
4. Activate the plugin through the WordPress admin

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook. Simply ensure both plugins are active and configure your API key:

```php
// Set your Google API key (or use the GOOGLE_API_KEY environment variable)
putenv('GOOGLE_API_KEY=your-api-key');

// Use the provider
$result = AiClient::prompt('Hello, world!')
    ->usingProvider('google')
    ->generateTextResult();
```

### As a Standalone Package

```php
use WordPress\AiClient\AiClient;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

// Register the provider
$registry = AiClient::defaultRegistry();
$registry->registerProvider(GoogleProvider::class);

// Set your API key
putenv('GOOGLE_API_KEY=your-api-key');

// Generate text
$result = AiClient::prompt('Explain quantum computing')
    ->usingProvider('google')
    ->generateTextResult();

echo $result->toText();
```

### Generating Embeddings

```php
// Generate a single embedding.
$embedding = AiClient::prompt('PHP powers a large part of the web.')
    ->usingProvider('google')
    ->generateEmbedding();

$values = $embedding->getValues();

// Generate embeddings for multiple inputs in a single batch.
$embeddings = AiClient::prompt()
    ->usingProvider('google')
    ->generateEmbeddings([
        'PHP powers a large part of the web.',
        'WordPress makes publishing accessible.',
    ]);

// Request a specific number of output dimensions (for models that support it).
$embedding = AiClient::prompt('PHP powers a large part of the web.')
    ->usingProvider('google')
    ->usingDimensions(512)
    ->generateEmbedding();
```

## Supported Models

Available models are dynamically discovered from the Google AI API. This includes Gemini models for text generation (with multimodal input support), Imagen models for image generation, and embedding models (such as `gemini-embedding-001` and `text-embedding-004`) for embedding generation. See the [Google AI documentation](https://ai.google.dev/gemini-api/docs/models) for the full list of available models.

## Configuration

The provider uses the `GOOGLE_API_KEY` environment variable for authentication. You can set this in your environment or via PHP:

```php
putenv('GOOGLE_API_KEY=your-api-key');
```

## License

GPL-2.0-or-later
