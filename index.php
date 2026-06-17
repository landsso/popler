<?php

header('Content-Type: application/json');

if (!isset($_FILES['pdf'])) {
    exit(json_encode([
        'status' => false,
        'message' => 'PDF required'
    ]));
}

$uploadDir = sys_get_temp_dir() . '/pdf_uploads/';
$extractDir = sys_get_temp_dir() . '/pdf_extract/';

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
    // Cleanup
    @unlink($pdfFile);
    @rmdir($extractDir);
    exit(json_encode([
        'status' => false,
        'message' => 'No images extracted'
    ]));
}

// Helper function to convert image to base64
function imageToBase64($filePath) {
    $mime = mime_content_type($filePath);
    $data = file_get_contents($filePath);
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

$allImagesBase64 = [];

foreach ($images as $img) {
    $allImagesBase64[] = [
        'file' => basename($img),
        'base64' => imageToBase64($img)
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

$signatureBase64 = null;
$invert = false;

if ($signature) {

    $fixedSignature = $extractDir . 'signature_' . uniqid() . '.png';

    try {
        $im = new Imagick($signature);
        $histogram = $im->getImageHistogram();
        $darkPixels = 0;
        $totalPixels = 0;

        foreach ($histogram as $pixel) {
            $color = $pixel->getColor();
            $brightness = ($color['r'] + $color['g'] + $color['b']) / 3;
            $count = $pixel->getColorCount();
            $totalPixels += $count;
            if ($brightness < 50) {
                $darkPixels += $count;
            }
        }

        $darkPercent = ($darkPixels / max($totalPixels, 1)) * 100;
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

    $signatureBase64 = imageToBase64($fixedSignature);
}

$photoBase64 = null;
if ($photo) {
    $photoBase64 = imageToBase64($photo);
}

// Cleanup all temporary files
@unlink($pdfFile);
foreach ($images as $img) {
    @unlink($img);
}
@unlink($fixedSignature);
@rmdir($uploadDir);
@rmdir($extractDir);

echo json_encode([
    'status' => true,
    'photo' => $photoBase64,
    'signature' => $signatureBase64,
    'signature_inverted' => $invert,
   // 'all_images' => $allImagesBase64  // পরে প্রয়োজনে আনকমেন্ট করুন
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
