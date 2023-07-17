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
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var bool */
    private $debug = false;

    /** @var bool */
    private $symlinkedBuild = true;

    /**
     * Relative path to the source directory without trailing slash.
     * 
     * @var string
     */
    private $firstPartysrc = 'src';

    private $thirdPartySrc = '.';

    /**
     * Relative path to the destination directory without trailing slash.
     * 
     * @var string
     */
    private $dest = 'wordpress';

    /**
     * Absolute path to the source directory without trailing slash.
     * 
     * @var string
     */
    private $firstPartySrcDir;

    private $thridPartySrcDir;

    /**
     * Absolute path to the destination directory without trailing slash.
     * 
     * @var string
     */
    private $destDir;

    /**
     * First party package relative paths.
     */
    private $firstPartySrcPaths = [
        'wp-config.php',
        'wp-content/plugins/*',
        'wp-content/themes/*',
        'wp-content/mu-plugins/*',
        'wp-content/languages/*',
        'wp-content/*.php',
    ];

    private $thirdPartySrcPaths = [
        'wp-content/plugins/*',
        'wp-content/themes/*',
        'wp-content/mu-plugins/*',
        'wp-content/languages/*',
        'wp-content/*.php',
    ];

    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    /** @var Filesystem */
    private $fs;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        if (getenv('AGILO_WP_PACKAGE_INSTALLER_DEBUG') === '1') {
            $this->debug = true;
        }

        if ($this->debug) {
            $this->io->write(__CLASS__.'::activate');
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
        }

        $this->fs = new Filesystem();
        $extra = $this->composer->getPackage()->getExtra();
        $settings = $extra['agilo-wp-package-installer'] ?? [];

        if (!is_array($settings)) {
            throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer value is not an array.');
        }

        if (isset($settings['symlinked-build'])) {
            if (!is_bool($settings['symlinked-build'])) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::symlinked-build value is not a boolean.');
            }
            $this->symlinkedBuild = $settings['symlinked-build'];
        }

        // Allow overriding via environment variable.
        if (getenv('AGILO_WP_PACKAGE_INSTALLER_SYMLINKED_BUILD') === '0') {
            $this->symlinkedBuild = false;
        } elseif (getenv('AGILO_WP_PACKAGE_INSTALLER_SYMLINKED_BUILD') === '1') {
            $this->symlinkedBuild = true;
        }

        if (isset($settings['first-party-src'])) {
            if (!is_string($settings['first-party-src'])) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::first-party-src value is not a string.');
            }
            if ($settings['first-party-src'] === '') {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::first-party-src value is empty.');
            }
            $this->firstPartysrc = $settings['first-party-src'];
        }

        if (isset($settings['third-party-src'])) {
            if (!is_string($settings['third-party-src'])) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::third-party-src value is not a string.');
            }
            if ($settings['third-party-src'] === '') {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::third-party-src value is empty.');
            }
            $this->thirdPartySrc = $settings['third-party-src'];
        }

        if (isset($settings['dest'])) {
            if (!is_string($settings['dest'])) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::dest value is not a string.');
            }
            if ($settings['dest'] === '') {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer::dest value is empty.');
            }
            $this->dest = $settings['dest'];
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('getcwd() failed.');
        }

        // TODO: Allow setting $this->firstPartysrc, $this->thirdPartySrc and $this->dest from config.

        $this->firstPartySrcDir = $this->fs->normalizePath($cwd.'/'.$this->firstPartysrc);
        $this->thridPartySrcDir = $this->fs->normalizePath($cwd.'/'.$this->thirdPartySrc);
        $this->destDir = $this->fs->normalizePath($cwd.'/'.$this->dest);

        $this->validateFirstPartyPaths();
    }

    private function validateFirstPartyPaths(): void
    {
        foreach ($this->firstPartySrcPaths as $path) {
            if (!is_string($path)) {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-first-party-paths value is not a string.');
            }
            if ($path === '') {
                throw new InvalidArgumentException('composer.json::extra::agilo-wp-package-installer-first-party-paths value is empty.');
            }
        }
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        if ($this->debug) {
            $this->io->write(__CLASS__.'::deactivated');
        }
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        if ($this->debug) {
            $this->io->write(__CLASS__.'::uninstall');
        }
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
        if ($this->debug) {
            $this->io->write(__CLASS__.'::onPostUpdateCmd');
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
        }

        /**
         * Handle thrid party packages.
         */

        if (is_dir($this->thridPartySrcDir)) {
            $finder = new Finder();
            $finder->in($this->thridPartySrcDir);

            foreach ($this->thirdPartySrcPaths as $path) {
                if (substr($path, 0, 1) === '!') {
                    $finder->notPath(Glob::toRegex(substr($path, 1)));
                } else {
                    $finder->path(Glob::toRegex($path));
                }
            }

            foreach($finder as $fileinfo) {
                $srcPath = $fileinfo->getRealPath();
                if ($srcPath === false) {
                    throw new RuntimeException('getRealPath() failed.');
                }

                $destPath = str_replace($this->thridPartySrcDir, $this->destDir, $srcPath);

                $this->fs->ensureDirectoryExists(dirname($destPath));

                if ($this->symlinkedBuild) {
                    $this->fs->relativeSymlink($srcPath, $destPath);
                    echo 'Symlinked '.$srcPath.' to '.$destPath.PHP_EOL;
                } else {
                    $this->fs->copy($srcPath, $destPath);
                    echo 'Copied '.$srcPath.' to '.$destPath.PHP_EOL;
                }
            }
        }

        /**
         * Handle first party packages.
         */

        if (is_dir($this->firstPartySrcDir)) {
            $finder = new Finder();
            $finder->in($this->firstPartySrcDir);

            foreach ($this->firstPartySrcPaths as $path) {
                if (substr($path, 0, 1) === '!') {
                    $finder->notPath(Glob::toRegex(substr($path, 1)));
                } else {
                    $finder->path(Glob::toRegex($path));
                }
            }

            foreach($finder as $fileinfo) {
                $srcPath = $fileinfo->getRealPath();
                if ($srcPath === false) {
                    throw new RuntimeException('getRealPath() failed.');
                }

                $destPath = str_replace($this->firstPartySrcDir, $this->destDir, $srcPath);

                $this->fs->ensureDirectoryExists(dirname($destPath));

                if ($this->symlinkedBuild) {
                    $this->fs->relativeSymlink($srcPath, $destPath);
                    $this->io->write('Symlinked '.$srcPath.' to '.$destPath);
                } else {
                    $this->fs->copy($srcPath, $destPath);
                    $this->io->write('Copied '.$srcPath.' to '.$destPath);
                }
            }
        }
    }

    public function onPostPackageUninstall(PackageEvent $event): void
    {
        if ($this->debug) {
            $this->io->write(__CLASS__.'::onPostPackageUninstall');
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
        }

        $operation = $event->getOperation();
        if (!($operation instanceof UninstallOperation)) {
            return;
        }

        $package = $operation->getPackage();
        $installPath = $this->composer->getInstallationManager()->getInstallPath($package);

        if ($installPath) {
            $destPath = str_replace($this->thridPartySrcDir, $this->destDir, $installPath);

            // strip trailing slashes
            $destPath = rtrim($destPath, '/\\');

            $this->remove($destPath);
            return;
        }
    }

    private function remove(string $path): bool
    {
        if (is_link($path)) {
            return $this->fs->unlink($path);
        }
        return $this->fs->remove($path);
    }
}
