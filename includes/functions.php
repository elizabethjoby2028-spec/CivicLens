<?php
/**
 * Global Helper Functions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/**
 * HTML Output Escaping
 */
function e($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique Complaint ID (e.g. CLN-20261017-8A3F)
 */
function generate_complaint_id() {
    $db = get_db();
    do {
        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
        $complaintId = 'CLN-' . $datePart . '-' . $randomPart;

        $stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE complaint_id = ?");
        $stmt->execute([$complaintId]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);

    return $complaintId;
}

/**
 * Get matching authority for a civic complaint category
 */
function get_authority_for_category($category) {
    $db = get_db();
    $catLower = strtolower(trim((string)$category));

    if (strpos($catLower, 'water') !== false) {
        $targetId = 1; // Kerala Water Authority
    } elseif (strpos($catLower, 'road') !== false) {
        $targetId = 2; // PWD Roads & Bridges
    } elseif (strpos($catLower, 'waste') !== false || strpos($catLower, 'garbage') !== false) {
        $targetId = 3; // Solid Waste Management
    } elseif (strpos($catLower, 'food') !== false || strpos($catLower, 'hygiene') !== false) {
        $targetId = 4; // Food Safety
    } elseif (strpos($catLower, 'drug') !== false || strpos($catLower, 'thoofan') !== false || strpos($catLower, 'narcotic') !== false) {
        $targetId = 5; // Anti-Narcotics
    } else {
        $targetId = 6; // General Municipal Engineering
    }

    $stmt = $db->prepare("SELECT id, department, official_name, email FROM authorities WHERE id = ? AND is_active = 1");
    $stmt->execute([$targetId]);
    $auth = $stmt->fetch();
    if ($auth) {
        return $auth;
    }

    // Dynamic search fallback
    return $db->query("SELECT id, department, official_name, email FROM authorities WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch();
}

/**
 * Generate a strong random anonymous tracking credential
 */
function generate_tracking_credential() {
    // 32-character hexadecimal token (128 bits of entropy)
    return bin2hex(random_bytes(16));
}

/**
 * Securely hash tracking credential before storing in DB
 */
function hash_tracking_credential($token) {
    return password_hash($token, PASSWORD_DEFAULT);
}

/**
 * Verify anonymous tracking credential against stored hash
 */
function verify_tracking_credential($token, $hash) {
    if (empty($token) || empty($hash)) {
        return false;
    }
    return password_verify($token, $hash);
}

/**
 * Sanitize text input
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return trim((string)$data);
}

/**
 * Safe redirect to a URL
 */
function redirect($path) {
    if (preg_match('/^https?:\/\//i', $path)) {
        // External redirect or full URL - check host to avoid open redirects
        $targetHost = parse_url($path, PHP_URL_HOST);
        $appHost = parse_url(APP_URL, PHP_URL_HOST);
        if ($targetHost !== $appHost && !empty($targetHost)) {
            $path = APP_URL . '/index.php';
        }
    } else {
        $path = APP_URL . '/' . ltrim($path, '/');
    }
    header("Location: " . $path);
    exit;
}

/**
 * Build application URL
 */
function url($path = '') {
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Flash message helper
 */
function flash($type, $message = null) {
    if ($message === null) {
        if (isset($_SESSION['flash_' . $type])) {
            $msg = $_SESSION['flash_' . $type];
            unset($_SESSION['flash_' . $type]);
            return $msg;
        }
        return null;
    }
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Format datetime nicely
 */
function format_date($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }
    $ts = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $ts);
}

/**
 * Secure file upload handler
 * Validates MIME type, extension, size, and generates a random filename.
 */
function validate_and_save_upload($fileArray, $subDirectory = 'complaints', $maxSizeMb = 5) {
    if (!isset($fileArray['error']) || is_array($fileArray['error'])) {
        return ['success' => false, 'error' => 'Invalid file parameter.'];
    }

    if ($fileArray['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null]; // File is optional
    }

    if ($fileArray['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $fileArray['error']];
    }

    // Size limit check
    $maxBytes = $maxSizeMb * 1024 * 1024;
    if ($fileArray['size'] > $maxBytes) {
        return ['success' => false, 'error' => "File exceeds maximum size of {$maxSizeMb}MB."];
    }

    // Check MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileArray['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and WebP images are allowed.'];
    }

    // Double check extension
    $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
    $validExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $validExts)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    // Generate random, unguessable file name
    $newFileName = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mimeType];
    $targetDir = ROOT_PATH . '/uploads/' . trim($subDirectory, '/');

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $destPath = $targetDir . '/' . $newFileName;

    if (!move_uploaded_file($fileArray['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }

    // Return relative web path
    $relativePath = 'uploads/' . trim($subDirectory, '/') . '/' . $newFileName;
    return ['success' => true, 'path' => $relativePath];
}

/**
 * Get client IP address
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Can be comma separated
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

/**
 * Anonymized IP hash for privacy-safe analytics
 */
function get_anonymized_ip_hash() {
    $ip = get_client_ip();
    return hash('sha256', $ip . '_civiclens_salt_2026');
}

/**
 * Render visual status badge HTML
 */
function render_status_badge($status) {
    $classes = [
        'Opened'      => 'badge-opened',
        'In Progress' => 'badge-inprogress',
        'Solved'      => 'badge-solved',
        'Reopened'    => 'badge-reopened',
        'Closed'      => 'badge-closed',
    ];
    $cls = isset($classes[$status]) ? $classes[$status] : 'badge-default';
    return '<span class="status-badge ' . $cls . '">' . e($status) . '</span>';
}
