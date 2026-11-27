<?php

/**
 * Bump the language pack version for both Joomla targets in one go.
 *
 * Updates:
 *   - build/build.config.json   packageVersion of the j5 and j6 targets (used by build.ps1)
 *   - pkg_ms-MY.xml             <version> and <creationDate> (Joomla 5 package manifest)
 *   - README.md                 package versions and file names in "Active Target Versions"
 *   - {site,administrator,api,installation} ms-MY install.xml / langmetadata.xml
 *                               <version> and <creationDate> of the source manifests
 *
 * Usage:
 *   php build/bump.php --j5=5.4.11 --j6=6.2.1 [--pack=1]
 *
 * --pack is the language pack revision (5.4.11.<pack>); default 1.
 * The release tag to create afterwards is printed at the end, e.g. 5.4.11v1.
 *
 * @copyright  (C) 2026 Joomla! Malaysia
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

$options = getopt('', ['j5:', 'j6:', 'pack:', 'help']);

if (isset($options['help']) || !isset($options['j5'], $options['j6'])) {
    fwrite(STDOUT, "Usage: php build/bump.php --j5=5.4.11 --j6=6.2.1 [--pack=1]\n");
    exit(isset($options['help']) ? 0 : 2);
}

$pack = (string) ($options['pack'] ?? '1');

foreach (['j5' => 5, 'j6' => 6] as $key => $major) {
    if (!preg_match('/^' . $major . '\.\d+\.\d+$/', (string) $options[$key])) {
        fwrite(STDERR, "--{$key} must be a Joomla {$major}.x.y version, got '{$options[$key]}'\n");
        exit(2);
    }
}

if (!preg_match('/^\d+$/', $pack)) {
    fwrite(STDERR, "--pack must be a number, got '{$pack}'\n");
    exit(2);
}

$root = dirname(__DIR__);
$full = [
    'j5' => "{$options['j5']}.{$pack}",
    'j6' => "{$options['j6']}.{$pack}",
];
$today = date('Y-m-d');

/**
 * Apply a regex replacement to a file and fail loudly when nothing matched.
 */
function replaceIn(string $path, string $pattern, string $replacement): void
{
    $content = (string) file_get_contents($path);
    $updated = preg_replace($pattern, $replacement, $content, -1, $count);

    if ($count === 0) {
        fwrite(STDERR, "Pattern not found in {$path}: {$pattern}\n");
        exit(1);
    }

    file_put_contents($path, $updated);
}

// 1. build.config.json: the packageVersion that follows each target key
$config = "{$root}/build/build.config.json";

foreach ($full as $key => $version) {
    replaceIn(
        $config,
        '/("key"\s*:\s*"' . $key . '"[^}]*?"packageVersion"\s*:\s*")[^"]*(")/s',
        '${1}' . $version . '${2}'
    );
}

json_decode((string) file_get_contents($config), flags: JSON_THROW_ON_ERROR);

// 2. Repository package manifest (Joomla 5 line)
replaceIn("{$root}/pkg_ms-MY.xml", '#<version>[^<]*</version>#', "<version>{$full['j5']}</version>");
replaceIn("{$root}/pkg_ms-MY.xml", '#<creationDate>[^<]*</creationDate>#', "<creationDate>{$today}</creationDate>");

// 2b. README: "Active Target Versions" table (package version and file name per target)
foreach (['j5' => 5, 'j6' => 6] as $key => $major) {
    $zip = "ms-MY_joomla_lang_full_{$options[$key]}v{$pack}.zip";
    replaceIn(
        "{$root}/README.md",
        '/^\| `' . $key . '` \| ' . $major . ' \| [^|]+\| [^|]+\|$/m',
        "| `{$key}` | {$major} | {$full[$key]} | `dist/{$zip}` |"
    );
}

// 3. Source manifests (shipped as they are by build.ps1 and by Joomla core-translations)
foreach (['language', 'administrator/language', 'api/language'] as $section) {
    foreach (['install.xml', 'langmetadata.xml'] as $file) {
        $path = "{$root}/{$section}/ms-MY/{$file}";

        if (is_file($path)) {
            replaceIn($path, '#<version>[^<]*</version>#', "<version>{$full['j5']}</version>");
            replaceIn($path, '#<creationDate>[^<]*</creationDate>#', "<creationDate>{$today}</creationDate>");
        }
    }
}

$installer = "{$root}/installation/language/ms-MY/langmetadata.xml";

if (is_file($installer)) {
    replaceIn($installer, '#<version>[^<]*</version>#', "<version>{$options['j5']}</version>");
    replaceIn($installer, '#<creationDate>[^<]*</creationDate>#', '<creationDate>' . date('Y-m') . '</creationDate>');
}

$tag = "{$options['j5']}v{$pack}";

echo "Joomla 5 package: {$full['j5']}\n";
echo "Joomla 6 package: {$full['j6']}\n";
echo "Next: rename the CHANGELOG.md [Unreleased] section to [{$tag}], run php build/changelog-xml.php and ./build.ps1, commit, then tag {$tag}\n";
