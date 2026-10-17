<?php
/**
 * CivicLens — Seed Administrators for Each Category
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$admins = [
    [
        'username' => 'superadmin',
        'email' => 'superadmin@civiclens.gov',
        'name' => 'Chief Municipal Superadmin',
        'role' => 'superadmin',
        'category' => null,
        'pass' => 'Super@admin2026'
    ],
    [
        'username' => 'admin_water',
        'email' => 'water.admin@civiclens.gov',
        'name' => 'Water Works Department Admin',
        'role' => 'admin',
        'category' => 'Water',
        'pass' => 'Water@admin2026'
    ],
    [
        'username' => 'admin_road',
        'email' => 'road.admin@civiclens.gov',
        'name' => 'Roads & Infrastructure Admin',
        'role' => 'admin',
        'category' => 'Road',
        'pass' => 'Road@admin2026'
    ],
    [
        'username' => 'admin_food',
        'email' => 'food.admin@civiclens.gov',
        'name' => 'Food Safety & Hygiene Admin',
        'role' => 'admin',
        'category' => 'Food',
        'pass' => 'Food@admin2026'
    ],
    [
        'username' => 'admin_waste',
        'email' => 'waste.admin@civiclens.gov',
        'name' => 'Solid Waste & Sanitation Admin',
        'role' => 'admin',
        'category' => 'Waste',
        'pass' => 'Waste@admin2026'
    ],
    [
        'username' => 'admin_thoofan',
        'email' => 'thoofan.admin@civiclens.gov',
        'name' => 'Anti-Narcotics (Thoofan) Admin',
        'role' => 'admin',
        'category' => 'Drugs / Substance Abuse (Thoofan)',
        'pass' => 'Thoofan@admin2026'
    ],
    [
        'username' => 'admin_other',
        'email' => 'other.admin@civiclens.gov',
        'name' => 'General Civic Issues Admin',
        'role' => 'admin',
        'category' => 'Other Issues',
        'pass' => 'Other@admin2026'
    ]
];

foreach ($admins as $a) {
    $hash = password_hash($a['pass'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO administrators (username, email, name, password_hash, role, category, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash),
            name = VALUES(name),
            category = VALUES(category),
            role = VALUES(role),
            is_active = 1
    ");
    $stmt->execute([$a['username'], $a['email'], $a['name'], $hash, $a['role'], $a['category']]);
    echo "Seeded: {$a['username']} [Category: " . ($a['category'] ?? 'All') . "]\n";
}

echo "All 7 administrators created successfully!\n";
