<?php

header('Content-Type: application/json');

echo json_encode([
    'php_version' => PHP_VERSION,
    'imagick_extension' => extension_loaded('imagick'),
    'proc_open' => function_exists('proc_open'),
    'exec' => function_exists('exec'),
    'shell_exec' => function_exists('shell_exec'),
    'pdftotext' => trim(shell_exec('which pdftotext')),
    'pdfimages' => trim(shell_exec('which pdfimages')),
    'ghostscript' => trim(shell_exec('which gs')),
    'imagemagick' => trim(shell_exec('which magick')),
], JSON_PRETTY_PRINT);
