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

$protocol =
(
    !empty($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
)
? 'https://'
: 'http://';

$baseUrl = $protocol . $_SERVER['HTTP_HOST'];

$allImageUrls = [];

foreach ($images as $img) {

    $allImageUrls[] = [
        'file' => basename($img),
        'url'  => $baseUrl . str_replace(__DIR__, '', $img)
    ];
}

/*
|--------------------------------------------------------------------------
| Detect Photo
|--------------------------------------------------------------------------
*/

$photo = null;
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
| Detect Signature
|--------------------------------------------------------------------------
*/

$signature = null;

/* First Priority = 002 */
foreach ($images as $img) {

    $name = basename($img);

    if (strpos($name, '-002.') !== false) {

        $signature = $img;
        break;
    }
}

/* Second Priority = 001 */
if (!$signature) {

    foreach ($images as $img) {

        $name = basename($img);

        if (strpos($name, '-001.') !== false) {

            $signature = $img;
            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Auto Invert Signature
|--------------------------------------------------------------------------
*/

$signatureUrl = null;
$invert = false;

if ($signature) {

    $fixedSignature =
        $extractDir .
        'signature_' .
        uniqid() .
        '.png';

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

        copy(
            $signature,
            $fixedSignature
        );
    }

    $signatureUrl =
        $baseUrl .
        str_replace(__DIR__, '', $fixedSignature);
}

$photoUrl = null;

if ($photo) {

    $photoUrl =
        $baseUrl .
        str_replace(__DIR__, '', $photo);
}

echo json_encode([
    'status' => true,
    'photo' => $photoUrl,
    'signature' => $signatureUrl,
    'signature_inverted' => $invert,
    'all_images' => $allImageUrls
],
JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
