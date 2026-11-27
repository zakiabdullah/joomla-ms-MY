<?php

/**
 * Print the GitHub Release body for a tag, taken from CHANGELOG.md.
 *
 * CHANGELOG.md is the single source of release notes: the section
 * "## [<tag>] - <date>" becomes the release body, followed by the
 * standard install and verification instructions.
 *
 * Usage:
 *   php build/release-notes.php 5.4.11v1 > release-notes.md
 *
 * Exit code 1 when the CHANGELOG has no section for the tag, so a release
 * cannot be published without release notes.
 *
 * @copyright  (C) 2026 Joomla! Malaysia
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

$tag = $argv[1] ?? '';

if (!preg_match('/^\d+\.\d+\.\d+v\d+$/', $tag)) {
    fwrite(STDERR, "Usage: php build/release-notes.php <tag, e.g. 5.4.11v1>\n");
    exit(2);
}

$root      = dirname(__DIR__);
$changelog = str_replace("\r\n", "\n", (string) file_get_contents("{$root}/CHANGELOG.md"));

// Section body: from the heading of this tag up to the next "## [" heading or the link references
$pattern = '/^## \[' . preg_quote($tag, '/') . '\][^\n]*\n(.*?)(?=^## \[|^\[[^\]]+\]: |\z)/ms';

if (!preg_match($pattern, $changelog, $m) || trim($m[1]) === '') {
    fwrite(STDERR, "CHANGELOG.md has no section '## [{$tag}]'. Add it before tagging.\n");
    exit(1);
}

$config  = json_decode((string) file_get_contents("{$root}/build/build.config.json"), true, flags: JSON_THROW_ON_ERROR);
$targets = [];

// The tag must match the Joomla 5 package version (5.4.11v1 <=> 5.4.11.1)
$expected = str_replace('v', '.', $tag);

foreach ($config['targets'] as $target) {
    if ($target['key'] === 'j5' && $target['packageVersion'] !== $expected) {
        fwrite(STDERR, "Tag {$tag} does not match j5 packageVersion {$target['packageVersion']} in build/build.config.json. Run build/bump.php first.\n");
        exit(1);
    }
}

foreach ($config['targets'] as $target) {
    if (!empty($target['enabled'])) {
        // Same file name as build.ps1: 6.2.1.1 => ms-MY_joomla_lang_full_6.2.1v1.zip
        $parts   = explode('.', (string) $target['packageVersion']);
        $zipName = sprintf('%s_joomla_lang_full_%s.%s.%sv%s.zip', $config['languageTag'] ?? 'ms-MY', $parts[0], $parts[1], $parts[2], $parts[3] ?? '1');

        $targets[] = sprintf(
            '| `%s` | Joomla %d.x | %s |',
            $zipName,
            $target['joomlaMajor'],
            $target['packageVersion']
        );
    }
}

$body = trim($m[1]);

echo <<<MD
Malay (ms-MY) language pack for Joomla! 5 and 6.

## Packages
| Package | For | Version |
|---|---|---|

MD;
echo implode("\n", $targets) . "\n\n";
echo $body . "\n\n";
echo <<<MD
## Install
1. Download the package that matches your Joomla major version.
2. In Joomla Administrator open **System → Install → Extensions** and upload the ZIP.
3. Enable Malay in **System → Manage → Languages**.

Existing sites are updated through the Joomla Update Directory.

## Verify
`SHA256SUMS.txt` lists the checksum of each package. Builds are reproducible:
running `./build.ps1` on this tag produces byte-identical ZIP files.

MD;
