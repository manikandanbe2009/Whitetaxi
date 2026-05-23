<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$apiKey = env_value('GOOGLE_MAPS_API_KEY');

if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Google Maps API key is not configured.']);
    exit;
}

$pickupLat = $_GET['p_lat'] ?? '';
$pickupLng = $_GET['p_lng'] ?? '';
$dropLat = $_GET['d_lat'] ?? '';
$dropLng = $_GET['d_lng'] ?? '';

foreach ([$pickupLat, $pickupLng, $dropLat, $dropLng] as $coordinate) {
    if ($coordinate === '' || !is_numeric($coordinate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid coordinates supplied.']);
        exit;
    }
}

$url = 'https://maps.googleapis.com/maps/api/distancematrix/json?units=metric'
    . '&origins=' . rawurlencode($pickupLat . ',' . $pickupLng)
    . '&destinations=' . rawurlencode($dropLat . ',' . $dropLng)
    . '&key=' . rawurlencode($apiKey);

$responseJson = @file_get_contents($url);

if ($responseJson === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Unable to reach Google Maps Distance Matrix.']);
    exit;
}

$response = json_decode($responseJson, true);
$element = $response['rows'][0]['elements'][0] ?? null;

if (($response['status'] ?? '') !== 'OK' || !is_array($element) || ($element['status'] ?? '') !== 'OK') {
    http_response_code(422);
    echo json_encode([
        'error' => 'No route found for the selected pickup and drop locations.',
        'google_status' => $element['status'] ?? $response['status'] ?? 'UNKNOWN',
    ]);
    exit;
}

$distanceKm = ($element['distance']['value'] ?? 0) / 1000;

echo json_encode([
    'distance_km' => round($distanceKm, 2),
]);
