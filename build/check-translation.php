<?php

/**
 * Translation quality checker for the ms-MY Joomla language pack.
 *
 * Local checks (always run):
 *   - UTF-8 validity, no BOM
 *   - every non-comment line is KEY="value" (Joomla's strict INI format)
 *   - keys are upper case, no duplicate keys
 *   - no unescaped double quotes inside a value
 *   - file parses with parse_ini_file(INI_SCANNER_RAW)
 *   - no banned terms from build/glossary-rules.json (see GLOSSARY.md)
 *   - package and language XML manifests are well-formed
 *
 * Reference checks (with --ref=<path to a joomla-cms checkout>):
 *   - en-GB files without a ms-MY counterpart
 *   - en-GB keys missing from ms-MY
 *   - sprintf placeholder mismatch (%s, %d, %1$s ...)
 *   - obsolete ms-MY keys (reported with --verbose only)
 *
 * Usage:
 *   php build/check-translation.php [--ref=../joomla-cms] [--target=j6] [--strict] [--verbose] [--summary=FILE] [--json=FILE]
 *
 *   --target applies overrides/<target>/ on top of the shared files, exactly as
 *   build.ps1 does, before comparing with the reference.
 *
 * Exit code: 1 on errors (or on warnings with --strict), 0 otherwise.
 *
 * @copyright  (C) 2026 Joomla! Malaysia
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

const SECTIONS = ['language', 'administrator/language', 'api/language', 'installation/language'];
const TAG      = 'ms-MY';

$options = getopt('', ['ref:', 'target:', 'strict', 'verbose', 'summary:', 'json:', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php build/check-translation.php [--ref=PATH] [--target=j5|j6] [--strict] [--verbose] [--summary=FILE] [--json=FILE]\n");
    exit(0);
}

$root    = dirname(__DIR__);
$ref     = isset($options['ref']) ? rtrim((string) $options['ref'], '/\\') : null;
$target  = isset($options['target']) ? (string) $options['target'] : null;
$strict  = isset($options['strict']);
$verbose = isset($options['verbose']);
$isCi    = getenv('GITHUB_ACTIONS') === 'true';

$errors   = [];
$warnings = [];
$stats    = ['files' => 0, 'keys' => 0, 'reference_keys' => 0, 'missing_files' => 0, 'missing_file_keys' => 0, 'missing_keys' => 0, 'placeholders' => 0, 'protected' => 0, 'obsolete' => 0];

/**
 * Report a problem; emits a GitHub annotation when running in Actions.
 */
function report(array &$bucket, string $level, string $file, ?int $line, string $message, bool $isCi): void
{
    $bucket[] = [$file, $line, $message];

    if ($isCi) {
        $loc = 'file=' . $file . ($line ? ',line=' . $line : '');
        fwrite(STDOUT, "::{$level} {$loc}::{$message}\n");
    }
}

/**
 * Parse an INI file in Joomla's strict format.
 *
 * @return array{0: array<string,string>, 1: array<string,int>, 2: list<array{int,string}>, 3: array<string,string>}
 *         [key => value], [key => line number], [ [line, problem] ... ], [key => trailing comment]
 */
function parseStrict(string $path): array
{
    $values   = [];
    $lines    = [];
    $problems = [];
    $comments = [];
    $content  = (string) file_get_contents($path);

    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $problems[] = [1, 'File starts with a UTF-8 BOM'];
        $content    = substr($content, 3);
    }

    if (!mb_check_encoding($content, 'UTF-8')) {
        $problems[] = [1, 'File is not valid UTF-8'];
    }

    foreach (preg_split('/\r\n|\n|\r/', $content) as $i => $raw) {
        $n    = $i + 1;
        $line = trim($raw);

        if ($line === '' || $line[0] === ';' || $line[0] === '[') {
            continue;
        }

        // A trailing "; comment" after the closing quote is allowed (en-GB uses it too)
        if (!preg_match('/^([^=\s]+)\s*=\s*"(.*)"\s*(?:;(.*))?$/', $line, $m)) {
            $problems[] = [$n, 'Line is not in KEY="value" format'];
            continue;
        }

        [$key, $value] = [$m[1], $m[2]];

        if (isset($m[3])) {
            $comments[$key] = trim($m[3]);
        }

        if ($key !== strtoupper($key) || !preg_match('/^[A-Z0-9_.:\-]+$/', $key)) {
            $problems[] = [$n, "Invalid key name: {$key}"];
        }

        // A double quote is only allowed when escaped as \"
        if (preg_match('/(?<!\\\\)"/', $value)) {
            $problems[] = [$n, "Unescaped double quote in {$key} (use \\\")"];
        }

        if (isset($values[$key])) {
            $problems[] = [$n, "Duplicate key {$key} (first defined on line {$lines[$key]})"];
        }

        $values[$key] = $value;
        $lines[$key] ??= $n;
    }

    if (@parse_ini_file($path, false, INI_SCANNER_RAW) === false) {
        $problems[] = [1, 'parse_ini_file() failed'];
    }

    return [$values, $lines, $problems, $comments];
}

/**
 * Extract tokens that must survive translation unchanged:
 *   - sprintf placeholders as used by Joomla (%s, %d, %u, %f, %1$s, %02d ...)
 *   - JavaScript tokens in braces ({file}, {remaining} ...)
 * The escaped "%%", plain-text percentages ("50% or") and URL encoding ("%3F")
 * are ignored.
 *
 * @return list<string>
 */
function placeholders(string $value): array
{
    $value = str_replace('%%', '', $value);
    preg_match_all('/%(?:\d+\$)?(?:0\d+)?(?:\.\d+)?[sduf](?![A-Za-z0-9])/', $value, $m);

    // Normalise: "%s" and "%1$s" are equivalent (Malay word order often differs)
    $out = array_map(static fn ($p) => preg_replace('/^%\d+\$/', '%', $p), $m[0]);

    // Named tokens are replaced by name, so only their presence matters, not the count
    preg_match_all('/\{[a-z][a-zA-Z0-9_]*\}/', $value, $js);
    $out = array_merge($out, array_unique($js[0]));
    sort($out);

    return $out;
}

// Terminology rules from GLOSSARY.md
$glossary      = json_decode((string) @file_get_contents("{$root}/build/glossary-rules.json"), true) ?: ['rules' => [], 'allow' => []];
$glossaryAllow = array_flip($glossary['allow'] ?? []);

/**
 * Report banned terms (see build/glossary-rules.json) in the visible text of a value.
 */
function checkGlossary(array $rules, array $allow, string $rel, string $key, string $value, int $line, array &$errors, bool $isCi): void
{
    if (isset($allow[$key])) {
        return;
    }

    // Ignore markup and URLs: only the text a user reads must follow the glossary
    $text = preg_replace(['/<[^>]*>/u', '#\b(?:https?://|index\.php\?)\S+#u'], ' ', $value);

    foreach ($rules as $rule) {
        if (preg_match('/' . $rule['pattern'] . '/iu', $text, $m)) {
            report($errors, 'error', $rel, $line, "Glossary: \"{$m[0]}\" in {$key}, use \"{$rule['use']}\" ({$rule['en']})", $isCi);
        }
    }
}

// XML manifests must be well-formed
$xmlFiles = array_merge(glob("{$root}/*.xml") ?: [], glob("{$root}/*/language/" . TAG . '/*.xml') ?: [], glob("{$root}/language/" . TAG . '/*.xml') ?: []);

foreach (array_unique($xmlFiles) as $path) {
    $rel = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $root)) + 1);
    libxml_use_internal_errors(true);
    libxml_clear_errors();

    if (simplexml_load_file($path) === false) {
        $error = libxml_get_errors()[0] ?? null;
        report($errors, 'error', $rel, $error?->line, 'Invalid XML: ' . trim($error?->message ?? 'parse error'), $isCi);
    }
}

// Override files must be well-formed and must override an existing file
$overrideFiles = array_merge(
    glob("{$root}/overrides/*/language/" . TAG . '/*.ini') ?: [],
    glob("{$root}/overrides/*/*/language/" . TAG . '/*.ini') ?: []
);

foreach ($overrideFiles as $path) {
    $rel    = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $root)) + 1);
    $shared = preg_replace('#^overrides/[^/]+/#', '', $rel);

    if (!is_file("{$root}/{$shared}")) {
        report($errors, 'error', $rel, null, "Override has no matching file {$shared}", $isCi);
    }

    [$ovValues, $ovLines, $ovProblems] = parseStrict($path);

    foreach ($ovProblems as [$n, $msg]) {
        report($errors, 'error', $rel, $n, $msg, $isCi);
    }

    foreach ($ovValues as $key => $value) {
        checkGlossary($glossary['rules'], $glossaryAllow, $rel, $key, $value, $ovLines[$key], $errors, $isCi);
    }
}

/**
 * Terms that en-GB marks as untranslatable in a trailing comment, e.g.
 *   KEY="Force HTTP Headers" ; Do not translate 'HTTP Headers'
 *   KEY="max-age" ; Do not translate                 (the whole value)
 *   KEY="..." ; Do not translate the word Markdown
 *   KEY="..." ; do not translate $key
 * "Do not translate the text between the {}" is covered by the placeholder check.
 *
 * @return list<string>
 */
function protectedTerms(string $enValue, string $comment): array
{
    if (!preg_match('/not\s+translate/i', $comment) || preg_match('/between the \{\}/i', $comment)) {
        return [];
    }

    $terms = [];

    if (preg_match_all("/'([^']+)'/", $comment, $m)) {
        $terms = $m[1];
    } elseif (preg_match('/the word\s+([\w.\-]+)/i', $comment, $m)) {
        $terms = [$m[1]];
    } elseif (preg_match_all('/\$\w+/', $comment, $m)) {
        $terms = $m[0];
    } elseif (preg_match('/^\W*(?:please\s+)?do\s+not\s+translate\W*$/i', $comment)) {
        // The whole visible text must stay as is (markup may be localised, e.g. link text)
        $text  = trim(html_entity_decode(strip_tags($enValue)));
        $terms = $text !== '' ? [$text] : [];
    }

    // Only enforce terms that really occur in the source string
    return array_values(array_filter($terms, static fn ($t) => str_contains($enValue, $t)));
}

foreach (SECTIONS as $section) {
    $msDir = "{$root}/{$section}/" . TAG;

    if (!is_dir($msDir)) {
        continue;
    }

    $msFiles = glob("{$msDir}/*.ini") ?: [];

    foreach ($msFiles as $path) {
        $rel = "{$section}/" . TAG . '/' . basename($path);
        [$values, $valueLines, $problems] = parseStrict($path);
        $stats['files']++;
        $stats['keys'] += count($values);

        foreach ($problems as [$n, $msg]) {
            report($errors, 'error', $rel, $n, $msg, $isCi);
        }

        foreach ($values as $key => $value) {
            checkGlossary($glossary['rules'], $glossaryAllow, $rel, $key, $value, $valueLines[$key], $errors, $isCi);
        }
    }

    if ($ref === null) {
        continue;
    }

    $enDir = "{$ref}/{$section}/en-GB";

    if (!is_dir($enDir)) {
        continue;
    }

    foreach (glob("{$enDir}/*.ini") ?: [] as $enPath) {
        $name   = basename($enPath);
        $msPath = "{$msDir}/{$name}";
        $rel    = "{$section}/" . TAG . "/{$name}";
        [$en, , , $enComments] = parseStrict($enPath);
        $stats['reference_keys'] += count($en);

        if (!is_file($msPath)) {
            $stats['missing_files']++;
            $stats['missing_file_keys'] += count($en);
            report($warnings, 'warning', $rel, null, 'Missing file (' . count($en) . ' keys in en-GB)', $isCi);
            continue;
        }

        [$ms, $msLines] = parseStrict($msPath);

        // Overlay the version-specific overrides the build applies for this target
        $overridePath = $target !== null ? "{$root}/overrides/{$target}/{$section}/" . TAG . "/{$name}" : null;

        if ($overridePath !== null && is_file($overridePath)) {
            [$ov] = parseStrict($overridePath);
            $ms   = array_merge($ms, $ov);
        }

        foreach ($en as $key => $enValue) {
            if (!array_key_exists($key, $ms)) {
                $stats['missing_keys']++;
                report($warnings, 'warning', $rel, null, "Missing key {$key}=\"{$enValue}\"", $isCi);
                continue;
            }

            if (placeholders($enValue) !== placeholders($ms[$key])) {
                $stats['placeholders']++;
                $expected = implode(' ', placeholders($enValue)) ?: '(none)';
                $actual   = implode(' ', placeholders($ms[$key])) ?: '(none)';
                report($warnings, 'warning', $rel, $msLines[$key], "Placeholder mismatch in {$key}: en-GB has {$expected}, ms-MY has {$actual}", $isCi);
            }

            foreach (protectedTerms($enValue, $enComments[$key] ?? '') as $term) {
                if (!str_contains($ms[$key], $term)) {
                    $stats['protected']++;
                    report($warnings, 'warning', $rel, $msLines[$key], "Protected term in {$key}: en-GB says do not translate \"{$term}\"", $isCi);
                }
            }
        }

        $obsolete = array_diff_key($ms, $en);
        $stats['obsolete'] += count($obsolete);

        if ($verbose && $obsolete) {
            fwrite(STDOUT, "Note: {$rel} has " . count($obsolete) . " key(s) not in this en-GB reference\n");
        }
    }
}

// Human readable output (annotations already printed in CI)
if (!$isCi) {
    foreach ([['ERROR', $errors], ['WARN', $warnings]] as [$label, $list]) {
        foreach ($list as [$file, $line, $msg]) {
            fwrite(STDOUT, sprintf("[%s] %s%s: %s\n", $label, $file, $line ? ":{$line}" : '', $msg));
        }
    }
}

/**
 * Share of en-GB keys that have a ms-MY translation, rounded down to one decimal
 * so that 100% is only shown when nothing is missing.
 */
function coverage(array $stats): string
{
    if ($stats['reference_keys'] === 0) {
        return '0';
    }

    $translated = $stats['reference_keys'] - $stats['missing_keys'] - $stats['missing_file_keys'];
    $percent    = floor($translated / $stats['reference_keys'] * 1000) / 10;

    return rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');
}

$summary = sprintf(
    "Checked %d files / %d keys. Errors: %d. Warnings: %d%s",
    $stats['files'],
    $stats['keys'],
    count($errors),
    count($warnings),
    $ref !== null
        ? sprintf(' (coverage: %s%%, missing files: %d, missing keys: %d, placeholder mismatches: %d, protected terms: %d, keys not in reference: %d)', coverage($stats), $stats['missing_files'], $stats['missing_keys'], $stats['placeholders'], $stats['protected'], $stats['obsolete'])
        : ''
);

fwrite(STDOUT, $summary . "\n");

if (isset($options['summary'])) {
    $md   = ["### ms-MY translation check" . ($ref !== null ? ' against `' . basename($ref) . '`' : ''), ''];
    $md[] = '| Metric | Count |';
    $md[] = '|---|---:|';
    $md[] = "| Files checked | {$stats['files']} |";
    $md[] = "| Keys checked | {$stats['keys']} |";
    $md[] = '| Syntax / format errors | ' . count($errors) . ' |';

    if ($ref !== null) {
        $md[] = '| Coverage | ' . coverage($stats) . '% |';
        $md[] = "| Missing files | {$stats['missing_files']} |";
        $md[] = "| Missing keys | {$stats['missing_keys']} |";
        $md[] = "| Placeholder mismatches | {$stats['placeholders']} |";
        $md[] = "| Protected terms translated | {$stats['protected']} |";
    }

    if ($warnings) {
        $md[] = '';
        $md[] = '<details><summary>Warnings</summary>';
        $md[] = '';

        foreach ($warnings as [$file, , $msg]) {
            $md[] = '- `' . $file . '`: ' . str_replace('|', '\\|', $msg);
        }

        $md[] = '';
        $md[] = '</details>';
    }

    file_put_contents((string) $options['summary'], implode("\n", $md) . "\n", FILE_APPEND);
}

if (isset($options['json'])) {
    $toList = static fn (array $items) => array_map(
        static fn ($i) => ['file' => $i[0], 'line' => $i[1], 'message' => $i[2]],
        $items
    );

    file_put_contents((string) $options['json'], json_encode([
        'reference' => $ref !== null ? basename($ref) : null,
        'target'    => $target,
        'stats'     => $stats,
        'coverage'  => $ref !== null ? (float) coverage($stats) : null,
        'errors'    => $toList($errors),
        'warnings'  => $toList($warnings),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}

if ($errors || ($strict && $warnings)) {
    exit(1);
}

exit(0);
