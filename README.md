# README

(WIP) Enables installing WP plugins via Composer 2 (or Composer 1) inside [`johnpbloch/wordpress`](https://github.com/johnpbloch/wordpress). Replaces `drupal-composer/preserve-paths`.

## Installation / Usage

1. `mkdir your-project && cd your-project`
2. `composer init`
3. `composer require agilo/wp-package-installer`
4. `composer require johnpbloch/wordpress`

## Contributing

### Formatting code

1. Run `composer install --working-dir=tools/php-cs-fixer`
2. Run `./tools/php-cs-fixer/vendor/bin/php-cs-fixer fix`

### Running tests

1. Run `composer install --working-dir=tools/phpunit`
2. Run `./tools/phpunit/vendor/bin/phpunit`
