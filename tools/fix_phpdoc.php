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
 * CLI tool: reconcile function @param tags with actual signatures.
 *
 * Ensures the @param tag count in every docblock equals the number of
 * parameters in the corresponding function signature. Existing type
 * hints and descriptions are preserved; surplus tags are removed; missing
 * tags are added as "mixed $name See function signature."
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
        echo ($dryrun ? '[DRY] ' : '[FIX] ') . $rel . ": adjusted $n @param tag(s)\n";
    }
}
echo "\nDone. Files changed: $totalfiles. @param adjustments: $totalparams\n";

/**
 * Reconcile @param tags in all function docblocks within one file.
 *
 * Arguments: string $filepath (absolute path), bool $dryrun (report only).
 * Returns int: number of docblocks adjusted.
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
        $sigparams = extract_params($sig);

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

        // Gather existing @param info from docblock.
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
                // Continuation line of multi-line @param description.
                $docparams[$curparam][1] .= ' ' . ltrim($s, '* ');
            } else if (preg_match('/^\*\s+@/', $s)) {
                $curparam = null;
            }
        }

        if ($paramorder === $sigparams) {
            continue;
        }

        // Determine docblock indentation from the /** opening line.
        preg_match('/^(\s*)/', $lines[$openpos], $im);
        $indent = $im[1];

        // Strip all @param lines (and continuations) from docblock copy.
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

        // Build replacement @param lines.
        $newparams = [];
        foreach ($sigparams as $pname) {
            if (isset($docparams[$pname])) {
                [$type, $desc] = $docparams[$pname];
                $newparams[] = "$indent * @param $type \$$pname $desc";
            } else {
                $newparams[] = "$indent * @param mixed \$$pname See function signature.";
            }
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
 * Extract parameter names from a function signature fragment.
 *
 * Argument: string $sig (source fragment). Returns string[] of names.
 *
 * @param string $sig The function signature source fragment.
 * @return string[] Parameter names without leading dollar sign.
 */
function extract_params(string $sig): array {
    $start = strpos($sig, '(');
    if ($start === false) {
        return [];
    }
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
    preg_match_all('/\$([a-zA-Z_]\w*)/', $inside, $m);
    $params = $m[1] ?? [];
    return array_values(array_filter($params, fn($p) => $p !== 'this'));
}
