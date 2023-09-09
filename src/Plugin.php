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
use Composer\Util\Platform;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var bool */
    private $debug = false;

    /** @var string */
    private $cwd;

    /** @var string|null */
    private $context = null;

    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    /** @var Filesystem */
    private $fs;

    /**
     * Normalized absolute path to the destination directory without trailing slash.
     *
     * @var string
     */
    private $dest;

    /** @var Copier[] */
    private $copiers = [];

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

        $this->cwd = getcwd();
        if ($this->cwd === false) {
            throw new RuntimeException('Could not determine the current working directory.');
        }

        $context = getenv('AGILO_WP_PACKAGE_INSTALLER_CONTEXT');
        if (is_string($context) && $context !== '') {
            $this->context = $context;
        }

        $this->fs = new Filesystem();

        /**
         * Get and validate main config array.
         */

        $extra = $this->composer->getPackage()->getExtra();
        $config = $extra['agilo-wp-package-installer'] ?? [];
        $this->validateConfig($config);

        $this->dest = $this->fs->normalizePath($this->cwd . '/' . $config['dest']);

        $this->buildCopiers($config);
    }

    /**
     * Throws early if config is invalid.
     */
    private function validateConfig($config): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer is not an array.');
        }

        $this->validateSources($config);
        $this->validateDest($config);
        $this->validateOverrides($config);
    }

    /**
     * Throws early if config is invalid.
     */
    private function validateSources($config): void
    {
        if (!isset($config['sources'])) {
            return;
        }

        if (!is_array($config['sources'])) {
            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources is not an object.');
        }

        foreach ($config['sources'] as $name => $source) {

            if (!is_array($source)) {
                throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . ' is not an object.');
            }

            /**
             * src
             */

            if (isset($source['src'])) {
                if (!is_string($source['src'])) {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.src is not a string.');
                }
                if ($source['src'] === '') {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.src is an empty string.');
                }
            } else {
                if (in_array($name, ['first-party', 'third-party', 'uploads'], true)) {
                    // these have a default src
                } else {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.src is not set.');
                }
            }

            /**
             * mode
             */

            if (isset($source['mode'])) {
                if (!is_string($source['mode'])) {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.mode is not a string.');
                }
                if (!in_array($source['mode'], ['none', 'copy', 'symlink'])) {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.mode is not one of "none", "copy", or "symlink".');
                }
            } else {
                // every source has a default mode
            }

            /**
             * paths
             */

            if (isset($source['paths'])) {
                if (!is_array($source['paths'])) {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.paths is not an array.');
                }
                foreach ($source['paths'] as $index => $path) {
                    if (!is_string($path)) {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.paths[' . $index . '] is not a string.');
                    }
                    if ($path === '') {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . '.paths[' . $index . '] is an empty string.');
                    }
                }
            } else {
                // every source has default paths
            }
        }
    }

    /**
     * Throws early if config is invalid.
     */
    private function validateDest($config): void
    {
        if (isset($config['dest'])) {
            if (!is_string($config['dest'])) {
                throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.dest is not a string.');
            }
            if ($config['dest'] === '') {
                throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.dest is an empty string.');
            }
        }
    }

    /**
     * Throws early if config is invalid.
     */
    private function validateOverrides($config): void
    {
        if (!isset($config['overrides'])) {
            return;
        }

        if (!is_array($config['overrides'])) {
            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides is not an object.');
        }

        foreach ($config['overrides'] as $context => $configOverride) {

            /**
             * dest
             */

            if (isset($configOverride['dest'])) {
                if (!is_string($configOverride['dest'])) {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.dest is not a string.');
                }
                if ($configOverride['dest'] === '') {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.dest is an empty string.');
                }
            }

            /**
             * sources
             */

            if (!isset($configOverride['sources'])) {
                continue;
            }

            if (!is_array($configOverride['sources'])) {
                throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources is not an object.');
            }

            foreach ($configOverride['sources'] as $name => $source) {

                if (!is_array($source)) {
                    throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . ' is not an object.');
                }

                /**
                 * src
                 */

                if (isset($source['src'])) {
                    if (!is_string($source['src'])) {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.src is not a string.');
                    }
                    if ($source['src'] === '') {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.src is an empty string.');
                    }
                }

                /**
                 * mode
                 */

                if (isset($source['mode'])) {
                    if (!is_string($source['mode'])) {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.mode is not a string.');
                    }
                    if (!in_array($source['mode'], ['none', 'copy', 'symlink'])) {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.mode is not one of "none", "copy", or "symlink".');
                    }
                }

                /**
                 * paths
                 */

                if (isset($source['paths'])) {
                    if (!is_array($source['paths'])) {
                        throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.paths is not an array.');
                    }
                    foreach ($source['paths'] as $index => $path) {
                        if (!is_string($path)) {
                            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.paths[' . $index . '] is not a string.');
                        }
                        if ($path === '') {
                            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.overrides.'.$context.'.sources.' . $name . '.paths[' . $index . '] is an empty string.');
                        }
                    }
                }
            }
        }
    }

    private function buildCopiers($config)
    {
        $sourceNames = array_keys(array_merge(
            $config['sources'] ?? [],
            [
                'first-party' => [],
                'third-party' => [],
                'uploads'     => [],
            ]
        ));

        foreach ($sourceNames as $name) {
            $source = $this->getSourceConfig($config, $name);
            $this->copiers[] = new Copier(
                $name,
                $this->io,
                $this->fs,
                $this->fs->normalizePath($this->cwd . '/' . $source['src']),
                $this->dest,
                $source['mode'],
                $source['paths']
            );
        }
    }

    private function getSourceConfig($config, $name): array
    {
        $sources = $config['sources'] ?? [];

        if ($name === 'first-party') {
            $default = [
                'src' => 'src',
                'mode' => 'symlink',
                'paths' => [],
            ];
        } elseif ($name === 'third-party') {
            $default = [
                'src' => '.',
                'mode' => 'symlink',
                'paths' => [],
            ];
        } elseif ($name === 'uploads') {
            $default = [
                'src' => 'shared/uploads',
                'mode' => 'symlink',
                'paths' => [],
            ];
        } else {
            $default = [
                'mode' => 'symlink',
                'paths' => [],
            ];
        }

        if ($name === 'first-party') {
            $source = $sources[$name] ?? [];
        } elseif ($name === 'third-party') {
            $source = $sources[$name] ?? [];
        } elseif ($name === 'uploads') {
            $source = $sources[$name] ?? [];
        } elseif (!isset($sources[$name])) {
            throw new InvalidArgumentException('composer.extra.agilo-wp-package-installer.sources.' . $name . ' is not set.');
        }

        $override = $config['overrides'][$this->context]['sources'][$name] ?? [];

        $sourceConfig = array_merge($default, $source, $override);

        if ($name === 'first-party') {
            $sourceConfig['paths'] = array_merge(
                [
                    '*',
                    '!wp-content',
                    'wp-content/plugins/*',
                    'wp-content/themes/*',
                    'wp-content/mu-plugins/*',
                    'wp-content/languages/*',
                    'wp-content/*.php',
                ],
                $sourceConfig['paths']
            );
        } elseif ($name === 'third-party') {
            $sourceConfig['paths'] = array_merge(
                [
                    'wp-content/plugins/*',
                    'wp-content/themes/*',
                    'wp-content/mu-plugins/*',
                    'wp-content/languages/*',
                    'wp-content/*.php',
                ],
                $sourceConfig['paths']
            );
        }

        return $sourceConfig;
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

        foreach ($this->copiers as $copier) {
            $copier->copy();
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

        $copier = $this->getThirdPartyCopier();
        if (!$copier) {
            return;
        }

        $package = $operation->getPackage();
        $installPath = $this->composer->getInstallationManager()->getInstallPath($package);

        if ($installPath) {
            $destPath = str_replace($copier->getSrc(), $this->dest, $installPath);

            // strip trailing slashes
            $destPath = rtrim($destPath, '/\\');

            $this->remove($destPath);
            return;
        }
    }

    private function getThirdPartyCopier(): ?Copier
    {
        foreach ($this->copiers as $copier) {
            if ($copier->getName() === 'third-party') {
                return $copier;
            }
        }
        return null;
    }

    private function remove(string $path): bool
    {
        if (is_link($path)) {
            return $this->fs->unlink($path);
        }
        return $this->fs->remove($path);
    }
}
