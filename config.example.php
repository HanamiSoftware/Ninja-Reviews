<?php
// NinjaReviews configuration example.
// The installer generates the real config.php automatically.
return [
    // Keep this key server-side; it is sent only to Google by reviews.php.
    'google_api_key' => '',
    // The installer resolves this value from the selected Google Maps place.
    'place_id' => '',
    // Origins allowed to embed the widget. Use ['*'] only for intentionally public APIs.
    'allowed_origins' => ['https://www.example.com'],
];
