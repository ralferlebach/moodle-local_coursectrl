<?php
/**
 * Simple Mustache template syntax checker.
 * Usage: php mustache_check.php <templates-directory>
 */
if ($argc < 2) {
    echo "Usage: php mustache_check.php <templates-directory>\n";
    exit(1);
}
$dir = rtrim($argv[1], '/');
$files = glob($dir . '/*.mustache');
if (empty($files)) {
    echo "No .mustache files found in $dir\n";
    exit(0);
}
$errors = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $open  = preg_match_all('/\{\{[#^]\s*(\S+)\s*\}\}/', $content, $m);
    $close = preg_match_all('/\{\{\/\s*\S+\s*\}\}/', $content, $m);
    $name  = basename($file);
    if ($open !== $close) {
        echo "WARN unbalanced tags ($open open, $close close): $name\n";
        $errors++;
    } else {
        echo "OK: $name\n";
    }
}
exit($errors > 0 ? 1 : 0);
