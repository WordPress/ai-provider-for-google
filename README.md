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

Embedding generation always requires you to name a model. Vectors are only comparable to other
vectors produced by the same model, so a stored corpus is permanently tied to the model that created
it — letting the library pick could silently invalidate embeddings you already saved.

```php
use WordPress\AiClient\AiClient;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

// Generate a single embedding.
$embedding = AiClient::input('PHP powers a large part of the web.')
    ->usingModel(GoogleProvider::model('gemini-embedding-001'))
    ->generateEmbedding();

$values = $embedding->getValues();

// Generate embeddings for multiple inputs in a single batch.
$embeddings = AiClient::input([
        'PHP powers a large part of the web.',
        'WordPress makes publishing accessible.',
    ])
    ->usingModel(GoogleProvider::model('gemini-embedding-001'))
    ->generateEmbeddings();

// Request a specific number of output dimensions.
$embedding = AiClient::input('PHP powers a large part of the web.')
    ->usingModel(GoogleProvider::model('gemini-embedding-001'))
    ->usingDimensions(512)
    ->generateEmbedding();
```

#### Multimodal Embeddings

The `gemini-embedding-2` model embeds images, audio, video and PDF documents in addition to text,
mapping every modality into the same vector space. That means a text embedding can be compared
directly against an image embedding, which is what makes cross-modal search possible.

```php
// Embed an image. Remote URLs, such as WordPress attachment URLs, are passed through directly.
$embedding = AiClient::input(new File(wp_get_attachment_url($attachment_id)))
    ->usingModel(GoogleProvider::model('gemini-embedding-2'))
    ->generateEmbedding();

// Text and media can be mixed in a single batch. One embedding is returned per input, in order.
$embeddings = AiClient::input([
        'A golden retriever running on a beach.',
        new File('https://example.com/dog-on-beach.jpg'),
        new File('https://example.com/clip.mp4'),
    ])
    ->usingModel(GoogleProvider::model('gemini-embedding-2'))
    ->generateEmbeddings();
```

Input modalities are advertised per model, so the model you name is matched against the inputs you
provide. Passing an image to a text-only model such as `gemini-embedding-001` is rejected. On PHP AI
Client versions that validate the named model, this happens locally before any request is sent, with
a message naming the unsupported option:

```
Model "gemini-embedding-001" from provider "google" cannot fulfill this embedding request.
Unsupported options: inputModalities ([image]).
```

Supported input per model:

| Model | Text | Image | Audio | Video | PDF |
| --- | --- | --- | --- | --- | --- |
| `gemini-embedding-2` | Yes | Yes | Yes | Yes | Yes |
| `gemini-embedding-001` | Yes | No | No | No | No |

A few Google-specific notes:

* Each input produces its own embedding. Combining several parts into a single fused vector is not
  currently expressible through the PHP AI Client.
* `taskType` is honored by `gemini-embedding-001` but ignored by `gemini-embedding-2`; for the latter,
  describe the task in the input text instead.
* `gemini-embedding-2` normalizes truncated dimension vectors automatically, while
  `gemini-embedding-001` requires callers to renormalize them.
* `gemini-embedding-2` reports token usage on the embedding result; `gemini-embedding-001` does not,
  and reports zero.

## Supported Models

Available models are dynamically discovered from the Google AI API. This includes Gemini models for text generation (with multimodal input support), Imagen models for image generation, and embedding models (such as `gemini-embedding-2` for multimodal embeddings and `gemini-embedding-001` for text-only embeddings). See the [Google AI documentation](https://ai.google.dev/gemini-api/docs/models) for the full list of available models.

## Configuration

The provider uses the `GOOGLE_API_KEY` environment variable for authentication. You can set this in your environment or via PHP:

```php
putenv('GOOGLE_API_KEY=your-api-key');
```

## License

GPL-2.0-or-later
