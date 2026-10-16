<?php
/**
 * Application Core Constants & Metadata
 */

return [
    'name' => env('APP_NAME', 'CivicLens'),
    'tagline' => env('APP_TAGLINE', 'Report. Track. Resolve.'),
    'feedback_window_days' => 10,
    'categories' => [
        'Water',
        'Road',
        'Food',
        'Waste',
        'Drugs / Substance Abuse (Thoofan)',
        'Other Issues'
    ],
    'statuses' => [
        'Opened',
        'In Progress',
        'Solved',
        'Reopened',
        'Closed'
    ],
    'priorities' => [
        'Low',
        'Medium',
        'High',
        'Urgent'
    ],
    'service_areas' => [
        'Central Zone',
        'North Zone',
        'South Zone',
        'East Zone',
        'West Zone',
        'Harbor Division',
        'Industrial Corridor'
    ]
];
