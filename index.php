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

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!is_dir($extractDir)) {
    mkdir($extractDir, 0777, true);
}

$pdfName = uniqid('pdf_') . '.pdf';
$pdfPath = $uploadDir . $pdfName;

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfPath)) {
    exit(json_encode([
        'status' => false,
        'message' => 'Upload failed'
    ]));
}

$prefix = $extractDir . uniqid('img_');

exec(
    "pdfimages -all " .
    escapeshellarg($pdfPath) . " " .
    escapeshellarg($prefix)
);

$images = glob($prefix . '*');

if (!$images) {
    exit(json_encode([
        'status' => false,
        'message' => 'No images extracted'
    ]));
}

$photo = null;
$signature = null;

$largestArea = 0;
$smallestArea = PHP_INT_MAX;

foreach ($images as $img) {

    $info = @getimagesize($img);

    if (!$info) {
        continue;
    }

    $width = $info[0];
    $height = $info[1];

    $area = $width * $height;

    if ($area > $largestArea) {
        $largestArea = $area;
        $photo = $img;
    }

    if ($area < $smallestArea) {
        $smallestArea = $area;
        $signature = $img;
    }
}

if (!$photo || !$signature) {
    exit(json_encode([
        'status' => false,
        'message' => 'Photo or signature not found'
    ]));
}

$signatureExt = pathinfo($signature, PATHINFO_EXTENSION);

$fixedSignature =
    dirname($signature) .
    '/signature_' .
    uniqid() .
    '.png';

exec(
    "magick " .
    escapeshellarg($signature) .
    " -negate -threshold 50% " .
    escapeshellarg($fixedSignature)
);

$baseUrl =
    ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://')
    . $_SERVER['HTTP_HOST'];

$photoUrl = str_replace(__DIR__, '', $photo);
$signatureUrl = str_replace(__DIR__, '', $fixedSignature);

echo json_encode([
    'status' => true,
    'photo' => $baseUrl . $photoUrl,
    'signature' => $baseUrl . $signatureUrl
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
