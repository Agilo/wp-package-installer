
## Using agilo/wp-package-installer

1. `mkdir your-project && cd your-project`
2. `composer init`
3. `composer require agilo/wp-package-installer`
4. `composer require johnpbloch/wordpress`

## Running tests

1. Run `composer install --working-dir=tools/phpunit`
2. Run `./tools/phpunit/vendor/bin/phpunit tools/phpunit/tests/TestBuild.php`
