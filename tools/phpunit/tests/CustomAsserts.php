<?php

declare(strict_types=1);

namespace Agilo\WpPackageInstaller\Tests;

use FilesystemIterator;
use PHPUnit\Framework\Assert;

class CustomAsserts
{
    public static function assertFileDoesNotExist(string $filename, string $message = ''): void
    {
        method_exists(Assert::class, 'assertFileDoesNotExist')
            ? Assert::assertFileDoesNotExist($filename, $message) // phpunit 9+
            : Assert::assertFileNotExists($filename, $message); // older phpunit
    }

    public static function assertDirectoryDoesNotExist(string $directory, string $message = ''): void
    {
        method_exists(Assert::class, 'assertDirectoryDoesNotExist')
            ? Assert::assertDirectoryDoesNotExist($directory, $message) // phpunit 9+
            : Assert::assertDirectoryNotExists($directory, $message); // older phpunit
    }

    public static function assertDirectoryNotEmpty(string $directory): void
    {
        $iterator = new FilesystemIterator($directory);
        Assert::assertTrue($iterator->valid());
    }

    /**
     * Asserts that the given directory is a valid WordPress directory structure with
     * common WordPress files and folders present after clean johnpbloch/wordpress installation.
     */
    public static function assertWpDirectoryStructure(string $directory): void
    {
        Assert::assertDirectoryExists($directory);
        Assert::assertDirectoryExists($directory.'/wp-admin');
        Assert::assertDirectoryExists($directory.'/wp-content');
        // Assert::assertDirectoryExists($directory.'/wp-content/mu-plugins');
        Assert::assertDirectoryExists($directory.'/wp-content/plugins');
        Assert::assertFileExists($directory.'/wp-content/plugins/index.php');
        Assert::assertDirectoryExists($directory.'/wp-content/themes');
        Assert::assertFileExists($directory.'/wp-content/themes/index.php');
        Assert::assertFileExists($directory.'/wp-content/index.php');
        Assert::assertDirectoryExists($directory.'/wp-includes');
        Assert::assertFileExists($directory.'/index.php');
        // Assert::assertFileExists($directory.'/wp-config.php');
        Assert::assertFileExists($directory.'/wp-load.php');
    }
}
