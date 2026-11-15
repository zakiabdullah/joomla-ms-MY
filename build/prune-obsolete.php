<?php

/**
 * Remove ms-MY keys (and files) that no longer exist in any supported Joomla version.
 *
 * A key is only removed when it is absent from EVERY reference given, so pass all
 * supported releases and development branches, e.g. the latest 5.x and 6.x tags
 * plus 5.4-dev and 6.2-dev.
 *
 * Usage:
 *   php build/prune-obsolete.php --ref=../cms-5.4.10 --ref=../cms-5.4-dev --ref=../cms-6.2.0 --ref=../cms-6.2-dev [--write]
 *
 * Without --write it only reports what would be removed.
 *
 * @copyright  (C) 2026 Joomla! Malaysia
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

const SECTIONS = ['language', 'administrator/language', 'api/language', 'installation/language'];
const TAG      = 'ms-MY';
const KEY_RE   = '/^\s*([A-Z0-9_.:\-]+)\s*=/';

$options = getopt('', ['ref:', 'write']);
$refs    = array_map(static fn ($r) => rtrim((string) $r, '/\\'), (array) ($options['ref'] ?? []));
$write   = isset($options['write']);
$root    = dirname(__DIR__);

if (count($refs) < 2) {
    fwrite(STDERR, "Pass at least two --ref checkouts (every supported Joomla major).\n");
    exit(2);
}

foreach ($refs as $ref) {
    if (!is_dir("{$ref}/administrator/language/en-GB")) {
        fwrite(STDERR, "Not a joomla-cms checkout with en-GB files: {$ref}\n");
        exit(2);
    }
}

/** @return array<string,true> */
function keysOf(string $path): array
{
    $keys = [];

    foreach (file($path) ?: [] as $line) {
        if (preg_match(KEY_RE, $line, $m) && !str_starts_with(ltrim($line), ';')) {
            $keys[$m[1]] = true;
        }
    }

    return $keys;
}

$totalKeys  = 0;
$totalFiles = 0;

foreach (SECTIONS as $section) {
    foreach (glob("{$root}/{$section}/" . TAG . '/*.ini') ?: [] as $path) {
        $name  = basename($path);
        $rel   = "{$section}/" . TAG . "/{$name}";
        $known = [];
        $inRef = false;

        foreach ($refs as $ref) {
            $enPath = "{$ref}/{$section}/en-GB/{$name}";

            if (is_file($enPath)) {
                $inRef = true;
                $known += keysOf($enPath);
            }
        }

        if (!$inRef) {
            $totalFiles++;
            echo "FILE  {$rel} (not in any reference)\n";

            if ($write) {
                unlink($path);
            }

            continue;
        }

        $lines   = file($path) ?: [];
        $kept    = [];
        $removed = [];

        foreach ($lines as $line) {
            if (preg_match(KEY_RE, $line, $m) && !str_starts_with(ltrim($line), ';') && !isset($known[$m[1]])) {
                $removed[] = $m[1];
                continue;
            }

            $kept[] = $line;
        }

        if (!$removed) {
            continue;
        }

        $totalKeys += count($removed);
        echo sprintf("KEYS  %s: %d obsolete\n", $rel, count($removed));

        if ($write) {
            // Collapse blank lines left behind by removed blocks
            $content = preg_replace("/(\r?\n){3,}/", "\n\n", implode('', $kept));
            file_put_contents($path, $content);
        }
    }
}

echo sprintf("%s %d obsolete key(s) and %d obsolete file(s).\n", $write ? 'Removed' : 'Would remove', $totalKeys, $totalFiles);
