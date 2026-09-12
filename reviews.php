<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = $config['allowed_origins'] ?? [];

if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, OPTIONS');
    respond(405, ['success' => false, 'error' => ['message' => 'Method Not Allowed']]);
}

$apiKey = (string)($config['google_api_key'] ?? '');
$placeId = (string)($config['place_id'] ?? '');

if ($apiKey === '' || str_contains($apiKey, 'TEST_KEY')) {
    respond(500, ['success' => false, 'error' => ['message' => 'Google API key is not configured.']]);
}

if ($placeId === '') {
    respond(500, ['success' => false, 'error' => ['message' => 'Google Place ID is not configured.']]);
}

if (!preg_match('/^[A-Za-z0-9:_-]+$/', $placeId)) {
    respond(500, ['success' => false, 'error' => ['message' => 'Google Place ID is invalid.']]);
}

$url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId);

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fetchPlaceData(string $url, string $apiKey): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: id,displayName,formattedAddress,rating,reviews,googleMapsUri',
            'Accept-Language: it',
            'User-Agent: NinjaReviews/1.0',
        ],
    ]);

    $response = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlErrNo !== 0) {
        throw new RuntimeException('Network error while fetching Google Places data: ' . $curlErr);
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from Google Places API.');
    }

    if ($httpStatus >= 400) {
        if ($httpStatus === 429) {
            throw new RuntimeException('Google ha temporaneamente limitato le richieste.');
        }
        if ($httpStatus === 401 || $httpStatus === 403) {
            throw new RuntimeException('La Google API Key non Ã¨ autorizzata.');
        }
        throw new RuntimeException('Google Places API non ha accettato la richiesta.');
    }

    return $data;
}

function normalizeReview(array $review): array
{
    $author = trim((string)($review['authorAttribution']['displayName'] ?? ''));
    $photo = $review['authorAttribution']['photoUri'] ?? null;
    $authorUri = $review['authorAttribution']['uri'] ?? null;
    $rating = isset($review['rating']) ? (int)$review['rating'] : null;
    $text = $review['originalText']['text'] ?? ($review['text'] ?? '');
    $publishTime = $review['publishTime'] ?? null;
    $relative = $review['relativePublishTimeDescription'] ?? null;
    $mapsUri = $review['googleMapsUri'] ?? null;

    return [
        'author_name' => $author,
        'author_url' => $authorUri,
        'author_photo' => $photo,
        'rating' => $rating,
        'text' => $text,
        'time' => formatPublishTime($publishTime),
        'relative_time' => $relative,
        'review_url' => $mapsUri,
    ];
}

function formatPublishTime(?string $publishTime): ?string
{
    if (!$publishTime) {
        return null;
    }

    try {
        return (new DateTimeImmutable($publishTime))->format('d/m/Y H:i');
    } catch (Throwable) {
        return null;
    }
}

try {
    $placeData = fetchPlaceData($url, $apiKey);
} catch (Throwable $e) {
    respond(502, [
        'success' => false,
        'error' => ['message' => 'Non Ã¨ stato possibile caricare le recensioni.'],
    ]);
}

$rawReviews = $placeData['reviews'] ?? [];

if (!is_array($rawReviews) || count($rawReviews) === 0) {
    respond(404, [
        'success' => false,
        'error' => ['message' => 'No reviews found.'],
    ]);
}
$businessName = $placeData['displayName']['text'] ?? 'Unknown Business';
$formattedAddress = $placeData['formattedAddress'] ?? 'Unknown Address';
$reviews = array_map('normalizeReview', $rawReviews);

respond(200, [
    'success' => true,
    'count' => count($reviews),
    'business_name' => $businessName,
    'formatted_address' => $formattedAddress,
    'reviews' => $reviews,
]);
