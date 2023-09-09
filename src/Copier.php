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

    public function copy(): void
    {
        if ($this->mode === 'none') {
            return;
        }

        if (is_dir($this->src)) {
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
                $srcPath = $fileinfo->getRealPath();
                if ($srcPath === false) {
                    throw new RuntimeException('getRealPath() failed.');
                }
                $srcPath = $this->fs->normalizePath($srcPath);
                $destPath = str_replace($this->src, $this->dest, $srcPath);

                $this->fs->ensureDirectoryExists(dirname($destPath));

                if ($this->mode === 'symlink') {
                    $this->fs->relativeSymlink($srcPath, $destPath);
                    $this->io->write('Symlinked ' . $srcPath . ' to ' . $destPath);
                } elseif ($this->mode === 'copy') {
                    $this->fs->copy($srcPath, $destPath);
                    $this->io->write('Copied ' . $srcPath . ' to ' . $destPath);
                } else {
                    throw new RuntimeException('Invalid mode: ' . $this->mode);
                }
            }
        }
    }
}
