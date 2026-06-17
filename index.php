<?php

header('Content-Type: application/json');

if (!isset($_FILES['pdf'])) {
    die(json_encode([
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

$pdfFile = $uploadDir . uniqid('pdf_') . '.pdf';

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfFile)) {
    die(json_encode([
        'status' => false,
        'message' => 'Upload failed'
    ]));
}

$prefix = $extractDir . uniqid('img_');

exec(
    "pdfimages -all " .
    escapeshellarg($pdfFile) . " " .
    escapeshellarg($prefix)
);

$images = glob($prefix . '*');

if (!$images) {
    die(json_encode([
        'status' => false,
        'message' => 'No images extracted'
    ]));
}

$photo = null;
$signature = null;

$largestArea = 0;
$smallestArea = PHP_INT_MAX;

foreach ($images as $img) {

    $size = @getimagesize($img);

    if (!$size) {
        continue;
    }

    $area = $size[0] * $size[1];

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
    die(json_encode([
        'status' => false,
        'message' => 'Photo or Signature not found'
    ]));
}

/*
|--------------------------------------------------------------------------
| Signature Auto Detect & Invert
|--------------------------------------------------------------------------
*/

$fixedSignature =
    $extractDir .
    'signature_' .
    uniqid() .
    '.png';

$invert = false;

try {

    $img = new Imagick($signature);

    $histogram = $img->getImageHistogram();

    $darkPixels = 0;
    $totalPixels = 0;

    foreach ($histogram as $pixel) {

        $color = $pixel->getColor();

        $brightness =
            ($color['r'] +
             $color['g'] +
             $color['b']) / 3;

        $count = $pixel->getColorCount();

        $totalPixels += $count;

        if ($brightness < 50) {
            $darkPixels += $count;
        }
    }

    $darkPercent =
        ($darkPixels / max($totalPixels, 1)) * 100;

    if ($darkPercent > 70) {
        $invert = true;
    }

} catch (Exception $e) {

    copy($signature, $fixedSignature);
}

if ($invert) {

    exec(
        "magick "
        . escapeshellarg($signature)
        . " -negate "
        . escapeshellarg($fixedSignature)
    );

} else {

    copy($signature, $fixedSignature);
}

$protocol =
    (!empty($_SERVER['HTTPS']) &&
     $_SERVER['HTTPS'] !== 'off')
    ? 'https://'
    : 'http://';

$baseUrl =
    $protocol .
    $_SERVER['HTTP_HOST'];

$photoUrl =
    $baseUrl .
    str_replace(__DIR__, '', $photo);

$signatureUrl =
    $baseUrl .
    str_replace(__DIR__, '', $fixedSignature);

echo json_encode([
    'status' => true,
    'photo' => $photoUrl,
    'signature' => $signatureUrl,
    'signature_inverted' => $invert
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
