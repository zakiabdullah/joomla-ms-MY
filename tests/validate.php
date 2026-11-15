<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
	if (!$file->isFile() || $file->getExtension() !== 'ini') {
		continue;
	}

	$path = $file->getPathname();
	if (@parse_ini_file($path, false, INI_SCANNER_RAW) === false) {
		echo "Invalid INI: {$path}\n";
		$errors++;
	}
}

foreach (glob($root . '/*.xml') ?: [] as $path) {
	libxml_use_internal_errors(true);
	if (@simplexml_load_file($path) === false) {
		echo "Invalid XML: {$path}\n";
		$errors++;
	}
}

if ($errors > 0) {
	exit(1);
}

echo "Language files and package XML are valid.\n";
