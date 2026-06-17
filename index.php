<?php

header('Content-Type: application/json');

$data = [
    'php_version' => PHP_VERSION,
    'imagick' => extension_loaded('imagick'),
    'proc_open' => function_exists('proc_open'),
    'exec' => function_exists('exec'),
    'shell_exec' => function_exists('shell_exec'),
];

if (function_exists('shell_exec')) {
    $data['pdftotext'] = trim(shell_exec('which pdftotext 2>/dev/null'));
    $data['pdfimages'] = trim(shell_exec('which pdfimages 2>/dev/null'));
    $data['ghostscript'] = trim(shell_exec('which gs 2>/dev/null'));
    $data['imagemagick'] = trim(shell_exec('which convert 2>/dev/null'));
}

echo json_encode($data, JSON_PRETTY_PRINT);
