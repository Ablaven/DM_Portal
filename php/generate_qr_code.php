<?php
// Generate QR code using QR Server API (free, no rate limits, works perfectly)
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_GET['data'])) {
    http_response_code(400);
    exit('Missing data parameter');
}

$data = $_GET['data'];

// Use QR Server API - reliable and free
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($data);

// Fetch the QR code image
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

$imageData = @file_get_contents($qrApiUrl, false, $context);

if ($imageData === false) {
    // Fallback: try goqr.me API
    $qrApiUrl2 = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($data);
    $imageData = @file_get_contents($qrApiUrl2, false, $context);
    
    if ($imageData === false) {
        http_response_code(500);
        exit('Failed to generate QR code');
    }
}

// Output the image
echo $imageData;
