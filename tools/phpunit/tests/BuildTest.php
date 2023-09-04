<?php

declare(strict_types=1);

namespace Agilo\WpPackageInstaller\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BuildTest extends TestCase
{
    public function dataProvider()
    {
        return [
            [true, 'composer-symlinked-build.json'],
            [false, 'composer-non-symlinked-build.json'],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function test(bool $isSymlinkedBuild, string $composerJsonFilename): void
    {
        $firstPartySrc = TEST_PROJECT_ROOT_DIR.'/src';
        $thirdPartySrc = TEST_PROJECT_ROOT_DIR.'/vendor';
        $dest = TEST_PROJECT_ROOT_DIR.'/public';

        /**
         * setup the project
         */

        (new Process(['rm', '-rf', TEST_PROJECT_ROOT_DIR]))->run();
        (new Process(['cp', '-R', TESTS_ROOT_DIR.'/tests/fixtures/test-project', TEST_PROJECT_ROOT_DIR]))->run();
        (new Process(['cp', '-R', PROJECT_ROOT_DIR, TEST_PROJECT_ROOT_DIR.'/wp-package-installer']))->run();
        $this->assertTrue(copy(TESTS_ROOT_DIR.'/tests/fixtures/'.$composerJsonFilename, TEST_PROJECT_ROOT_DIR.'/composer.json'));

        $process = new Process(['composer', 'install'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        $this->assertProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);

        /**
         * test updating WP core
         */
        $process = new Process(['composer', 'require', '--update-with-dependencies', 'johnpbloch/wordpress:6.2.2'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        $this->assertProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);

        /**
         * test updating existing wpackagist-plugin/<plugin>
         */

        // update wpackagist-plugin/wp-crontrol 1.14 => 1.15
        $process = new Process(['composer', 'require', 'wpackagist-plugin/wp-crontrol:1.15'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        $this->assertProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);

        /**
         * test installing new wpackagist-plugin/<plugin>
         */

        $process = new Process(['composer', 'require', 'wpackagist-plugin/duplicate-post'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        // check that the plugin installed successfully
        $isLink = is_link($dest.'/wp-content/plugins/duplicate-post');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/plugins/duplicate-post');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/plugins/duplicate-post');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/plugins/duplicate-post/duplicate-post.php', $dest.'/wp-content/plugins/duplicate-post/duplicate-post.php');

        // check that the new plugin install didn't break other files
        $this->assertProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);

        /**
         * test uninstalling wpackagist-plugin/<plugin>
         */

        $process = new Process(['composer', 'remove', 'wpackagist-plugin/duplicate-post'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        // check that the plugin was deleted successfully
        $this->assertFalse(is_link($dest.'/wp-content/plugins/duplicate-post'));
        $this->assertFileNotExists($dest.'/wp-content/plugins/duplicate-post');
        $this->assertDirectoryNotExists($dest.'/wp-content/plugins/duplicate-post');

        // check that the plugin uninstall didn't break other files
        $this->assertProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
    }

    private function assertProjectFiles(bool $isSymlinkedBuild, string $firstPartySrc, string $thirdPartySrc, string $dest): void
    {
        /**
         * test WP directory structure
         */
        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertFileEquals($firstPartySrc.'/wp-config.php', $dest.'/wp-config.php');

        /**
         * test 1st party mu-plugins
         */
        $isLink = is_link($dest.'/wp-content/mu-plugins/agilo-mailpit.php');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertFileExists($dest.'/wp-content/mu-plugins/agilo-mailpit.php');
        $this->assertFileEquals($firstPartySrc.'/wp-content/mu-plugins/agilo-mailpit.php', $dest.'/wp-content/mu-plugins/agilo-mailpit.php');

        /**
         * test 1st party plugins
         */
        $isLink = is_link($dest.'/wp-content/plugins/agilo-hello-world-1');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/plugins/agilo-hello-world-1');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/plugins/agilo-hello-world-1');
        $this->assertFileEquals($firstPartySrc.'/wp-content/plugins/agilo-hello-world-1/plugin.php', $dest.'/wp-content/plugins/agilo-hello-world-1/plugin.php');

        /**
         * test standard wpackagist-plugin/<plugin> style plugins
         */
        $isLink = is_link($dest.'/wp-content/plugins/query-monitor');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/plugins/query-monitor');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/plugins/query-monitor');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/plugins/query-monitor/query-monitor.php', $dest.'/wp-content/plugins/query-monitor/query-monitor.php');

        $isLink = is_link($dest.'/wp-content/plugins/wp-crontrol');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/plugins/wp-crontrol');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/plugins/wp-crontrol');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/plugins/wp-crontrol/wp-crontrol.php', $dest.'/wp-content/plugins/wp-crontrol/wp-crontrol.php');

        /**
         * test plugins installed from ./plugins directory
         */
        $isLink = is_link($dest.'/wp-content/plugins/redirection');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/plugins/redirection');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/plugins/redirection');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/plugins/redirection/redirection.php', $dest.'/wp-content/plugins/redirection/redirection.php');
    }
}
