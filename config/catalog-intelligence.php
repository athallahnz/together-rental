<?php

return [
    'high_confidence_threshold' => (float) env('CATALOG_HIGH_CONFIDENCE_THRESHOLD', 85),

    'ai' => [
        'enabled' => (bool) env('CATALOG_AI_ENABLED', false),
        'provider' => env('CATALOG_AI_PROVIDER'),
    ],
];
