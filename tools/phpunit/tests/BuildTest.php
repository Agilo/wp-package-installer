<?php

declare(strict_types=1);

namespace Agilo\WpPackageInstaller\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class BuildTest extends TestCase
{
    private function setupProject(string $composerJsonFilename): void
    {
        /**
         * setup the project
         */

        $filesystem = new Filesystem();

        // remove tmp dir if it exists from the last run
        $filesystem->remove(TEST_PROJECT_ROOT_DIR);
        CustomAsserts::assertFileDoesNotExist(TEST_PROJECT_ROOT_DIR);
        CustomAsserts::assertDirectoryDoesNotExist(TEST_PROJECT_ROOT_DIR);

        // copy the test project fixture to a tmp dir
        $filesystem->mirror(TESTS_ROOT_DIR.'/tests/fixtures/test-project', TEST_PROJECT_ROOT_DIR);
        $this->assertDirectoryExists(TEST_PROJECT_ROOT_DIR);
        $this->assertFileExists(TEST_PROJECT_ROOT_DIR.'/src/wp-config.php');

        // copy the wp-package-installer package to the tmp dir
        $filesystem->mirror(
            PROJECT_ROOT_DIR,
            TEST_PROJECT_ROOT_DIR.'/wp-package-installer',
            // copy only src dir and composer.json
            (new Finder())->in(PROJECT_ROOT_DIR)->exclude(['tools', 'vendor'])->path('src')->path('composer.json')
        );
        $this->assertDirectoryExists(TEST_PROJECT_ROOT_DIR.'/wp-package-installer');
        $this->assertFileExists(TEST_PROJECT_ROOT_DIR.'/wp-package-installer/src/Plugin.php');
        $this->assertFileExists(TEST_PROJECT_ROOT_DIR.'/wp-package-installer/composer.json');

        $this->assertTrue(copy(TESTS_ROOT_DIR.'/tests/fixtures/'.$composerJsonFilename, TEST_PROJECT_ROOT_DIR.'/composer.json'));

        $process = new Process(['composer', 'install'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertSame(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());
    }

    private function assertFirstPartyProjectFiles(bool $isSymlinkedBuild, string $firstPartySrc, string $thirdPartySrc, string $dest): void
    {
        $this->assertFileEquals($firstPartySrc.'/wp-config.php', $dest.'/wp-config.php');

        /**
         * test 1st party custom folders
         */
        $isLink = is_link($dest.'/html');
        $this->assertSame($isLink, $isSymlinkedBuild);

        $this->assertFileExists($dest.'/html/index.php');
        $this->assertFileEquals($firstPartySrc.'/html/index.php', $dest.'/html/index.php');

        // test dot file
        $this->assertFileExists($dest.'/html/.browserslistrc');
        $this->assertFileEquals($firstPartySrc.'/html/.browserslistrc', $dest.'/html/.browserslistrc');

        $this->assertFileExists($dest.'/html/inc/functions.php');
        $this->assertFileEquals($firstPartySrc.'/html/inc/functions.php', $dest.'/html/inc/functions.php');

        /**
         * test 1st party themes
         */

        $isLink = is_link($dest.'/wp-content/themes/hello-world');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertFileExists($dest.'/wp-content/themes/hello-world/index.php');
        $this->assertFileEquals($firstPartySrc.'/wp-content/themes/hello-world/index.php', $dest.'/wp-content/themes/hello-world/index.php');
        $this->assertFileExists($dest.'/wp-content/themes/hello-world/style.css');
        $this->assertFileEquals($firstPartySrc.'/wp-content/themes/hello-world/style.css', $dest.'/wp-content/themes/hello-world/style.css');

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
         * test 1st party drop-ins
         */
        $isLink = is_link($dest.'/wp-content/sunrise.php');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertFileExists($dest.'/wp-content/sunrise.php');
        $this->assertFileEquals($firstPartySrc.'/wp-content/sunrise.php', $dest.'/wp-content/sunrise.php');
    }

    private function assertThirdPartyProjectFiles(bool $isSymlinkedBuild, string $firstPartySrc, string $thirdPartySrc, string $dest): void
    {
        /**
         * test 3rd party wpackagist-plugin/<plugin> style plugins
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
         * test 3rd party wpackagist-plugin/<theme> style themes
         */
        $isLink = is_link($dest.'/wp-content/themes/twentysixteen');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/themes/twentysixteen');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/themes/twentysixteen');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/themes/twentysixteen/style.css', $dest.'/wp-content/themes/twentysixteen/style.css');

        /**
         * test 3rd party plugins installed from ./plugins directory
         */
        $isLink = is_link($dest.'/wp-content/plugins/classic-editor');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/plugins/classic-editor');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/plugins/classic-editor');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/plugins/classic-editor/classic-editor.php', $dest.'/wp-content/plugins/classic-editor/classic-editor.php');
    }

    private function assertUploadsProjectFiles(bool $isSymlinkedBuild, string $firstPartySrc, string $thirdPartySrc, string $dest, string $uploadsSrc): void
    {
        $isLink = is_link($dest.'/wp-content/uploads');
        $this->assertSame($isLink, $isSymlinkedBuild);
        $this->assertDirectoryExists($dest.'/wp-content/uploads');
        CustomAsserts::assertDirectoryNotEmpty($dest.'/wp-content/uploads');
        $this->assertFileEquals($uploadsSrc.'/index.php', $dest.'/wp-content/uploads/index.php');
        $this->assertFileEquals($uploadsSrc.'/2023/09/328-50x50.jpg', $dest.'/wp-content/uploads/2023/09/328-50x50.jpg');
    }

    public function testDefaults()
    {
        // $firstPartySrc = TEST_PROJECT_ROOT_DIR.'/src';
        $thirdPartySrc = TEST_PROJECT_ROOT_DIR;
        $dest = TEST_PROJECT_ROOT_DIR.'/wordpress';

        // test composer.json without any extra config, by default without config our plugin should do nothing
        $this->setupProject('composer-defaults.json');

        CustomAsserts::assertWpDirectoryStructure($dest);

        // check that composer/installers installed plugins/themes to the default locations
        $this->assertDirectoryExists($thirdPartySrc.'/wp-content/plugins/query-monitor');
        $this->assertDirectoryExists($thirdPartySrc.'/wp-content/plugins/classic-editor');
        $this->assertDirectoryExists($thirdPartySrc.'/wp-content/themes/twentysixteen');

        // check that wp-package-installer didn't install anything
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/plugins/query-monitor');
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/plugins/classic-editor');
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/themes/twentysixteen');
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/uploads');
        CustomAsserts::assertFileDoesNotExist($dest.'/html');
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-config.php');
    }

    public static function johnpblochBuildDataProvider(): array
    {
        return [
            [true, 'composer-symlinked-johnpbloch-defaults.json'],
            [false, 'composer-non-symlinked-johnpbloch-defaults.json'],
        ];
    }

    /**
     * @dataProvider johnpblochBuildDataProvider
     */
    public function testJohnpblochBuild(bool $isSymlinkedBuild, string $composerJsonFilename): void
    {
        if ($isSymlinkedBuild && DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Symlinked builds are not supported on Windows');
        }

        $firstPartySrc = TEST_PROJECT_ROOT_DIR.'/src';
        $thirdPartySrc = TEST_PROJECT_ROOT_DIR;
        $dest = TEST_PROJECT_ROOT_DIR.'/wordpress';

        $this->setupProject($composerJsonFilename);
        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertThirdPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
    }

    public static function usualComposerActionsDataProvider(): array
    {
        return [
            [true, 'composer-symlinked-actions.json'],
            [false, 'composer-non-symlinked-actions.json'],
        ];
    }

    /**
     * @dataProvider usualComposerActionsDataProvider
     */
    public function testUsualComposerActions(bool $isSymlinkedBuild, string $composerJsonFilename): void
    {
        if ($isSymlinkedBuild && DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Symlinked builds are not supported on Windows');
        }

        $firstPartySrc = TEST_PROJECT_ROOT_DIR.'/src';
        $thirdPartySrc = TEST_PROJECT_ROOT_DIR.'/vendor-wp';
        $dest = TEST_PROJECT_ROOT_DIR.'/public';
        $uploadsSrc = TEST_PROJECT_ROOT_DIR.'/shared/uploads';

        $this->setupProject($composerJsonFilename);
        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertFirstPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertThirdPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertUploadsProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest, $uploadsSrc);

        /**
         * test updating WP core
         */
        $process = new Process(['composer', 'require', '--update-with-dependencies', 'johnpbloch/wordpress:6.2.2'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertFirstPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertThirdPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertUploadsProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest, $uploadsSrc);

        /**
         * test updating existing wpackagist-plugin/<plugin>
         */

        // update wpackagist-plugin/wp-crontrol 1.14 => 1.15
        $process = new Process(['composer', 'require', 'wpackagist-plugin/wp-crontrol:1.15'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertFirstPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertThirdPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertUploadsProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest, $uploadsSrc);

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
        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertFirstPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertThirdPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertUploadsProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest, $uploadsSrc);

        /**
         * test uninstalling wpackagist-plugin/<plugin>
         */

        $process = new Process(['composer', 'remove', 'wpackagist-plugin/duplicate-post'], TEST_PROJECT_ROOT_DIR);
        $process->setTimeout(5 * 60);
        $this->assertEquals(0, $process->run(), $process->getCommandLine().' failed with the error below.'.PHP_EOL.$process->getErrorOutput());

        // check that the plugin was deleted successfully
        $this->assertFalse(is_link($dest.'/wp-content/plugins/duplicate-post'));
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/plugins/duplicate-post');
        CustomAsserts::assertDirectoryDoesNotExist($dest.'/wp-content/plugins/duplicate-post');

        // check that the plugin uninstall didn't break other files
        CustomAsserts::assertWpDirectoryStructure($dest);
        $this->assertFirstPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertThirdPartyProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest);
        $this->assertUploadsProjectFiles($isSymlinkedBuild, $firstPartySrc, $thirdPartySrc, $dest, $uploadsSrc);
    }

    public static function buildWithCustomPathsDataProvider(): array
    {
        return [
            [true, 'composer-symlinked-custom-paths.json'],
            [false, 'composer-non-symlinked-custom-paths.json'],
        ];
    }

    /**
     * @dataProvider buildWithCustomPathsDataProvider
     */
    public function testBuildWithCustomPaths(bool $isSymlinkedBuild, string $composerJsonFilename): void
    {
        if ($isSymlinkedBuild && DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Symlinked builds are not supported on Windows');
        }

        $firstPartySrc = TEST_PROJECT_ROOT_DIR.'/src';
        $thirdPartySrc = TEST_PROJECT_ROOT_DIR.'/vendor-wp';
        $dest = TEST_PROJECT_ROOT_DIR.'/public';

        $this->setupProject($composerJsonFilename);

        CustomAsserts::assertFileDoesNotExist($dest.'/html');
        CustomAsserts::assertFileDoesNotExist($dest.'/phpcs.xml.dist');

        $this->assertFileExists($dest.'/scripts/task1.php');
        $this->assertFileEquals($firstPartySrc.'/scripts/task1.php', $dest.'/scripts/task1.php');
        CustomAsserts::assertFileDoesNotExist($dest.'/scripts/task2.php');

        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/plugins/query-monitor');

        $this->assertFileExists($dest.'/wp-content/plugins/wp-crontrol/wp-crontrol.php');
        $this->assertFileEquals($thirdPartySrc.'/wp-content/plugins/wp-crontrol/wp-crontrol.php', $dest.'/wp-content/plugins/wp-crontrol/wp-crontrol.php');
        CustomAsserts::assertFileDoesNotExist($dest.'/wp-content/plugins/wp-crontrol/readme.md');
    }
}
