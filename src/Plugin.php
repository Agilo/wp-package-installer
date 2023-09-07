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

    /** @var string|null */
    private $context = null;

    /** @var bool */
    private $firstPartySymlinkedBuild = true;

    /** @var bool */
    private $thirdPartySymlinkedBuild = true;

    /**
     * Relative path to the first party source directory without trailing slash.
     *
     * @var string
     */
    private $firstPartysrc = 'src';

    /**
     * Relative path to the third party source directory without trailing slash.
     *
     * @var string
     */
    private $thirdPartySrc = '.';

    /**
     * Relative path to the destination directory without trailing slash.
     *
     * @var string
     */
    private $dest = 'wordpress';

    /**
     * Absolute path to the first party source directory without trailing slash.
     *
     * @var string
     */
    private $firstPartySrcDir;

    /**
     * Absolute path to the third party source directory without trailing slash.
     *
     * @var string
     */
    private $thirdPartySrcDir;

    /**
     * Absolute path to the destination directory without trailing slash.
     *
     * @var string
     */
    private $destDir;

    /**
     * First party package relative paths.
     */
    private $firstPartyPaths = [
        '*',
        '!wp-content',
        'wp-content/plugins/*',
        'wp-content/themes/*',
        'wp-content/mu-plugins/*',
        'wp-content/languages/*',
        'wp-content/*.php',
    ];

    private $thirdPartyPaths = [
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
            $this->io->write(__CLASS__ . '::activate');
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
        }

        $context = getenv('AGILO_WP_PACKAGE_INSTALLER_CONTEXT');
        if (is_string($context) && $context !== '') {
            $this->context = $context;
        }

        $this->fs = new Filesystem();
        $extra = $this->composer->getPackage()->getExtra();

        /**
         * Get and validate main settings array.
         */
        $settings = $extra['agilo-wp-package-installer'] ?? [];

        if (!is_array($settings)) {
            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer is not an array.');
        }

        $this->validateAndSetFirstPartyConfig($settings);
        $this->validateAndSetThirdPartyConfig($settings);

        /**
         * dest
         */

        if (isset($settings['dest'])) {
            if (!is_string($settings['dest'])) {
                throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.dest is not a string.');
            }
            if ($settings['dest'] === '') {
                throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.dest is an empty string.');
            }
            $this->dest = $settings['dest'];
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('getcwd() failed.');
        }

        $this->firstPartySrcDir = $this->fs->normalizePath($cwd . '/' . $this->firstPartysrc);
        $this->thirdPartySrcDir = $this->fs->normalizePath($cwd . '/' . $this->thirdPartySrc);
        $this->destDir = $this->fs->normalizePath($cwd . '/' . $this->dest);
    }

    private function validateAndSetFirstPartyConfig(array $config)
    {
        $firstPartyConfig = isset($config['first-party']) && is_array($config['first-party']) ? $config['first-party'] : [];
        $firstPartyConfigOverride = $this->context && isset($config['overrides'][$this->context]) && is_array($config['overrides'][$this->context]) ? $config['overrides'][$this->context] : [];
        $firstPartyConfig = array_merge($firstPartyConfig, $firstPartyConfigOverride);

        if (isset($firstPartyConfig['symlink'])) {
            if (!is_bool($firstPartyConfig['symlink'])) {
                throw new InvalidArgumentException('first-party.symlink is not a boolean.');
            }
            $this->firstPartySymlinkedBuild = $firstPartyConfig['symlink'];
        }

        if (isset($firstPartyConfig['src'])) {
            if (!is_string($firstPartyConfig['src'])) {
                throw new InvalidArgumentException('first-party.src is not a string.');
            }
            if ($firstPartyConfig['src'] === '') {
                throw new InvalidArgumentException('first-party.src is an empty string.');
            }
            $this->firstPartysrc = $firstPartyConfig['src'];
        }

        if (isset($firstPartyConfig['paths'])) {
            if (!is_array($firstPartyConfig['paths'])) {
                throw new InvalidArgumentException('first-party.paths is not an array.');
            }
            foreach ($firstPartyConfig['paths'] as $index => $path) {
                if (!is_string($path)) {
                    throw new InvalidArgumentException('first-party.paths[' . $index . '] is not a string.');
                }
                if ($path === '') {
                    throw new InvalidArgumentException('first-party.paths[' . $index . '] is an empty string.');
                }
            }
            $this->firstPartyPaths = array_merge($this->firstPartyPaths, $firstPartyConfig['paths']);
        }
    }

    private function validateAndSetThirdPartyConfig(array $config)
    {
        $thirdPartyConfig = isset($config['third-party']) && is_array($config['third-party']) ? $config['third-party'] : [];
        $thirdPartyConfigOverride = $this->context && isset($config['overrides'][$this->context]) && is_array($config['overrides'][$this->context]) ? $config['overrides'][$this->context] : [];
        $thirdPartyConfig = array_merge($thirdPartyConfig, $thirdPartyConfigOverride);

        if (isset($thirdPartyConfig['symlink'])) {
            if (!is_bool($thirdPartyConfig['symlink'])) {
                throw new InvalidArgumentException('third-party.symlink is not a boolean.');
            }
            $this->thirdPartySymlinkedBuild = $thirdPartyConfig['symlink'];
        }

        if (isset($thirdPartyConfig['src'])) {
            if (!is_string($thirdPartyConfig['src'])) {
                throw new InvalidArgumentException('third-party.src is not a string.');
            }
            if ($thirdPartyConfig['src'] === '') {
                throw new InvalidArgumentException('third-party.src is an empty string.');
            }
            $this->thirdPartySrc = $thirdPartyConfig['src'];
        }

        if (isset($thirdPartyConfig['paths'])) {
            if (!is_array($thirdPartyConfig['paths'])) {
                throw new InvalidArgumentException('third-party.paths is not an array.');
            }
            foreach ($thirdPartyConfig['paths'] as $index => $path) {
                if (!is_string($path)) {
                    throw new InvalidArgumentException('third-party.paths[' . $index . '] is not a string.');
                }
                if ($path === '') {
                    throw new InvalidArgumentException('third-party.paths[' . $index . '] is an empty string.');
                }
            }
            $this->thirdPartyPaths = array_merge($this->thirdPartyPaths, $thirdPartyConfig['paths']);
        }
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        if ($this->debug) {
            $this->io->write(__CLASS__ . '::deactivated');
        }
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        if ($this->debug) {
            $this->io->write(__CLASS__ . '::uninstall');
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
            $this->io->write(__CLASS__ . '::onPostUpdateCmd');
            if (function_exists('xdebug_break')) {
                xdebug_break();
            }
        }

        /**
         * Handle third party packages.
         */

        if (is_dir($this->thirdPartySrcDir)) {
            $finder = new Finder();
            $finder->in($this->thirdPartySrcDir);

            foreach ($this->thirdPartyPaths as $path) {
                if (substr($path, 0, 1) === '!') {
                    $finder->notPath(Glob::toRegex(substr($path, 1)));
                } else {
                    $finder->path(Glob::toRegex($path));
                }
            }

            foreach ($finder as $fileinfo) {
                $srcPath = $fileinfo->getRealPath();
                if ($srcPath === false) {
                    throw new RuntimeException('getRealPath() failed.');
                }
                $srcPath = $this->fs->normalizePath($srcPath);
                $destPath = str_replace($this->thirdPartySrcDir, $this->destDir, $srcPath);

                $this->fs->ensureDirectoryExists(dirname($destPath));

                if ($this->thirdPartySymlinkedBuild) {
                    $this->fs->relativeSymlink($srcPath, $destPath);
                    echo 'Symlinked ' . $srcPath . ' to ' . $destPath . PHP_EOL;
                } else {
                    $this->fs->copy($srcPath, $destPath);
                    echo 'Copied ' . $srcPath . ' to ' . $destPath . PHP_EOL;
                }
            }
        }

        /**
         * Handle first party packages.
         */

        if (is_dir($this->firstPartySrcDir)) {
            $finder = new Finder();
            $finder->in($this->firstPartySrcDir);

            foreach ($this->firstPartyPaths as $path) {
                if (substr($path, 0, 1) === '!') {
                    $finder->notPath(Glob::toRegex(substr($path, 1)));
                } else {
                    $finder->path(Glob::toRegex($path));
                }
            }

            foreach ($finder as $fileinfo) {
                $srcPath = $fileinfo->getRealPath();
                if ($srcPath === false) {
                    throw new RuntimeException('getRealPath() failed.');
                }
                $srcPath = $this->fs->normalizePath($srcPath);
                $destPath = str_replace($this->firstPartySrcDir, $this->destDir, $srcPath);

                $this->fs->ensureDirectoryExists(dirname($destPath));

                if ($this->firstPartySymlinkedBuild) {
                    $this->fs->relativeSymlink($srcPath, $destPath);
                    $this->io->write('Symlinked ' . $srcPath . ' to ' . $destPath);
                } else {
                    $this->fs->copy($srcPath, $destPath);
                    $this->io->write('Copied ' . $srcPath . ' to ' . $destPath);
                }
            }
        }
    }

    public function onPostPackageUninstall(PackageEvent $event): void
    {
        if ($this->debug) {
            $this->io->write(__CLASS__ . '::onPostPackageUninstall');
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
            $destPath = str_replace($this->thirdPartySrcDir, $this->destDir, $installPath);

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
