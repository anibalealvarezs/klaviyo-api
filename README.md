# Klaviyo API

## Instructions

Require the package in the `composer.json` file of your project, and map the package in the `repositories` section.
You must also map the `api-skeleton` package.

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

Note: In order to use the package from GitLab, you need to have a valid SSH key configured in your GitLab account.

## Methods

- ### getMetrics: *Array*

  `Gets the file data.`

  <details>
    <summary><strong>Parameters</strong></summary>

    - Required

        - `fileId`: *Integer*  
          ID of the file to be retrieved.
  </details><br>
