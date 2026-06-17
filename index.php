<?php

header('Content-Type: application/json');

if (!isset($_FILES['pdf'])) {
    exit(json_encode([
        'status' => false,
        'message' => 'PDF required'
    ]));
}

$uploadDir = __DIR__ . '/uploads/';
$extractDir = __DIR__ . '/extract/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($extractDir)) mkdir($extractDir, 0777, true);

$pdfFile = $uploadDir . uniqid() . '.pdf';

move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfFile);

$prefix = $extractDir . uniqid('img_');

exec("pdfimages -all " . escapeshellarg($pdfFile) . " " . escapeshellarg($prefix));

$images = glob($extractDir . '*');

if (!$images) {
    exit(json_encode([
        'status' => false,
        'message' => 'No images found'
    ]));
}

$photo = null;
$signature = null;

$maxArea = 0;
$minArea = PHP_INT_MAX;

foreach ($images as $img) {

    $size = @getimagesize($img);

    if (!$size) continue;

    $area = $size[0] * $size[1];

    if ($area > $maxArea) {
        $maxArea = $area;
        $photo = $img;
    }

    if ($area < $minArea) {
        $minArea = $area;
        $signature = $img;
    }
}

$baseUrl = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https://' : 'http://'
) . $_SERVER['HTTP_HOST'];

echo json_encode([
    'status' => true,
    'photo' => $photo ? $baseUrl . str_replace(__DIR__, '', $photo) : null,
    'signature' => $signature ? $baseUrl . str_replace(__DIR__, '', $signature) : null
], JSON_PRETTY_PRINT); 
