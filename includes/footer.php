<?php
/**
 * Shared Footer Template
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lang.php';
?>
    </div><!-- /.container -->
</main><!-- /.main-content -->

<footer class="footer">
    <div class="container footer-inner">
        <div>
            <div style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem;">
                <?= e(APP_NAME) ?>
            </div>
            <p style="font-size: 0.85rem; color: #94a3b8;"><?= e(APP_TAGLINE) ?> &bull; Modern, Transparent Civic Governance</p>
        </div>

        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem;">
            <a href="<?= url('index.php') ?>">Home</a>
            <a href="<?= url('portal.php') ?>"><?= e(__('citizen_portal')) ?></a>
            <a href="<?= url('track_complaint.php') ?>"><?= e(__('track_complaint')) ?></a>
            <a href="<?= url('anonymous.php') ?>"><?= e(__('anonymous_complaint')) ?></a>
            <a href="<?= url('advertisements.php') ?>"><?= e(__('discover_local')) ?></a>
            <a href="<?= url('authority_login.php') ?>"><?= e(__('authority_login_btn')) ?></a>
            <a href="<?= url('business_login.php') ?>"><?= e(__('business_login_btn')) ?></a>
            <a href="<?= url('admin_login.php') ?>"><?= e(__('admin_login_btn')) ?></a>
        </div>

        <div style="font-size: 0.8rem; color: #64748b;">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
        </div>
    </div>
</footer>

<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
