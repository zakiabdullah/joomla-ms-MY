<?php

/**
 * Generate changelog.xml (Joomla changelog format) from CHANGELOG.md.
 *
 * pkg_ms-MY.xml points <changelogurl> to this file, so Joomla shows the changes
 * of the installed version under System -> Manage -> Extensions -> Changelog.
 * Every released section "## [<tag>]" becomes one <changelog> per package:
 * the Joomla 5 version from the tag (5.4.11v3 => 5.4.11.3) and the Joomla 6
 * version from the section text (6.2.1.3, or "6.2.1" plus the pack number).
 *
 * Headings map to Joomla changelog types: Added => addition, Changed => change,
 * Fixed => fix, Removed => remove, Security => security. "Tooling" is skipped
 * because it only concerns this repository, not the sites using the pack.
 *
 * Usage:
 *   php build/changelog-xml.php           write changelog.xml
 *   php build/changelog-xml.php --check   exit 1 when changelog.xml is out of date
 *
 * @copyright  (C) 2026 Joomla! Malaysia
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

const TYPES = [
    'added'    => 'addition',
    'changed'  => 'change',
    'fixed'    => 'fix',
    'removed'  => 'remove',
    'security' => 'security',
    'tooling'  => null,
];
// Order of the elements inside <changelog>, as in Joomla's own changelogs
const ORDER = ['security', 'fix', 'language', 'addition', 'change', 'remove', 'note'];

$root      = dirname(__DIR__);
$check     = in_array('--check', $argv, true);
$changelog = str_replace("\r\n", "\n", (string) file_get_contents("{$root}/CHANGELOG.md"));

/**
 * Markdown list text to plain text.
 */
function plain(string $text): string
{
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);   // [label](url)
    $text = str_replace(['**', '`'], '', $text);
    $text = preg_replace('/(?<![\w*])\*(?!\s)([^*]+?)\*(?![\w*])/u', '$1', $text); // *emphasis*

    return trim(preg_replace('/\s+/', ' ', $text));
}

/**
 * Bullet items of a section body, grouped by Joomla changelog type.
 *
 * @return array<string, list<string>>
 */
function items(string $body): array
{
    $groups  = [];
    $type    = 'change';  // bullets without a heading (older sections)
    $current = null;      // [type, text] of the item being read

    $flush = static function () use (&$current, &$groups): void {
        if ($current !== null && $current[0] !== null && plain($current[1]) !== '') {
            $groups[$current[0]][] = plain($current[1]);
        }

        $current = null;
    };

    foreach (explode("\n", $body) as $line) {
        if (preg_match('/^###\s+(.+)$/', $line, $m)) {
            $flush();
            $key  = strtolower(trim($m[1]));
            $type = array_key_exists($key, TYPES) ? TYPES[$key] : 'note';
            continue;
        }

        // "- item" and nested "  - item" both start a new item
        if (preg_match('/^\s*- (.*)$/', $line, $m)) {
            $flush();
            $current = [$type, $m[1]];
            continue;
        }

        // Continuation of a wrapped item
        if ($current !== null && preg_match('/^\s+\S/', $line)) {
            $current[1] .= ' ' . trim($line);
            continue;
        }

        $flush();
    }

    $flush();

    return $groups;
}

$xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<changelogs>'];

preg_match_all('/^## \[(\d+\.\d+\.\d+)v(\d+)\][^\n]*\n(.*?)(?=^## \[|^\[[^\]]+\]: |\z)/ms', $changelog, $sections, PREG_SET_ORDER);

foreach ($sections as [, $joomla5, $pack, $body]) {
    $versions = ["{$joomla5}.{$pack}"];

    // Joomla 6 package: an explicit 6.x.y.n, otherwise the Joomla 6.x.y version named in the text
    if (preg_match('/\b(6\.\d+\.\d+\.\d+)\b/', $body, $m)) {
        $versions[] = $m[1];
    } elseif (preg_match('/\b(6\.\d+\.\d+)\b/', $body, $m)) {
        $versions[] = "{$m[1]}.{$pack}";
    }

    $groups = items($body);

    foreach ($versions as $version) {
        $xml[] = "\t<changelog>";
        $xml[] = "\t\t<element>pkg_ms-MY</element>";
        $xml[] = "\t\t<type>package</type>";
        $xml[] = "\t\t<version>{$version}</version>";

        foreach (ORDER as $type) {
            if (empty($groups[$type])) {
                continue;
            }

            $xml[] = "\t\t<{$type}>";

            foreach ($groups[$type] as $item) {
                $xml[] = "\t\t\t<item>" . htmlspecialchars($item, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</item>';
            }

            $xml[] = "\t\t</{$type}>";
        }

        $xml[] = "\t</changelog>";
    }
}

$xml[]  = '</changelogs>';
$output = implode("\n", $xml) . "\n";
$target = "{$root}/changelog.xml";

if ($check) {
    $current = is_file($target) ? str_replace("\r\n", "\n", (string) file_get_contents($target)) : '';

    if ($current !== $output) {
        fwrite(STDERR, "changelog.xml is out of date. Run: php build/changelog-xml.php\n");
        exit(1);
    }

    echo "changelog.xml is up to date.\n";
    exit(0);
}

file_put_contents($target, $output);
echo 'Wrote changelog.xml with ' . substr_count($output, '<changelog>') . " changelog entries.\n";
