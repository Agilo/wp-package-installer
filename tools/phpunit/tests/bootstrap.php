<?php

namespace Agilo\WpPackageInstaller\Tests;

define('PROJECT_ROOT_DIR', realpath(__DIR__.'/../../..'));
define('TEST_PROJECT_ROOT_DIR', sys_get_temp_dir().'/agilo-wp-package-installer-test-project');
define('TESTS_ROOT_DIR', realpath(__DIR__.'/..'));

echo 'Using ['.TEST_PROJECT_ROOT_DIR.'] for TEST_PROJECT_ROOT_DIR.'.PHP_EOL;

require_once __DIR__ . '/../vendor/autoload.php';
