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

    private $thirdPartySrc = 'bin';

    /**
     * Relative path to the destination directory without trailing slash.
     * 
     * @var string
     */
    private $dest = 'public';

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

    public function activate(Composer $composer, IOInterface $io)
    {
        if (getenv('AGILO_WP_PACKAGE_INSTALLER_DEBUG') === '1') {
            $this->debug = true;
        }

        if ($this->debug) {
            echo __CLASS__.'::activate'.PHP_EOL;
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
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

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('getcwd() failed.');
        }

        // TODO: Allow setting $this->firstPartysrc, $this->thirdPartySrc and $this->dest from config.

        $this->firstPartySrcDir = $cwd.'/'.$this->firstPartysrc;
        $this->thridPartySrcDir = $cwd.'/'.$this->thirdPartySrc;
        $this->destDir = $cwd.'/'.$this->dest;

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
            echo __CLASS__.'::deactivated'.PHP_EOL;
        }
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        if ($this->debug) {
            echo __CLASS__.'::uninstall'.PHP_EOL;
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
            echo __CLASS__.'::onPostUpdateCmd'.PHP_EOL;
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
        }

        $fs = new Filesystem();

        /**
         * Handle thrid party packages.
         */

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

            $fs->ensureDirectoryExists(dirname($destPath));

            if ($this->symlinkedBuild) {
                $fs->relativeSymlink($srcPath, $destPath);
                echo 'Symlinked '.$srcPath.' to '.$destPath.PHP_EOL;
            } else {
                $fs->copy($srcPath, $destPath);
                echo 'Copied '.$srcPath.' to '.$destPath.PHP_EOL;
            }
        }

        /**
         * Handle first party packages.
         */

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

            $fs->ensureDirectoryExists(dirname($destPath));

            if ($this->symlinkedBuild) {
                $fs->relativeSymlink($srcPath, $destPath);
                echo 'Symlinked '.$srcPath.' to '.$destPath.PHP_EOL;
            } else {
                $fs->copy($srcPath, $destPath);
                echo 'Copied '.$srcPath.' to '.$destPath.PHP_EOL;
            }
        }
    }

    public function onPostPackageUninstall(PackageEvent $event): void
    {
        if ($this->debug) {
            echo __CLASS__.'::onPostPackageUninstall'.PHP_EOL;
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
            foreach ($this->locations as $from_path => $to_path) {
                if (realpath($from_path) === dirname($installPath)) {
                    $fs = new Filesystem();
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
