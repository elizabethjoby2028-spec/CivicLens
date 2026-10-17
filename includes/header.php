<?php
/**
 * Shared Header Template
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/NotificationService.php';

$page_title = isset($page_title) ? $page_title . ' — ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$current_page = basename($_SERVER['PHP_SELF']);

// Check for unread notifications for logged in citizen
$unread_notifications_count = 0;
if (is_citizen_logged_in()) {
    $unread_notifications_count = NotificationService::getUnreadCount('citizen', current_citizen_id());
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="CivicLens — Empowering citizens to report, track, and resolve local civic issues securely and transparently.">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>

<header class="navbar">
    <div class="container navbar-inner">
        <a href="<?= url('index.php') ?>" class="brand-link" title="<?= e(APP_NAME) ?>">
            <div class="brand-logo-icon">🏛️</div>
            <div>
                <div><?= e(__('app_name')) ?></div>
                <span class="brand-tagline"><?= e(__('tagline')) ?></span>
            </div>
        </a>

        <nav>
            <ul class="nav-links">
                <?php if (is_citizen_logged_in()): ?>
                    <li><a href="<?= url('dashboard.php') ?>" class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>"><?= e(__('dashboard')) ?></a></li>
                    <li><a href="<?= url('report_issue.php') ?>" class="nav-link <?= ($current_page === 'report_issue.php') ? 'active' : '' ?>">+ <?= e(__('report_issue')) ?></a></li>
                    <li><a href="<?= url('track_complaint.php') ?>" class="nav-link <?= ($current_page === 'track_complaint.php') ? 'active' : '' ?>"><?= e(__('track_complaint')) ?></a></li>
                    <li>
                        <a href="<?= url('notifications.php') ?>" class="nav-link <?= ($current_page === 'notifications.php') ? 'active' : '' ?>">
                            🔔 <?= e(__('notifications')) ?>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span style="background-color: var(--danger); color: #fff; border-radius: 9999px; padding: 2px 7px; font-size: 0.72rem; font-weight: 700; margin-left: 3px;">
                                    <?= $unread_notifications_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php elseif (is_admin_logged_in()): ?>
                    <li><a href="<?= url('admin_dashboard.php') ?>" class="nav-link <?= ($current_page === 'admin_dashboard.php') ? 'active' : '' ?>">Admin Panel</a></li>
                    <li><a href="<?= url('admin_ads.php') ?>" class="nav-link <?= ($current_page === 'admin_ads.php') ? 'active' : '' ?>">Advertisements</a></li>
                <?php elseif (is_authority_logged_in()): ?>
                    <li><a href="<?= url('authority_dashboard.php') ?>" class="nav-link <?= ($current_page === 'authority_dashboard.php') ? 'active' : '' ?>">Authority Panel</a></li>
                    <li><a href="<?= url('authority_subscription.php') ?>" class="nav-link <?= ($current_page === 'authority_subscription.php') ? 'active' : '' ?>">Subscription</a></li>
                <?php elseif (is_business_logged_in()): ?>
                    <li><a href="<?= url('business_dashboard.php') ?>" class="nav-link <?= ($current_page === 'business_dashboard.php') ? 'active' : '' ?>">Business Dashboard</a></li>
                <?php else: ?>
                    <li><a href="<?= url('index.php') ?>" class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= url('portal.php') ?>" class="nav-link <?= ($current_page === 'portal.php') ? 'active' : '' ?>"><?= e(__('citizen_portal')) ?></a></li>
                    <li><a href="<?= url('track_complaint.php') ?>" class="nav-link <?= ($current_page === 'track_complaint.php') ? 'active' : '' ?>"><?= e(__('track_complaint')) ?></a></li>
                    <li><a href="<?= url('anonymous.php') ?>" class="nav-link <?= ($current_page === 'anonymous.php') ? 'active' : '' ?>"><?= e(__('anonymous_complaint')) ?></a></li>
                    <li><a href="<?= url('authority_login.php') ?>" class="nav-link <?= ($current_page === 'authority_login.php') ? 'active' : '' ?>">🏛️ Authority</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="nav-actions">
            <!-- Language Switcher -->
            <div class="lang-selector">
                <a href="?lang=en" class="lang-btn <?= (current_lang() === 'en') ? 'active' : '' ?>" title="English">EN</a>
                <a href="?lang=ml" class="lang-btn <?= (current_lang() === 'ml') ? 'active' : '' ?>" title="Malayalam">മലയാളം</a>
            </div>

            <!-- Auth Buttons -->
            <?php if (is_citizen_logged_in()): ?>
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--navy-blue); background: #e0f2fe; padding: 0.4rem 0.8rem; border-radius: 9999px;">
                    👤 <?= e($_SESSION['citizen_name']) ?>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn-secondary btn-sm"><?= e(__('logout')) ?></a>
            <?php elseif (is_admin_logged_in()): ?>
                <span style="font-size: 0.85rem; font-weight: 600; color: #fff; background: var(--primary-navy); padding: 0.4rem 0.8rem; border-radius: 9999px;">
                    🛡️ <?= e($_SESSION['admin_username']) ?>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn-secondary btn-sm"><?= e(__('logout')) ?></a>
            <?php elseif (is_authority_logged_in()): ?>
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--navy-light); background: #eff6ff; padding: 0.4rem 0.8rem; border-radius: 9999px;">
                    🏛️ <?= e($_SESSION['authority_name']) ?>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn-secondary btn-sm"><?= e(__('logout')) ?></a>
            <?php elseif (is_business_logged_in()): ?>
                <span style="font-size: 0.85rem; font-weight: 600; color: #047857; background: #ecfdf5; padding: 0.4rem 0.8rem; border-radius: 9999px;">
                    💼 <?= e($_SESSION['business_name']) ?>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn-secondary btn-sm"><?= e(__('logout')) ?></a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>" class="btn btn-outline btn-sm"><?= e(__('login')) ?></a>
                <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm"><?= e(__('register')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="container">
        <!-- Flash Alerts -->
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <span>✓ <?= e($msg) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-error alert-dismissible">
                <span>⚠️ <?= e($msg) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg = flash('warning')): ?>
            <div class="alert alert-warning alert-dismissible">
                <span>⚡ <?= e($msg) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg = flash('info')): ?>
            <div class="alert alert-info alert-dismissible">
                <span>ℹ️ <?= e($msg) ?></span>
            </div>
        <?php endif; ?>
