<?php
/**
 * CivicLens — Seed All 6 Category Authorities
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$db = get_db();
$pass_hash = password_hash('Authority@2026', PASSWORD_DEFAULT);

$authorities = [
    [
        'id' => 1,
        'department' => 'Kerala Water Authority (KWA)',
        'official_name' => 'Er. Rajeev Nambiar',
        'designation' => 'Superintending Engineer',
        'email' => 'kwa.officer@civiclens.local',
        'phone' => '+91 9847000101',
        'jurisdiction' => 'District Central & North Water Supply Zones'
    ],
    [
        'id' => 2,
        'department' => 'Public Works Department (PWD Roads)',
        'official_name' => 'Er. Deepa Thomas',
        'designation' => 'Executive Engineer',
        'email' => 'pwd.roads@civiclens.local',
        'phone' => '+91 9847000102',
        'jurisdiction' => 'State Highways & Municipal Major Arteries'
    ],
    [
        'id' => 3,
        'department' => 'Municipal Solid Waste Management (Haritha Karma)',
        'official_name' => 'Biju Narayanan',
        'designation' => 'Chief Sanitation Inspector',
        'email' => 'waste.mgmt@civiclens.local',
        'phone' => '+91 9847000103',
        'jurisdiction' => 'Urban Sanitation Wards 1-45'
    ],
    [
        'id' => 4,
        'department' => 'Food Safety & Hygiene Department',
        'official_name' => 'Dr. Suma Nair',
        'designation' => 'Assistant Food Safety Commissioner',
        'email' => 'food.safety@civiclens.local',
        'phone' => '+91 9847000104',
        'jurisdiction' => 'Municipal Food & Hospitality Establishments'
    ],
    [
        'id' => 5,
        'department' => 'Excise & Anti-Narcotics Special Squad (Operation Thoofan)',
        'official_name' => 'K. Mohandas',
        'designation' => 'Circle Inspector of Excise',
        'email' => 'thoofan.squad@civiclens.local',
        'phone' => '+91 9847000105',
        'jurisdiction' => 'Special Enforcement Narcotics Zone'
    ],
    [
        'id' => 6,
        'department' => 'General Municipal Engineering & Public Amenities',
        'official_name' => 'M. V. Suresh',
        'designation' => 'Municipal Engineer',
        'email' => 'general.officer@civiclens.local',
        'phone' => '+91 9847000106',
        'jurisdiction' => 'Municipal Public Infrastructure & Parks'
    ],
];

foreach ($authorities as $a) {
    $stmt = $db->prepare("
        INSERT INTO authorities (id, department, official_name, designation, email, phone, password_hash, jurisdiction, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            department = VALUES(department),
            official_name = VALUES(official_name),
            designation = VALUES(designation),
            email = VALUES(email),
            phone = VALUES(phone),
            password_hash = VALUES(password_hash),
            jurisdiction = VALUES(jurisdiction),
            is_active = 1
    ");
    $stmt->execute([
        $a['id'],
        $a['department'],
        $a['official_name'],
        $a['designation'],
        $a['email'],
        $a['phone'],
        $pass_hash,
        $a['jurisdiction']
    ]);

    // Ensure active subscription
    $sub = $db->prepare("
        INSERT INTO authority_subscriptions 
        (authority_id, plan_name, amount, payment_status, payment_method, transaction_reference, start_date, end_date, verified_by_admin)
        VALUES (?, 'Annual Official Access', 5000.00, 'active', 'Official Direct Allocation', 'SEED-ACTIVE-ACCESS', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 1)
        ON DUPLICATE KEY UPDATE 
            payment_status = 'active',
            end_date = DATE_ADD(NOW(), INTERVAL 365 DAY)
    ");
    $sub->execute([$a['id']]);

    echo "Seeded Authority: [ID {$a['id']}] {$a['department']} ({$a['email']})\n";
}

echo "All 6 municipal authorities seeded and activated successfully!\n";
