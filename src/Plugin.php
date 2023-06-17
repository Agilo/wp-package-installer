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
use DirectoryIterator;
use Throwable;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Relative paths to where the packages should be copied from.
     * 
     * https://github.com/composer/installers/blob/main/src/Composer/Installers/WordPressInstaller.php
     * 
     * @var array<string, string>
     * 
     * eg.:
     * 'bin/wp-content/plugins/'    => 'public/wp-content/plugins/',
     * 'bin/wp-content/themes/'     => 'public/wp-content/themes/',
     * 'bin/wp-content/mu-plugins/' => 'public/wp-content/mu-plugins/',
     * 'bin/wp-content/dropins/'    => 'public/wp-content/',
     */
    private array $locations = [];

    // /**
    //  * Relative paths to where the packages should be copied to.
    //  */
    // private array $to_locations = [
    //     'plugin'   => 'wp-content/plugins/',
    //     'theme'    => 'wp-content/themes/',
    //     'muplugin' => 'wp-content/mu-plugins/',
    //     'dropin'   => 'wp-content/',
    // ];

    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        if (function_exists('xdebug_break')) {
            // var_dump(\xdebug_break());
        }

        $this->composer = $composer;
        $this->io = $io;
        echo 'Plugin::activate' . PHP_EOL;

        $extra = $this->composer->getPackage()->getExtra();
        var_dump($extra);

        // $this->mapFromLocations($extra);
        $this->mapLocations($extra);
    }

    // private function mapFromLocations(array $extra): void
    // {
    //     if (!isset($extra['installer-paths'])) {
    //         return;
    //     }

    //     if (!is_array($extra['installer-paths'])) {
    //         return;
    //     }

    //     /**
    //      * original code that determines the path from composer/installers can be found here:
    //      * https://github.com/composer/installers/blob/main/src/Composer/Installers/BaseInstaller.php#LL116C34-L116C34
    //      */
    //     foreach ($extra['installer-paths'] as $path => $names) {
    //         $names = (array) $names;
    //         if (in_array('type:wordpress-plugin', $names, true)) {
    //             $this->from_locations['plugin'] = $path;
    //         } elseif (in_array('type:wordpress-theme', $names, true)) {
    //             $this->from_locations['theme'] = $path;
    //         } elseif (in_array('type:wordpress-muplugin', $names, true)) {
    //             $this->from_locations['muplugin'] = $path;
    //         } elseif (in_array('type:wordpress-dropin', $names, true)) {
    //             $this->from_locations['dropin'] = $path;
    //         }
    //     }
    // }

    private function mapLocations(array $extra): void
    {
        if (!isset($extra['agilo-wp-package-installer-paths'])) {
            return;
        }

        if (!is_array($extra['agilo-wp-package-installer-paths'])) {
            return;
        }

        /**
         * original code that determines the path from composer/installers can be found here:
         * https://github.com/composer/installers/blob/main/src/Composer/Installers/BaseInstaller.php#LL116C34-L116C34
         */
        foreach ($extra['agilo-wp-package-installer-paths'] as $from_path => $to_path) {
            if (is_string($from_path) && is_string($to_path)) {
                $this->locations[$from_path] = $to_path;
            }
        }
    }

    // private function getToLocationByPackageType(string $type): ?string
    // {
    //     if (substr($type, 0, 9) === 'wordpress') {
    //         return $this->to_locations[substr($type, 10)] ?? null;
    //     }
    //     return null;
    // }

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
            // PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
            // PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageUpdate',
        ];
    }

    public function onPostUpdateCmd(Event $event): void
    {
        // var_dump(\xdebug_break());
        echo 'Plugin::onPostUpdateCmd' . PHP_EOL;
        $fs = new Filesystem;

        foreach ($this->locations as $from_path => $to_path) {
            try {
                $it = new DirectoryIterator($from_path);
                foreach ($it as $fileinfo) {
                    if ($fileinfo->isDot()) {
                        // skip . & ..
                        continue;
                    }
                    // var_dump(\xdebug_break());
                    // var_dump($fileinfo->getFilename());
                    $fs->relativeSymlink($fileinfo->getRealPath(), realpath($to_path).'/'.$fileinfo->getFilename());
                }
            } catch (Throwable $t) {
                // DirectoryIterator can throw
            }
        }
    }

    public function onPostPackageUninstall(PackageEvent $event): void
    {
        echo 'Plugin::onPostPackageUninstall' . PHP_EOL;

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
                    $pathToRemove = $to_path.basename($installPath);
                    // $fs->remove($pathToRemove);
                    // $remove = $fs->remove($pathToRemove);
                    $remove = $fs->unlink($pathToRemove);
                    return;
                }
            }
        }
    }

    // public function onPostPackageInstall(PackageEvent $event): void
    // {
    //     echo 'Plugin::onPostPackageInstall' . PHP_EOL;
    //     var_dump($event->getName());
    //     file_put_contents(__DIR__ . '/test.txt', print_r($event->getOperation(), true), FILE_APPEND);

    //     $operation = $event->getOperation();
    //     if (!($operation instanceof InstallOperation)) {
    //         return;
    //     }

    //     $package = $operation->getPackage();
    //     var_dump($package->getType());
    //     var_dump($package->getName());
    //     var_dump($package->getId());
    //     var_dump($package->getTargetDir());
    //     var_dump($this->composer->getInstallationManager()->getInstallPath($package));
    //     if ($package->getType() === 'wordpress-plugin') {
    //         // copy_dir();
    //         // $fs = new Filesystem;
    //         // $fs->copy(
    //         //     $this->composer->getInstallationManager()->getInstallPath($package),
    //         //     'public/wp-content/plugins/' . $package->getPrettyName(),
    //         // );
    //     }

    //     // new InstallOperation;
    //     // new Composer\DependencyResolver\Operation\InstallOperation;
    //     // new InstallOperation;
    // }

    // public function onPostPackageUpdate(PackageEvent $event): void
    // {
    //     echo 'Plugin::onPostPackageUpdate' . PHP_EOL;
    //     var_dump($event->getName());
    //     // file_put_contents(__DIR__ . '/test.txt', print_r($event->getOperation(), true), FILE_APPEND);

    //     $operation = $event->getOperation();
    //     if (!($operation instanceof UpdateOperation)) {
    //         return;
    //     }

    //     $package = $operation->getTargetPackage();
    //     // file_put_contents(__DIR__ . '/composer.txt', print_r($this->composer, true));
    //     // $this->composer->
    //     // file_put_contents(__DIR__ . '/onPostPackageUpdate.txt', print_r($package, true));
    //     var_dump($package->getType());
    //     var_dump($package->getTargetDir());
    //     var_dump($this->composer->getInstallationManager()->getInstallPath($package));
    //     // if ($package->getType() === 'wordpress-plugin') {
    //     //     $fs = new Filesystem;
    //     //     $fs->copy(
    //     //         $this->composer->getInstallationManager()->getInstallPath($package),
    //     //         'public/wp-content/plugins/' . basename($this->composer->getInstallationManager()->getInstallPath($package))
    //     //     );
    //     // }
    // }
}
