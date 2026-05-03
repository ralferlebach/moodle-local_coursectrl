<?php
// phpcs:ignoreFile
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI tool: reconcile function param tags with actual signatures.
 *
 * Ensures the param tag count in every docblock equals the number of
 * parameters in the corresponding function signature. Uses the actual
 * type hint from the signature (including |null for nullable parameters).
 * Existing descriptions are preserved; surplus tags are removed; missing
 * tags are generated from the signature type.
 *
 * Usage (from plugin root):
 *   php tools/fix_phpdoc.php [--dry-run] [<plugin-dir>]
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$dryrun = in_array('--dry-run', $argv, true);
$plugindir = realpath(__DIR__ . '/..');
foreach ($argv as $arg) {
    if ($arg[0] !== '-' && is_dir($arg)) {
        $plugindir = realpath($arg);
        break;
    }
}

$totalfiles  = 0;
$totalparams = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugindir));
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getRealPath();
    if (strpos($path, DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $n = fix_file($path, $dryrun);
    if ($n > 0) {
        $totalfiles++;
        $totalparams += $n;
        $rel = str_replace($plugindir . DIRECTORY_SEPARATOR, '', $path);
        echo ($dryrun ? '[DRY] ' : '[FIX] ') . $rel . ": adjusted $n param tag(s)\n";
    }
}
echo "\nDone. Files changed: $totalfiles. Param adjustments: $totalparams\n";

/**
 * Reconcile param tags in all function docblocks within one file.
 *
 * @param string $filepath Absolute path to the PHP file.
 * @param bool   $dryrun   When true, report but do not write.
 * @return int Number of docblocks adjusted.
 */
function fix_file(string $filepath, bool $dryrun): int {
    $src = file_get_contents($filepath);
    if ($src === false) {
        return 0;
    }
    $lines = explode("\n", $src);
    $n     = count($lines);
    $total = 0;

    $funcpat = '/^\s+(?:(?:public|protected|private|abstract|static|final)\s+)*function\s+\w+\s*\(/';
    for ($i = $n - 1; $i >= 0; $i--) {
        if (!preg_match($funcpat, $lines[$i])) {
            continue;
        }

        // Collect full function signature (may span multiple lines).
        $sig   = '';
        $depth = 0;
        for ($j = $i; $j < min($i + 15, $n); $j++) {
            $sig   .= ' ' . $lines[$j];
            $depth += substr_count($lines[$j], '(') - substr_count($lines[$j], ')');
            if ($depth === 0 && strpos($sig, '(') !== false) {
                break;
            }
        }
        // Extract parameters with their types from the signature.
        $sigparams = extract_params_typed($sig);

        // Locate the preceding docblock.
        $closepos = $i - 1;
        while ($closepos >= 0 && strpos($lines[$closepos], '*/') === false) {
            $closepos--;
        }
        if ($closepos < 0) {
            continue;
        }
        $openpos = $closepos - 1;
        while ($openpos >= 0 && strpos($lines[$openpos], '/**') === false) {
            $openpos--;
        }
        if ($openpos < 0) {
            continue;
        }

        // Gather existing param info from docblock.
        $docparams   = [];
        $paramorder  = [];
        $curparam    = null;
        for ($k = $openpos; $k <= $closepos; $k++) {
            $s = trim($lines[$k]);
            if (preg_match('/^\*\s+@param\s+(\S[^$]*?)\s+\$(\w+)\s*(.*)$/', $s, $m)) {
                $curparam             = $m[2];
                $docparams[$curparam] = [$m[1], trim($m[3])];
                $paramorder[]         = $curparam;
            } else if (
                $curparam !== null
                && preg_match('/^\*\s+\S/', $s)
                && !preg_match('/^\*\s+@/', $s)
            ) {
                // Continuation line of multi-line param description.
                $docparams[$curparam][1] .= ' ' . ltrim($s, '* ');
            } else if (preg_match('/^\*\s+@/', $s)) {
                $curparam = null;
            }
        }

        $signames = array_keys($sigparams);

        // Check if docblock already matches: same names in same order
        // AND each type is compatible with the signature type.
        if ($paramorder === $signames && params_types_match($sigparams, $docparams)) {
            continue;
        }

        // Determine docblock indentation from the /** opening line.
        preg_match('/^(\s*)/', $lines[$openpos], $im);
        $indent = $im[1];

        // Strip all param lines (and continuations) from docblock copy.
        $newdoc  = [];
        $skipping = false;
        for ($k = $openpos; $k <= $closepos; $k++) {
            $s = trim($lines[$k]);
            if (preg_match('/^\*\s+@param\s+/', $s)) {
                $skipping = true;
                continue;
            }
            if ($skipping) {
                $iscont = preg_match('/^\*\s+\S/', $s) && !preg_match('/^\*\s+@/', $s);
                if ($iscont) {
                    continue;
                }
                $skipping = false;
            }
            $newdoc[] = $lines[$k];
        }

        // Find insertion point (before @return/@throws, otherwise before */).
        $insertidx = count($newdoc) - 1;
        foreach ($newdoc as $mi => $ml) {
            if (preg_match('/\*\s+@(return|throws)/', $ml)) {
                $insertidx = $mi;
                break;
            }
        }

        // Build replacement param lines using signature types.
        $newparams = [];
        foreach ($sigparams as $pname => $sigtype) {
            $desc = '';
            if (isset($docparams[$pname])) {
                // Preserve existing description; use signature type.
                $desc = $docparams[$pname][1];
            }
            $newparams[] = "$indent * @param $sigtype \$$pname $desc";
        }

        array_splice($newdoc, $insertidx, 0, $newparams);
        array_splice($lines, $openpos, $closepos - $openpos + 1, $newdoc);
        $n = count($lines);
        $total++;
    }

    if ($total > 0 && !$dryrun) {
        file_put_contents($filepath, implode("\n", $lines));
    }
    return $total;
}

/**
 * Extract parameters with their PHPDoc-compatible types from a signature.
 *
 * Returns an array keyed by parameter name; each value is the type string
 * ready for use in a param tag (e.g. "string", "array", "MyClass|null").
 *
 * @param string $sig Full function signature source fragment.
 * @return array<string,string> Parameter name => PHPDoc type string.
 */
function extract_params_typed(string $sig): array {
    $start = strpos($sig, '(');
    if ($start === false) {
        return [];
    }
    // Extract content between outer parentheses.
    $depth  = 0;
    $inside = '';
    for ($i = $start; $i < strlen($sig); $i++) {
        if ($sig[$i] === '(') {
            $depth++;
        } else if ($sig[$i] === ')') {
            $depth--;
            if ($depth === 0) {
                break;
            }
        }
        if ($depth > 0 && $i > $start) {
            $inside .= $sig[$i];
        }
    }

    $result = [];
    // Split on commas that are not inside < > (for generic types).
    $parts = preg_split('/,(?![^<>]*>)/', $inside);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        // Remove default value (everything after "=").
        $part = preg_replace('/\s*=\s*.+$/s', '', $part);
        $part = trim($part);

        // Match: [?][type] $name  OR just $name
        if (preg_match('/^(\??)([a-zA-Z_\\\\][a-zA-Z0-9_\\\\|]*)\s+\$([a-zA-Z_]\w*)$/', $part, $m)) {
            $nullable = $m[1] === '?';
            $type     = $m[2];
            $name     = $m[3];
            if ($name === 'this') {
                continue;
            }
            // Build PHPDoc type: nullable → append |null if not already present.
            if ($nullable && strpos($type, '|null') === false && strpos($type, 'null|') === false) {
                $type .= '|null';
            }
            $result[$name] = $type;
        } else if (preg_match('/\$([a-zA-Z_]\w*)$/', $part, $m)) {
            // Untyped parameter — fall back to "mixed".
            if ($m[1] !== 'this') {
                $result[$m[1]] = 'mixed';
            }
        }
    }
    return $result;
}

/**
 * Check whether existing docblock types are compatible with signature types.
 *
 * Returns false if any param has a type mismatch that needs correcting.
 *
 * @param array $sigparams  name => type from signature.
 * @param array $docparams  name => [type, desc] from docblock.
 * @return bool True when all types already match.
 */
function params_types_match(array $sigparams, array $docparams): bool {
    foreach ($sigparams as $name => $sigtype) {
        if (!isset($docparams[$name])) {
            return false;
        }
        $doctype = trim($docparams[$name][0]);
        // Normalise both sides: sort union parts alphabetically.
        $sigparts = explode('|', $sigtype);
        $docparts = explode('|', $doctype);
        sort($sigparts);
        sort($docparts);
        if ($sigparts !== $docparts) {
            return false;
        }
    }
    return true;
}
