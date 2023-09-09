<?php

namespace Agilo\WpPackageInstaller;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

class Copier
{
    /** @var string */
    private $name;

    /** @var IOInterface */
    private $io;

    /** @var Filesystem */
    private $fs;

    /**
     * Normalized absolute path to the source directory without trailing slash.
     *
     * @var string
     */
    private $src;

    /**
     * Normalized absolute path to the destination directory without trailing slash.
     *
     * @var string
     */
    private $dest;

    /** @var 'symlink'|'copy'|'none' */
    private $mode;

    /** @var string[] */
    private $paths;

    public function __construct($name, $io, $fs, $src, $dest, $mode, $paths)
    {
        $this->name = $name;
        $this->io = $io;
        $this->fs = $fs;
        $this->src = $src;
        $this->dest = $dest;
        $this->mode = $mode;
        $this->paths = $paths;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function getDest(): string
    {
        return $this->dest;
    }

    public function copy(): void
    {
        if ($this->mode === 'none') {
            return;
        }

        if (!is_dir($this->src)) {
            return;
        }

        if (!$this->paths) {

            /**
             * we don't need to look inside the src dir since no paths are specified
             */

            $this->fs->ensureDirectoryExists(dirname($this->dest));
            Util::remove($this->fs, $this->dest);

            if ($this->mode === 'symlink') {
                $this->fs->relativeSymlink($this->src, $this->dest);
                $this->io->write('Symlinked ' . $this->src . ' to ' . $this->dest);
            } elseif ($this->mode === 'copy') {
                $this->fs->copy($this->src, $this->dest);
                $this->io->write('Copied ' . $this->src . ' to ' . $this->dest);
            } else {
                throw new RuntimeException('Invalid mode: ' . $this->mode);
            }

        } else {

            /**
             * look inside the src dir and handle only the files/dirs that match the paths specified
             */

            $finder = new Finder();
            $finder->in($this->src);

            foreach ($this->paths as $path) {
                if (substr($path, 0, 1) === '!') {
                    $finder->notPath(Glob::toRegex(substr($path, 1)));
                } else {
                    $finder->path(Glob::toRegex($path));
                }
            }

            foreach ($finder as $fileinfo) {

                $srcPath = $fileinfo->getPathname();
                $srcPath = $this->fs->normalizePath($srcPath); // windows compatibility

                if (strpos($srcPath, $this->src) === 0) {
                    $destPath = $this->dest . substr($srcPath, strlen($this->src));

                    $this->fs->ensureDirectoryExists(dirname($destPath));
                    Util::remove($this->fs, $destPath);

                    if ($this->mode === 'symlink') {
                        $this->fs->relativeSymlink($srcPath, $destPath);
                        $this->io->write('Symlinked ' . $srcPath . ' to ' . $destPath);
                    } elseif ($this->mode === 'copy') {
                        $this->fs->copy($srcPath, $destPath);
                        $this->io->write('Copied ' . $srcPath . ' to ' . $destPath);
                    } else {
                        throw new RuntimeException('Invalid mode: ' . $this->mode);
                    }
                } else {
                    $this->io->warning('Invalid src path: ' . $srcPath);
                }
            }
        }
    }
}
