# README

(WIP) Enables installing WP plugins via Composer 2 (or Composer 1) inside [`johnpbloch/wordpress`](https://github.com/johnpbloch/wordpress). Replaces `drupal-composer/preserve-paths`.

- PHP 7.2 - 8.3 is supported
- Composer 1 & 2 is supported

## Installation / Usage

1. `mkdir your-project && cd your-project`
2. `composer init`
3. Add the following to your `composer.json` extra section:

```json
{
  "extra": {
    "wordpress-install-dir": "public",
    "installer-paths": {
      "vendor-wp/wp-content/plugins/{$name}/": ["type:wordpress-plugin"],
      "vendor-wp/wp-content/themes/{$name}/": ["type:wordpress-theme"],
      "vendor-wp/wp-content/mu-plugins/{$name}/": ["type:wordpress-muplugin"],
      "vendor-wp/wp-content/{$name}/": ["type:wordpress-dropin"]
    },
    "agilo-wp-package-installer": {
      "sources": {
        "third-party": {
          "src": "vendor-wp",
          "dest": "public",
          "mode": "symlink"
        }
      }
    }
  }
}
```

4. `composer require agilo/wp-package-installer`
5. `composer require johnpbloch/wordpress`

## Contributing

### Formatting code

1. Run `composer install --working-dir=tools/php-cs-fixer`
2. Run `./tools/php-cs-fixer/vendor/bin/php-cs-fixer fix`

### Running tests

1. Run `composer install --working-dir=tools/phpunit`
2. Run `./tools/phpunit/vendor/bin/phpunit`
