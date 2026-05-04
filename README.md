# Klaviyo API

## Instructions

Require the package in the `composer.json` file of your project, and map the package in the `repositories` section.

```json
{
    "require": {
        "php": ">=8.1",
        "anibalealvarezs/klaviyo-api": "@dev"
    },
    "repositories": [
        {
          "type": "composer", "url": "https://satis.anibalalvarez.com/"
        }
    ]
}
```

## Methods

## Error Handling

- The SDK now uses a semantic classifier at `src/Support/KlaviyoErrorClassifier.php`.
- `KlaviyoApi` configures a callable detector with:

```php
$this->setRateLimitDetector([KlaviyoErrorClassifier::class, 'isRetryable']);
```

- Retry logic is intentionally conservative and focuses on throttling/rate-limit signals (`429`, `rate_limit`, `throttled`, `too many requests`).
- Non-throttling API errors continue to surface through normal exceptions (`ApiRequestException`, auth exceptions, etc.).

- ### getMetrics: *Array*

  `Gets the file data.`

  <details>
    <summary><strong>Parameters</strong></summary>

    - Required

        - `fileId`: *Integer*  
          ID of the file to be retrieved.
  </details><br>
