<?php
/**
 * Authentication and Role-Based Authorization
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Citizen Helpers
 */
function is_citizen_logged_in() {
    return !empty($_SESSION['citizen_id']);
}

function current_citizen_id() {
    return isset($_SESSION['citizen_id']) ? (int)$_SESSION['citizen_id'] : null;
}

function get_current_citizen() {
    if (!is_citizen_logged_in()) return null;
    $db = get_db();
    $stmt = $db->prepare("SELECT id, name, email, phone, address, created_at FROM citizens WHERE id = ?");
    $stmt->execute([current_citizen_id()]);
    return $stmt->fetch();
}

/**
 * Administrator Helpers
 */
function is_admin_logged_in() {
    return !empty($_SESSION['admin_id']);
}

function current_admin_id() {
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function get_current_admin() {
    if (!is_admin_logged_in()) return null;
    $db = get_db();
    $stmt = $db->prepare("SELECT id, username, email, name, role, is_active FROM administrators WHERE id = ?");
    $stmt->execute([current_admin_id()]);
    return $stmt->fetch();
}

/**
 * Authority Helpers
 */
function is_authority_logged_in() {
    return !empty($_SESSION['authority_id']);
}

function current_authority_id() {
    return isset($_SESSION['authority_id']) ? (int)$_SESSION['authority_id'] : null;
}

function get_current_authority() {
    if (!is_authority_logged_in()) return null;
    $db = get_db();
    $stmt = $db->prepare("SELECT id, department, official_name, designation, email, phone, jurisdiction, is_active FROM authorities WHERE id = ?");
    $stmt->execute([current_authority_id()]);
    return $stmt->fetch();
}

/**
 * Check if an authority has an active paid or verified subscription
 */
function is_authority_subscription_active($authority_id) {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM authority_subscriptions 
        WHERE authority_id = ? 
          AND payment_status = 'active'
          AND (end_date IS NULL OR end_date >= NOW())
    ");
    $stmt->execute([$authority_id]);
    return ($stmt->fetchColumn() > 0);
}

/**
 * Business Helpers
 */
function is_business_logged_in() {
    return !empty($_SESSION['business_id']);
}

function current_business_id() {
    return isset($_SESSION['business_id']) ? (int)$_SESSION['business_id'] : null;
}

function get_current_business() {
    if (!is_business_logged_in()) return null;
    $db = get_db();
    $stmt = $db->prepare("SELECT id, business_name, owner_name, email, phone, category, address, city, website_url, is_verified FROM businesses WHERE id = ?");
    $stmt->execute([current_business_id()]);
    return $stmt->fetch();
}

/**
 * Role-Based Access Control Enforcer
 */
function require_auth($role = 'citizen') {
    switch ($role) {
        case 'citizen':
            if (!is_citizen_logged_in()) {
                flash('error', 'Please log in to access your citizen portal.');
                redirect('login.php');
            }
            break;

        case 'admin':
            if (!is_admin_logged_in()) {
                flash('error', 'Administrator authentication required.');
                redirect('admin_login.php');
            }
            break;

        case 'authority':
            if (!is_authority_logged_in()) {
                flash('error', 'Authorized personnel login required.');
                redirect('authority_login.php');
            }
            // Check if subscription has expired
            if (!is_authority_subscription_active(current_authority_id())) {
                flash('warning', 'Your official subscription has expired or is pending verification. Please renew or contact administration.');
                // Let them access authority_subscription.php but not dashboard updates
                $currentScript = basename($_SERVER['PHP_SELF']);
                if ($currentScript !== 'authority_subscription.php' && $currentScript !== 'logout.php') {
                    redirect('authority_subscription.php');
                }
            }
            break;

        case 'business':
            if (!is_business_logged_in()) {
                flash('error', 'Business account login required.');
                redirect('business_login.php');
            }
            break;

        default:
            http_response_code(403);
            die("Unauthorized role requirement.");
    }
}

/**
 * Rate Limiting for Authentication Endpoints
 */
function check_rate_limit($identifier, $max_attempts = 5, $decay_seconds = 900) {
    $db = get_db();
    $ip = get_client_ip();
    $cutoff = date('Y-m-d H:i:s', time() - $decay_seconds);

    // Clean up older attempts occasionally
    if (rand(1, 20) === 1) {
        $db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM login_attempts 
        WHERE (ip_address = ? OR identifier = ?) 
          AND attempted_at >= ?
    ");
    $stmt->execute([$ip, $identifier, $cutoff]);
    $attempts = (int)$stmt->fetchColumn();

    return ($attempts < $max_attempts);
}

function record_login_attempt($identifier) {
    $db = get_db();
    $ip = get_client_ip();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, identifier) VALUES (?, ?)");
    $stmt->execute([$ip, $identifier]);
}

function clear_login_attempts($identifier) {
    $db = get_db();
    $ip = get_client_ip();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR identifier = ?");
    $stmt->execute([$ip, $identifier]);
}
