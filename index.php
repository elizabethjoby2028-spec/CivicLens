<?php
/**
 * CivicLens — Role Selection (Welcome Page)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';

$page_title = __('welcome_title');
require_once __DIR__ . '/includes/header.php';
?>

<div class="hero-section">
    <div class="hero-badge">
        <span>🏛️</span>
        <span><?= e(__('tagline')) ?></span>
    </div>

    <h1 class="hero-title"><?= e(__('welcome_title')) ?></h1>
    <p class="hero-subtitle"><?= e(__('welcome_subtitle')) ?></p>

    <!-- Two Primary Role Cards: User vs Admin -->
    <div class="role-grid">
        <!-- Option 1: User / Citizen Portal -->
        <div class="role-card role-card-user">
            <div>
                <div class="role-icon-box">👥</div>
                <h3><?= e(__('citizen_portal')) ?></h3>
                <p><?= e(__('citizen_portal_desc')) ?></p>
                <ul class="role-features">
                    <li>Free verified civic complaints</li>
                    <li>Secure anonymous reporting (₹9 fee)</li>
                    <li>Live status tracking & timeline</li>
                    <li>10-day satisfaction feedback cycle</li>
                </ul>
            </div>
            <div>
                <a href="<?= url('portal.php') ?>" class="btn btn-primary btn-lg btn-block">
                    <?= e(__('enter_citizen_portal')) ?> &rarr;
                </a>
            </div>
        </div>

        <!-- Option 2: Admin / Administrator Access -->
        <div class="role-card role-card-admin">
            <div>
                <div class="role-icon-box">🛡️</div>
                <h3><?= e(__('admin_portal')) ?></h3>
                <p><?= e(__('admin_portal_desc')) ?></p>
                <ul class="role-features">
                    <li>Complaints triage & priority sorting</li>
                    <li>Assign issues to official authorities</li>
                    <li>Verify local business advertising</li>
                    <li>Review reopened complaints & analytics</li>
                </ul>
            </div>
            <div>
                <a href="<?= url('admin_login.php') ?>" class="btn btn-secondary btn-lg btn-block">
                    <?= e(__('admin_login_btn')) ?> &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Secondary Portals (Authority & Business Navigation) -->
    <div class="sub-portals-bar">
        <div>
            <strong>Are you a Department Official?</strong> 
            <a href="<?= url('authority_login.php') ?>" style="margin-left: 0.5rem; font-weight: 600;">
                <?= e(__('authority_login_btn')) ?> &rarr;
            </a>
        </div>
        <div style="color: var(--border-color);">|</div>
        <div>
            <strong>Local Business Owner?</strong> 
            <a href="<?= url('business_login.php') ?>" style="margin-left: 0.5rem; font-weight: 600;">
                <?= e(__('business_login_btn')) ?> &rarr;
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
