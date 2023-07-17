<?php

namespace Agilo\WpPackageInstaller\Tests;

define('PROJECT_ROOT_DIR', realpath(__DIR__.'/../../..'));
define('TEST_PROJECT_ROOT_DIR', sys_get_temp_dir().'/wp-package-installer-test-project');
define('TESTS_ROOT_DIR', realpath(__DIR__.'/..'));

var_dump('TEST_PROJECT_ROOT_DIR', TEST_PROJECT_ROOT_DIR);

require_once __DIR__ . '/../vendor/autoload.php';
