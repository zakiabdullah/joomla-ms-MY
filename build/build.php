<?php
/**
 * Script used to build Joomla language distribution archive packages
 * Builds packages in build/tmp/packages folder
 *
 * Note: the new package must be tagged in your git repository BEFORE doing this.
 * It uses the git tag for the new version, not trunk.
 *
 * This script is designed to be run in CLI on Linux, Mac OS X and WSL.
 * Make sure your default umask is 022 to create archives with correct permissions.
 *
 * Steps:
 * 1. Run the bump script: php build/bump.php -v <version> -l <languagepackversion>
 * 2. Commit the version changes
 * 3. Tag new release in the local git repository (for example, "git tag -a 5.4.4v1 -m 'Release 5.4.4v1'")
 * 4. Run from CLI as: php build/build.php --lpackages --v
 * 5. Check the build/tmp directory
 *
 * Examples:
 * - php build/build.php --lpackages --v
 * - php build/build.php --lpackages --v --tagversion "5.4.4v1"
 *
 * @package    Joomla.Language
 * @copyright  (C) 2026 Joomla! Malaysia <https://www.joomla.my>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// Based on the J!German build script
// https://github.com/joomlagerman/joomla/blob/5.4-dev/build/build.php

const PHP_TAB = "\t";

$time = time();

// Set path to git binary
ob_start();
passthru('which git', $systemGit);
$systemGit = trim(ob_get_clean());

// Make sure file and folder permissions are set correctly
umask(022);

// Shortcut the paths to the repository root and build folder
$repo = dirname(__DIR__);
$here = __DIR__;

// Set paths for the build packages
$tmp      = $here . '/tmp';
$fullpath = $tmp . '/' . $time;

// Parse input options
$options = getopt('', ['help', 'lpackages', 'v', 'tagversion:']);

$showHelp         = isset($options['help']);
$languagePackages = isset($options['lpackages']);
$verbose          = isset($options['v']);
$tagVersion       = $options['tagversion'] ?? false;

if ($showHelp)
{
	usage($argv[0]);
	exit;
}

if (!$tagVersion)
{
	// Looking for the latest local tag
	chdir($repo);
	$tagVersion = system($systemGit . ' describe --tags `' . $systemGit . ' rev-list --tags --max-count=1`', $tagVersion);
}

$remote = 'tags/' . $tagVersion;
chdir($here);

message('Start build for remote ' . $remote, $verbose);
message('Delete old release folder.', $verbose);
system('rm -rf ' . $tmp);
mkdir($tmp);
mkdir($fullpath);

message('Copy the files from the git repository.', $verbose);
chdir($repo);
system($systemGit . ' archive ' . $remote . ' | tar -x -C ' . $fullpath);
chdir($fullpath);

$versionParts = explode('.', $tagVersion);

$languagePackAndPatchVersion = explode('v', $versionParts[2]);

// Set version information for the build
$version     = $versionParts[0] . '.' . $versionParts[1];
$release     = $languagePackAndPatchVersion[0];
$fullVersion = $versionParts[0] . '.' . $versionParts[1] . '.' . $versionParts[2];

chdir($tmp);

// We only need this when we are building packages
if ($languagePackages)
{
	system('mkdir packages');
}

/*
 * Here we set the files/folders which should not be packaged at any time
 * These paths are from the repository root without the leading slash
 */
$doNotPackage = [
	'.git',
	'.github',
	'.gitattributes',
	'.gitignore',
	'CODE_OF_CONDUCT.md',
	'LICENSE',
	'README.md',
	'build',
	'crowdin.yml',
];

// Delete the files and folders we exclude from the packages
message('Delete folders not included in packages.', $verbose);

foreach ($doNotPackage as $removeFile)
{
	system('rm -rf ' . $time . '/' . $removeFile);
}

message('Prepare packages.', $verbose);

if ($languagePackages)
{
	$languageCode = 'ms-MY';

	system('mkdir tmp_packages');
	chdir('tmp_packages');

	system('mkdir ' . $languageCode);
	chdir($languageCode);

	message('Build package: ' . $languageCode, $verbose);

	foreach (['full', 'admin', 'site', 'api'] as $folder)
	{
		$tmpLanguagePath       = $tmp . '/tmp_packages/' . $languageCode;
		$tmpLanguagePathFolder = $tmp . '/tmp_packages/' . $languageCode . '/' . $folder;

		system('mkdir ' . $tmpLanguagePathFolder);

		if ($folder === 'full')
		{
			system('cp ' . $fullpath . '/pkg_ms-MY.xml ' . $tmpLanguagePathFolder . '/pkg_' . $languageCode . '.xml');
			system('cp ' . $fullpath . '/script.php ' . $tmpLanguagePathFolder . '/script.php');
		}

		if ($folder === 'admin')
		{
			system('cp -r ' . $fullpath . '/administrator/language/ms-MY/* ' . $tmpLanguagePathFolder);
			chdir($tmpLanguagePathFolder);

			if ($languagePackages)
			{
				system('zip -r ' . $tmpLanguagePath . '/full/admin_' . $languageCode . '.zip * > /dev/null');
			}
		}

		if ($folder === 'site')
		{
			system('cp -r ' . $fullpath . '/language/ms-MY/* ' . $tmpLanguagePathFolder);
			chdir($tmpLanguagePathFolder);

			if ($languagePackages)
			{
				system('zip -r ' . $tmpLanguagePath . '/full/site_' . $languageCode . '.zip * > /dev/null');
			}
		}

		if ($folder === 'api')
		{
			system('cp -r ' . $fullpath . '/api/language/ms-MY/* ' . $tmpLanguagePathFolder);
			chdir($tmpLanguagePathFolder);

			if ($languagePackages)
			{
				system('zip -r ' . $tmpLanguagePath . '/full/api_' . $languageCode . '.zip * > /dev/null');
			}
		}

		chdir('..');
	}

	if ($languagePackages)
	{
		// Build zip package
		chdir($tmpLanguagePath . '/full');

		system('zip -r ' . $tmpLanguagePath . '/full/full_' . $languageCode . '.zip * > /dev/null');
		system('cp ' . $tmpLanguagePath . '/full/full_' . $languageCode . '.zip ' . $tmp . '/packages/' . $languageCode . '_joomla_lang_full_' . $fullVersion . '.zip');

		chdir('..');
	}

	chdir('..');
}

// Cleanup
system('rm -rf ' . $tmp . '/tmp_packages/');

message('The build of version ' . $fullVersion . ' has been successfully completed!', $verbose);

function message(string $messagetext, $verbose)
{
	if ($verbose)
	{
		echo $messagetext . PHP_EOL;
	}
}

function usage(string $command)
{
	echo PHP_EOL;
	echo 'Usage: php ' . $command . ' [options]' . PHP_EOL;
	echo PHP_TAB . '[options]:' . PHP_EOL;
	echo PHP_TAB . PHP_TAB . '--lpackages' . PHP_TAB . PHP_TAB . 'Build the language packages' . PHP_EOL;
	echo PHP_TAB . PHP_TAB . '--v' . PHP_TAB . PHP_TAB . PHP_TAB . 'Show progress messages' . PHP_EOL;
	echo PHP_TAB . PHP_TAB . '--tagversion "x.y.zv1"' . PHP_TAB . 'Use specific tag version' . PHP_EOL;
	echo PHP_TAB . PHP_TAB . '--help:' . PHP_TAB . PHP_TAB . PHP_TAB . 'Show this help output' . PHP_EOL;
	echo PHP_EOL;
}
