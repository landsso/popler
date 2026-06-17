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

$pdfFile = $uploadDir . uniqid('pdf_') . '.pdf';

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $pdfFile)) {
    exit(json_encode([
        'status' => false,
        'message' => 'Upload failed'
    ]));
}

$prefix = $extractDir . uniqid('img_');

exec(
    "pdfimages -all "
    . escapeshellarg($pdfFile)
    . " "
    . escapeshellarg($prefix)
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

foreach ($images as $img) {

    $name = basename($img);

    if (strpos($name, '-000.') !== false) {
        $photo = $img;
    }

    if (strpos($name, '-002.') !== false) {
        $signature = $img;
    }
}

if (!$photo) {
    exit(json_encode([
        'status' => false,
        'message' => 'Photo not found',
        'all_images' => array_map('basename', $images)
    ]));
}

if (!$signature) {
    exit(json_encode([
        'status' => false,
        'message' => 'Signature not found',
        'all_images' => array_map('basename', $images)
    ]));
}

/*
|--------------------------------------------------------------------------
| Auto Invert Signature
|--------------------------------------------------------------------------
*/

$fixedSignature =
    $extractDir .
    'signature_' .
    uniqid() .
    '.png';

$invert = false;

try {

    $im = new Imagick($signature);

    $histogram = $im->getImageHistogram();

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
(
    !empty($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
)
? 'https://'
: 'http://';

$baseUrl =
    $protocol .
    $_SERVER['HTTP_HOST'];

echo json_encode([
    'status' => true,
    'photo' =>
        $baseUrl .
        str_replace(__DIR__, '', $photo),

    'signature' =>
        $baseUrl .
        str_replace(__DIR__, '', $fixedSignature),

    'signature_inverted' => $invert,

    'all_images' => array_map('basename', $images)

], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
