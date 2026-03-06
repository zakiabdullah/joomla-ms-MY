<?php
/**
 * Script used to build Joomla language distribution archive packages
 * Builds packages in build/tmp/packages folder
 *
 * This script works on Windows, Linux, macOS, and WSL.
 *
 * Steps:
 * 1. Run the bump script: php build/bump.php -v <version> -l <languagepackversion>
 * 2. Commit the version changes
 * 3. Tag new release in the local git repository (for example, "git tag -a 5.4.4v1 -m 'Release 5.4.4v1'")
 * 4. Run from CLI as: php build/build.php --lpackages --v
 * 5. Check the build/tmp/packages directory
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

// Shortcut the paths to the repository root and build folder
$repo = dirname(__DIR__);
$here = __DIR__;

// Set paths for the build packages
$tmp = $here . DIRECTORY_SEPARATOR . 'tmp';

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

// If no tag specified, read version from pkg_ms-MY.xml
if (!$tagVersion)
{
	$pkgFile = $repo . DIRECTORY_SEPARATOR . 'pkg_ms-MY.xml';

	if (!file_exists($pkgFile))
	{
		echo 'Error: pkg_ms-MY.xml not found.' . PHP_EOL;
		exit(1);
	}

	$xml = simplexml_load_file($pkgFile);
	$pkgVersion = (string) $xml->version;

	if (empty($pkgVersion))
	{
		echo 'Error: Could not read version from pkg_ms-MY.xml.' . PHP_EOL;
		exit(1);
	}

	// Convert version format: 5.4.4.1 -> 5.4.4v1 for tag format
	$parts = explode('.', $pkgVersion);

	if (count($parts) === 4)
	{
		$tagVersion = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . 'v' . $parts[3];
	}
	else
	{
		$tagVersion = $pkgVersion;
	}

	message('No tag specified, using version from pkg_ms-MY.xml: ' . $pkgVersion, true);
}

// Parse version from tag (supports both 5.4.4v1 and 5.4.4.1 formats)
if (strpos($tagVersion, 'v') !== false)
{
	// Format: 5.4.4v1
	$mainAndPatch = explode('v', $tagVersion);
	$versionBase  = $mainAndPatch[0];
	$langPack     = $mainAndPatch[1] ?? '1';
	$versionParts = explode('.', $versionBase);
	$fullVersion  = $versionBase . 'v' . $langPack;
}
else
{
	// Format: 5.4.4.1
	$versionParts = explode('.', $tagVersion);
	$langPack     = $versionParts[3] ?? '1';
	$versionBase  = $versionParts[0] . '.' . $versionParts[1] . '.' . $versionParts[2];
	$fullVersion  = $tagVersion;
}

$version = $versionParts[0] . '.' . $versionParts[1];

message('Start build for version ' . $fullVersion, $verbose);

// Clean and create tmp directories
message('Delete old release folder.', $verbose);
deleteDirectory($tmp);

$packagesDir = $tmp . DIRECTORY_SEPARATOR . 'packages';
@mkdir($packagesDir, 0755, true);

/*
 * Files/folders which should not be packaged
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
	'dist',
	'_build_temp',
];

if ($languagePackages)
{
	$languageCode = 'ms-MY';
	$tmpLang      = $tmp . DIRECTORY_SEPARATOR . 'lang_build';

	message('Build package: ' . $languageCode, $verbose);

	// Prepare the full package directory
	$fullDir = $tmpLang . DIRECTORY_SEPARATOR . 'full';
	@mkdir($fullDir, 0755, true);

	// Copy pkg_ms-MY.xml and script.php to full package
	copy($repo . DIRECTORY_SEPARATOR . 'pkg_ms-MY.xml', $fullDir . DIRECTORY_SEPARATOR . 'pkg_' . $languageCode . '.xml');

	$scriptFile = $repo . DIRECTORY_SEPARATOR . 'script.php';

	if (file_exists($scriptFile))
	{
		copy($scriptFile, $fullDir . DIRECTORY_SEPARATOR . 'script.php');
	}

	// Build admin ZIP
	$adminSource = $repo . DIRECTORY_SEPARATOR . 'administrator' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'ms-MY';

	if (is_dir($adminSource))
	{
		$adminZip = $fullDir . DIRECTORY_SEPARATOR . 'admin_' . $languageCode . '.zip';
		createZipFromDirectory($adminSource, $adminZip);
		$fileCount = count(glob($adminSource . DIRECTORY_SEPARATOR . '*'));
		message('  admin: ' . $fileCount . ' files -> admin_' . $languageCode . '.zip (' . round(filesize($adminZip) / 1024, 1) . ' KB)', $verbose);
	}

	// Build site ZIP
	$siteSource = $repo . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'ms-MY';

	if (is_dir($siteSource))
	{
		$siteZip = $fullDir . DIRECTORY_SEPARATOR . 'site_' . $languageCode . '.zip';
		createZipFromDirectory($siteSource, $siteZip);
		$fileCount = count(glob($siteSource . DIRECTORY_SEPARATOR . '*'));
		message('  site: ' . $fileCount . ' files -> site_' . $languageCode . '.zip (' . round(filesize($siteZip) / 1024, 1) . ' KB)', $verbose);
	}

	// Build API ZIP
	$apiSource = $repo . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'ms-MY';

	if (is_dir($apiSource))
	{
		$apiZip = $fullDir . DIRECTORY_SEPARATOR . 'api_' . $languageCode . '.zip';
		createZipFromDirectory($apiSource, $apiZip);
		$fileCount = count(glob($apiSource . DIRECTORY_SEPARATOR . '*'));
		message('  api: ' . $fileCount . ' files -> api_' . $languageCode . '.zip (' . round(filesize($apiZip) / 1024, 1) . ' KB)', $verbose);
	}

	// Build the full language pack ZIP
	$packageName = $languageCode . '_joomla_lang_full_' . $fullVersion . '.zip';
	$packagePath = $packagesDir . DIRECTORY_SEPARATOR . $packageName;
	createZipFromDirectory($fullDir, $packagePath);

	message('', $verbose);
	message('Package: packages/' . $packageName . ' (' . round(filesize($packagePath) / 1024, 1) . ' KB)', $verbose);

	// Cleanup temp build
	deleteDirectory($tmpLang);
}

message('', $verbose);
message('The build of version ' . $fullVersion . ' has been successfully completed!', $verbose);

// --- Helper functions ---

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

function deleteDirectory(string $dir): void
{
	if (!is_dir($dir))
	{
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($items as $item)
	{
		if ($item->isDir())
		{
			rmdir($item->getPathname());
		}
		else
		{
			unlink($item->getPathname());
		}
	}

	rmdir($dir);
}

function createZipFromDirectory(string $sourceDir, string $zipFile): void
{
	$zip = new ZipArchive();

	if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
	{
		echo 'Error: Cannot create zip file: ' . $zipFile . PHP_EOL;
		exit(1);
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($files as $file)
	{
		$filePath     = $file->getPathname();
		$relativePath = substr($filePath, strlen($sourceDir) + 1);

		// Normalize path separators for ZIP
		$relativePath = str_replace('\\', '/', $relativePath);

		$zip->addFile($filePath, $relativePath);
	}

	$zip->close();
}
