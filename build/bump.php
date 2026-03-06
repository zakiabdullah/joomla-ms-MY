<?php
/**
 * Script used to make a version bump
 * Updates all version XMLs
 *
 * Usage: php build/bump.php -v <version> -l <languagepackversion>
 *
 * Examples:
 * - php build/bump.php -v 5.4.4 -l 1
 * - php build/bump.php -v 6.0.0 -l 1
 *
 * @package    Joomla.Language
 * @copyright  (C) 2026 Joomla! Malaysia <https://www.joomla.my>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// Based on the J!German bump script
// https://github.com/joomlagerman/joomla/blob/5.4-dev/build/bump.php

// Functions
function usage($command)
{
	echo PHP_EOL;
	echo 'Usage: php ' . $command . ' [options]' . PHP_EOL;
	echo PHP_TAB . '[options]:' . PHP_EOL;
	echo PHP_TAB . PHP_TAB . '-v <version>:' . PHP_TAB . 'Version (ex: 5.4.4, 6.0.0)' . PHP_EOL;
	echo PHP_TAB . PHP_TAB . '-l <languagepackversion>:' . PHP_TAB . 'Language pack version (ex: 1, 2)' . PHP_EOL;
	echo PHP_EOL;
}

// Constants.
const PHP_TAB = "\t";

// File paths.
$languageXmlFiles = [
	'/administrator/language/ms-MY/install.xml',
	'/administrator/language/ms-MY/langmetadata.xml',
	'/api/language/ms-MY/install.xml',
	'/api/language/ms-MY/langmetadata.xml',
	'/language/ms-MY/install.xml',
	'/language/ms-MY/langmetadata.xml',
];

$installerXmlFile = '/installation/language/ms-MY/langmetadata.xml';

$languagePackXmlFile = '/pkg_ms-MY.xml';

/*
 * Change copyright date exclusions.
 * Some systems may try to scan the .git directory, exclude it.
 */
$directoryLoopExcludeDirectories = [
	'/.git',
	'/build/tmp/',
];

$directoryLoopExcludeFiles = [];

// Check arguments (exit if incorrect cli arguments).
$opts = getopt("v:l:");

if (empty($opts['v']))
{
	usage($argv[0]);
	die();
}

if (empty($opts['l']))
{
	usage($argv[0]);
	die();
}

// Check version string (exit if not correct).
$versionParts = explode('-', $opts['v']);
$languagePackVersion = (integer) $opts['l'];

if (!preg_match('#^[0-9]+\.[0-9]+\.[0-9]+$#', $versionParts[0]))
{
	usage($argv[0]);
	die();
}

if (!is_integer($languagePackVersion))
{
	usage($argv[0]);
	die();
}

// Make sure we use the correct language and timezone.
setlocale(LC_ALL, 'en_GB');
date_default_timezone_set('Asia/Kuala_Lumpur');

// Set version properties.
$versionSubParts = explode('.', $versionParts[0]);

$version = [
	'main'            => $versionSubParts[0] . '.' . $versionSubParts[1],
	'major'           => $versionSubParts[0],
	'minor'           => $versionSubParts[1],
	'patch'           => $versionSubParts[2],
	'release'         => $versionSubParts[0] . '.' . $versionSubParts[1] . '.' . $versionSubParts[2] . 'v' . $languagePackVersion,
	'full'            => $versionSubParts[0] . '.' . $versionSubParts[1] . '.' . $versionSubParts[2] . '.' . $languagePackVersion,
	'reldate'         => date('j-F-Y'),
	'reltime'         => date('H:i'),
	'reltz'           => 'GMT+8',
	'credate'         => date('Y-m-d'),
	'install_credate' => date('Y-m'),
	'install_version' => $versionSubParts[0] . '.' . $versionSubParts[1] . '.' . $versionSubParts[2],
];

// Prints version information.
echo PHP_EOL;
echo 'Version data:' . PHP_EOL;
echo '- Main:' . PHP_TAB . PHP_TAB . PHP_TAB . PHP_TAB . $version['main'] . PHP_EOL;
echo '- Release:' . PHP_TAB . PHP_TAB . PHP_TAB . $version['release'] . PHP_EOL;
echo '- Full:' . PHP_TAB . PHP_TAB . PHP_TAB . PHP_TAB . $version['full'] . PHP_EOL;
echo '- Release date:' . PHP_TAB . PHP_TAB . PHP_TAB . $version['reldate'] . PHP_EOL;
echo '- Creation date:' . PHP_TAB . PHP_TAB . $version['credate'] . PHP_EOL;
echo '- Installer: creation date:' . PHP_TAB . $version['install_credate'] . PHP_EOL;
echo '- Installer: version:' . PHP_TAB . PHP_TAB . $version['install_version'] . PHP_EOL;

echo PHP_EOL;

$rootPath = dirname(__DIR__);

// Updates the version and creation date in language xml files.
foreach ($languageXmlFiles as $languageXmlFile)
{
	if (file_exists($rootPath . $languageXmlFile))
	{
		$fileContents = file_get_contents($rootPath . $languageXmlFile);
		$fileContents = preg_replace('#<version>[^<]*</version>#', '<version>' . $version['full'] . '</version>', $fileContents);
		$fileContents = preg_replace('#<creationDate>[^<]*</creationDate>#', '<creationDate>' . $version['credate'] . '</creationDate>', $fileContents);
		file_put_contents($rootPath . $languageXmlFile, $fileContents);
	}
}

// Updates the version and creation date in language installer xml file.
if (file_exists($rootPath . $installerXmlFile))
{
	$fileContents = file_get_contents($rootPath . $installerXmlFile);
	$fileContents = preg_replace('#<version>[^<]*</version>#', '<version>' . $version['install_version'] . '</version>', $fileContents);
	$fileContents = preg_replace('#<creationDate>[^<]*</creationDate>#', '<creationDate>' . $version['install_credate'] . '</creationDate>', $fileContents);
	file_put_contents($rootPath . $installerXmlFile, $fileContents);
}

// Updates the version and creation date in language package xml file.
if (file_exists($rootPath . $languagePackXmlFile))
{
	$fileContents = file_get_contents($rootPath . $languagePackXmlFile);
	$fileContents = preg_replace('#<version>[^<]*</version>#', '<version>' . $version['full'] . '</version>', $fileContents);
	$fileContents = preg_replace('#<creationDate>[^<]*</creationDate>#', '<creationDate>' . $version['credate'] . '</creationDate>', $fileContents);
	file_put_contents($rootPath . $languagePackXmlFile, $fileContents);
}

// Updates the copyright date in core files.
$changedFilesCopyrightDate = 0;
$year                      = date('Y');
$directory                 = new RecursiveDirectoryIterator($rootPath);
$iterator                  = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

foreach ($iterator as $file)
{
	if ($file->isFile())
	{
		$filePath     = $file->getPathname();
		$relativePath = str_replace($rootPath, '', $filePath);

		// Exclude certain extensions.
		if (preg_match('#\.(png|jpeg|jpg|gif|bmp|ico|webp|svg|woff|woff2|ttf|eot)$#', $filePath))
		{
			continue;
		}

		// Exclude certain directories.
		$continue = true;

		foreach ($directoryLoopExcludeDirectories as $excludeDirectory)
		{
			if (preg_match('#^' . preg_quote($excludeDirectory) . '#', $relativePath))
			{
				$continue = false;
				break;
			}
		}

		if ($continue)
		{
			$changeCopyrightDate = false;

			// Load the file.
			$fileContents = file_get_contents($filePath);

			// Check if need to change the copyright date for Joomla! Malaysia.
			if (preg_match('#\(C\)\s+[0-9]{4}\s+Joomla!\s+Malaysia#', $fileContents) && !preg_match('#\(C\)\s+' . $year . '\s+Joomla!\s+Malaysia#', $fileContents))
			{
				$changeCopyrightDate = true;
				$fileContents = preg_replace('#\(C\)\s+[0-9]{4}\s+Joomla!\s+Malaysia#', '(C) ' . $year . ' Joomla! Malaysia', $fileContents);
				$changedFilesCopyrightDate++;
			}

			// Save the file.
			if ($changeCopyrightDate)
			{
				echo $filePath;
				file_put_contents($filePath, $fileContents);
			}
		}
	}
}

if ($changedFilesCopyrightDate > 0)
{
	echo '- Copyright Date changed in ' . $changedFilesCopyrightDate . ' files.' . PHP_EOL;
	echo PHP_EOL;
}

echo 'Version bump complete!' . PHP_EOL;
