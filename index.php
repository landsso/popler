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
    "pdfimages -all "
    . escapeshellarg($pdfFile)
    . " "
    . escapeshellarg($prefix)
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
}

/*
|--------------------------------------------------------------------------
| Signature Detection
|--------------------------------------------------------------------------
*/

$bestScore = 0;

foreach ($images as $img) {

    if ($img === $photo) {
        continue;
    }

    $size = @getimagesize($img);

    if (!$size) {
        continue;
    }

    $w = $size[0];
    $h = $size[1];

    if ($w < 20 || $h < 10) {
        continue;
    }

    try {

        $im = new Imagick($img);

        $hist = $im->getImageHistogram();

        $visiblePixels = 0;

        foreach ($hist as $pixel) {

            $c = $pixel->getColor();

            $brightness =
                ($c['r'] + $c['g'] + $c['b']) / 3;

            $count = $pixel->getColorCount();

            if (
                $brightness > 20 &&
                $brightness < 235
            ) {
                $visiblePixels += $count;
            }
        }

        if ($visiblePixels > $bestScore) {
            $bestScore = $visiblePixels;
            $signature = $img;
        }

    } catch (Exception $e) {
    }
}

if (!$photo || !$signature) {

    die(json_encode([
        'status' => false,
        'message' => 'Photo or Signature not found',
        'all_images' => array_map('basename', $images)
    ]));
}

/*
|--------------------------------------------------------------------------
| Auto Invert
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

    $hist = $img->getImageHistogram();

    $darkPixels = 0;
    $totalPixels = 0;

    foreach ($hist as $pixel) {

        $c = $pixel->getColor();

        $brightness =
            ($c['r'] +
             $c['g'] +
             $c['b']) / 3;

        $count = $pixel->getColorCount();

        $totalPixels += $count;

        if ($brightness < 40) {
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

    copy(
        $signature,
        $fixedSignature
    );
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

],
JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
