<?php

namespace Agilo\WpPackageInstaller;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use FilesystemIterator;
use InvalidArgumentException;
use Throwable;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Relative path mapping of where the packages should be copied from and to. Example:
     * ```php
     * private array $locations = [
     *     'bin/wp-content/plugins/'    => 'public/wp-content/plugins/',
     *     'bin/wp-content/themes/'     => 'public/wp-content/themes/',
     *     'bin/wp-content/mu-plugins/' => 'public/wp-content/mu-plugins/',
     *     'bin/wp-content/dropins/'    => 'public/wp-content/',
     * ];
     * ```
     * 
     * @var array<string, string>
     */
    private array $locations = [];

    private bool $symlinkedBuild = true;

    private Composer $composer;

    public function activate(Composer $composer, IOInterface $io)
    {
        if (function_exists('xdebug_break')) {
            // var_dump(\xdebug_break());
        }
        $this->composer = $composer;
        $extra = $this->composer->getPackage()->getExtra();

        if (
            isset($extra['agilo-wp-package-installer-symlinked-build'])
            && is_bool($extra['agilo-wp-package-installer-symlinked-build'])
        ) {
            $this->symlinkedBuild = $extra['agilo-wp-package-installer-symlinked-build'];
        }
        // Allow overriding via environment variable.
        if (getenv('AGILO_WP_PACKAGE_INSTALLER_SYMLINKED_BUILD') === '0') {
            $this->symlinkedBuild = false;
        } elseif (getenv('AGILO_WP_PACKAGE_INSTALLER_SYMLINKED_BUILD') === '1') {
            $this->symlinkedBuild = true;
        }

        $this->mapLocations($extra);
    }

    private function mapLocations(array $extra): void
    {
        if (!isset($extra['agilo-wp-package-installer-paths'])) {
            throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-paths is missing.');
        }

        if (!is_array($extra['agilo-wp-package-installer-paths'])) {
            throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-paths is not an array.');
        }

        $fs = new Filesystem;
        foreach ($extra['agilo-wp-package-installer-paths'] as $from_path => $to_path) {
            if (!is_string($from_path)) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-paths key is not a string.');
            }
            $from_path = $fs->trimTrailingSlash($from_path);
            if ($from_path === '') {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-paths key is empty.');
            }
            if (!is_string($to_path)) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-paths value is not a string.');
            }
            $to_path = $fs->trimTrailingSlash($to_path);
            if ($to_path === '') {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-paths value is empty.');
            }
            $fs->ensureDirectoryExists($to_path);
            $this->locations[$from_path] = $to_path;
        }
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        echo 'Plugin::deactivate' . PHP_EOL;
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        echo 'Plugin::uninstall' . PHP_EOL;
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostUpdateCmd',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdateCmd',
            PackageEvents::POST_PACKAGE_UNINSTALL => 'onPostPackageUninstall',
        ];
    }

    public function onPostUpdateCmd(Event $event): void
    {
        $fs = new Filesystem;
        foreach ($this->locations as $from_path => $to_path) {
            try {
                $it = new FilesystemIterator($from_path, FilesystemIterator::SKIP_DOTS);
                foreach ($it as $fileinfo) {
                    // var_dump(\xdebug_break());
                    $target = realpath($to_path).'/'.$fileinfo->getFilename();
                    self::remove($fs, $target);
                    if ($this->symlinkedBuild) {
                        $fs->relativeSymlink($fileinfo->getRealPath(), $target);
                    } else {
                        $fs->copy($fileinfo->getRealPath(), $target);
                    }
                }
            } catch (Throwable $t) {
                // FilesystemIterator can throw
            }
        }
    }

    public function onPostPackageUninstall(PackageEvent $event): void
    {
        if (function_exists('xdebug_break')) {
            var_dump(\xdebug_break());
        }

        $operation = $event->getOperation();
        if (!($operation instanceof UninstallOperation)) {
            return;
        }

        $package = $operation->getPackage();
        $installPath = $this->composer->getInstallationManager()->getInstallPath($package);

        if ($installPath) {
            foreach ($this->locations as $from_path => $to_path) {
                if (realpath($from_path) === dirname($installPath)) {
                    $fs = new Filesystem;
                    $pathToRemove = $to_path.'/'.basename($installPath);
                    self::remove($fs, $pathToRemove);
                    return;
                }
            }
        }
    }

    private static function remove(Filesystem $fs, string $path): bool
    {
        if (is_link($path)) {
            return $fs->unlink($path);
        }
        return $fs->remove($path);
    }
}
