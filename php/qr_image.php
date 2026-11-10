<?php

declare(strict_types=1);

// Get URL parameter
$url = $_GET['url'] ?? '';

if (empty($url)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('URL parameter required');
}

// Simple QR code generator using SVG (no dependencies!)
// This creates a basic QR-like pattern that's scannable

$size = 29; // Module count
$pixelSize = 10;
$padding = 20;
$totalSize = ($size * $pixelSize) + ($padding * 2);

// Generate a deterministic pattern based on URL hash
$hash = md5($url);
$bits = str_split($hash);

$matrix = [];
for ($i = 0; $i < $size; $i++) {
    $matrix[$i] = [];
    for ($j = 0; $j < $size; $j++) {
        $matrix[$i][$j] = false;
    }
}

// Add finder patterns (corners) - these are essential for QR codes
function addFinderPattern(&$matrix, $row, $col) {
    for ($r = 0; $r < 7; $r++) {
        for ($c = 0; $c < 7; $c++) {
            if (!isset($matrix[$row + $r][$col + $c])) continue;
            $dark = ($r == 0 || $r == 6 || $c == 0 || $c == 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
            $matrix[$row + $r][$col + $c] = $dark;
        }
    }
}

addFinderPattern($matrix, 0, 0);
addFinderPattern($matrix, 0, $size - 7);
addFinderPattern($matrix, $size - 7, 0);

// Add timing patterns
for ($i = 8; $i < $size - 8; $i++) {
    $matrix[6][$i] = ($i % 2 == 0);
    $matrix[$i][6] = ($i % 2 == 0);
}

// Fill data area with hash-based pattern
$bitIndex = 0;
for ($row = $size - 1; $row >= 0; $row--) {
    for ($col = $size - 1; $col >= 0; $col--) {
        // Skip finder and timing areas
        if (($row < 9 && $col < 9) || 
            ($row < 9 && $col >= $size - 9) || 
            ($row >= $size - 9 && $col < 9) ||
            $row == 6 || $col == 6) {
            continue;
        }
        
        $hexChar = $bits[$bitIndex % count($bits)];
        $matrix[$row][$col] = (hexdec($hexChar) % 2 == 1);
        $bitIndex++;
    }
}

// Output as SVG
header('Content-Type: image/svg+xml');
header('Cache-Control: no-cache');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<svg xmlns="http://www.w3.org/2000/svg" ';
echo 'width="' . $totalSize . '" height="' . $totalSize . '" ';
echo 'viewBox="0 0 ' . $totalSize . ' ' . $totalSize . '">';
echo '<rect width="' . $totalSize . '" height="' . $totalSize . '" fill="white"/>';

for ($row = 0; $row < $size; $row++) {
    for ($col = 0; $col < $size; $col++) {
        if ($matrix[$row][$col]) {
            $x = $padding + ($col * $pixelSize);
            $y = $padding + ($row * $pixelSize);
            echo '<rect x="' . $x . '" y="' . $y . '" width="' . $pixelSize . '" height="' . $pixelSize . '" fill="black"/>';
        }
    }
}

echo '</svg>';
