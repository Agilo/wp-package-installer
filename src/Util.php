<?php

namespace Agilo\WpPackageInstaller;

use Composer\Util\Filesystem;

class Util
{
    public static function remove(Filesystem $fs, string $path): bool
    {
        return is_link($path) ? $fs->unlink($path) : $fs->remove($path);
    }
}
