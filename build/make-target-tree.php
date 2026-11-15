<?php

/**
 * Turn the shared (j5) source tree into the tree of another target, in place.
 *
 * Joomla's core-translations project downloads the j5 and j6 branches of this
 * repository as they are (no build step) and syncs them to Crowdin and the core
 * installer. The j6 branch must therefore contain Joomla 6 ready files:
 *
 *   - overrides/<target>/... values applied to the language files
 *   - pkg_ms-MY.xml and source manifests with the target packageVersion
 *   - the Joomla Update Directory URL for the target major (translationlist_<major>)
 *   - script.php requiring the target Joomla major
 *
 * Usage (run on a checkout of j5; the sync-j6 workflow does this):
 *   php build/make-target-tree.php j6
 *
 * @copyright  (C) 2026 Joomla! Malaysia
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

const TAG = 'ms-MY';

$key  = $argv[1] ?? '';
$root = dirname(__DIR__);

$config = json_decode((string) file_get_contents("{$root}/build/build.config.json"), true, flags: JSON_THROW_ON_ERROR);
$target = null;

foreach ($config['targets'] as $candidate) {
    if ($candidate['key'] === $key) {
        $target = $candidate;
    }
}

if ($target === null) {
    fwrite(STDERR, "Usage: php build/make-target-tree.php <target key from build/build.config.json, e.g. j6>\n");
    exit(2);
}

$major   = (int) $target['joomlaMajor'];
$version = (string) $target['packageVersion'];               // e.g. 6.2.0.1
$joomla  = implode('.', array_slice(explode('.', $version), 0, 3)); // e.g. 6.2.0

/**
 * Regex replace that fails loudly when the pattern does not match.
 */
function rewrite(string $path, string $pattern, string $replacement): void
{
    $content = (string) file_get_contents($path);
    $updated = preg_replace($pattern, $replacement, $content, -1, $count);

    if ($count === 0) {
        fwrite(STDERR, "Pattern not found in {$path}: {$pattern}\n");
        exit(1);
    }

    file_put_contents($path, $updated);
}

// 1. Apply overrides/<target>/<section>/language/ms-MY/*.ini
$applied = 0;

foreach (glob("{$root}/overrides/{$key}/{,*/}language/" . TAG . '/*.ini', GLOB_BRACE) ?: [] as $override) {
    $relative = substr(str_replace('\\', '/', $override), strlen(str_replace('\\', '/', "{$root}/overrides/{$key}/")));
    $file     = "{$root}/{$relative}";

    if (!is_file($file)) {
        fwrite(STDERR, "Override {$relative} has no matching language file\n");
        exit(1);
    }

    $values = [];

    foreach (file($override, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*([A-Z0-9_.:\-]+)\s*=\s*".*"\s*$/', $line, $m)) {
            $values[$m[1]] = trim($line);
        }
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $seen  = [];

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*([A-Z0-9_.:\-]+)\s*=/', $line, $m) && isset($values[$m[1]])) {
            $lines[$i]     = $values[$m[1]];
            $seen[$m[1]]   = true;
        }
    }

    foreach (array_diff_key($values, $seen) as $line) {
        $lines[] = $line;
    }

    file_put_contents($file, implode("\n", $lines) . "\n");
    $applied += count($values);
}

// 2. Package manifest: version and update server of the target major
rewrite("{$root}/pkg_ms-MY.xml", '#<version>[^<]*</version>#', "<version>{$version}</version>");
rewrite("{$root}/pkg_ms-MY.xml", '#translationlist_\d+\.xml#', "translationlist_{$major}.xml");

// 3. Source language manifests
foreach (['language', 'administrator/language', 'api/language'] as $section) {
    foreach (['install.xml', 'langmetadata.xml'] as $name) {
        $path = "{$root}/{$section}/" . TAG . "/{$name}";

        if (is_file($path)) {
            rewrite($path, '#<version>[^<]*</version>#', "<version>{$version}</version>");
        }
    }
}

$installer = "{$root}/installation/language/" . TAG . '/langmetadata.xml';

if (is_file($installer)) {
    rewrite($installer, '#<version>[^<]*</version>#', "<version>{$joomla}</version>");
}

// 4. Installer script: minimum Joomla major
rewrite("{$root}/script.php", "#(\\\$this->minimumJoomla\\s*=\\s*')[^']+(')#", '${1}' . $major . '.0${2}');

echo "Prepared {$key} tree: Joomla {$major}, package {$version}, {$applied} override(s) applied.\n";
