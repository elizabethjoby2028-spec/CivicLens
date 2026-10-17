<?php
/**
 * CivicLens — Submit Verified Citizen Issue
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/NotificationService.php';

// Enforce citizen authentication
require_auth('citizen');

$citizen_id = current_citizen_id();
$app_config = require __DIR__ . '/config/app.php';
$categories = $app_config['categories'];

$errors = [];
$category = '';
$title = '';
$description = '';
$location = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $category = sanitize_input($_POST['category'] ?? '');
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $location = sanitize_input($_POST['location'] ?? '');

    // Server-side validation
    if (empty($category) || !in_array($category, $categories)) {
        $errors[] = 'Please select a valid civic complaint category.';
    }
    if (empty($title) || strlen($title) < 5 || strlen($title) > 200) {
        $errors[] = 'Issue title must be between 5 and 200 characters.';
    }
    if (empty($description) || strlen($description) < 15) {
        $errors[] = 'Please provide a detailed description (at least 15 characters).';
    }
    if (empty($location)) {
        $errors[] = 'Location or landmark is required for officials to locate the problem.';
    }

    // Handle optional photograph upload
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = validate_and_save_upload($_FILES['photo'], 'complaints', 5);
        if (!$uploadResult['success']) {
            $errors[] = $uploadResult['error'];
        } else {
            $photo_path = $uploadResult['path'];
        }
    }

    if (empty($errors)) {
        $db = get_db();
        $complaint_id = generate_complaint_id();

        try {
            $db->beginTransaction();

            // Insert complaint record
            // Automatically route and assign to respective municipal authority based on category
            $assigned_authority = get_authority_for_category($category);
            $assigned_authority_id = $assigned_authority ? (int)$assigned_authority['id'] : null;

            $stmt = $db->prepare("
                INSERT INTO complaints 
                (complaint_id, citizen_id, is_anonymous, category, title, description, location, photo_path, status, priority, assigned_authority_id, assigned_at)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?, 'Opened', 'Medium', ?, NOW())
            ");
            $stmt->execute([
                $complaint_id,
                $citizen_id,
                $category,
                $title,
                $description,
                $location,
                $photo_path,
                $assigned_authority_id
            ]);
            $inserted_db_id = $db->lastInsertId();

            // Insert status history entry
            $hist_stmt = $db->prepare("
                INSERT INTO complaint_status_history 
                (complaint_id, from_status, to_status, actor_type, actor_id, actor_name, note)
                VALUES (?, NULL, 'Opened', 'citizen', ?, ?, ?)
            ");
            $hist_note = "Issue reported by citizen and automatically routed to {$assigned_authority['department']} based on category '{$category}'.";
            $hist_stmt->execute([
                $inserted_db_id,
                $citizen_id,
                $_SESSION['citizen_name'] ?? 'Citizen',
                $hist_note
            ]);

            // Create notification for citizen
            NotificationService::notifyCitizen(
                $citizen_id,
                $inserted_db_id,
                'Complaint Submitted & Assigned',
                "Your complaint {$complaint_id} has been registered and automatically routed to {$assigned_authority['department']}.",
                'submitted'
            );

            // Notify the assigned authority immediately
            if ($assigned_authority_id) {
                NotificationService::notifyAuthority(
                    $assigned_authority_id,
                    $inserted_db_id,
                    'New Issue Assigned (Automatic Routing)',
                    "New complaint {$complaint_id} ({$category}) has been automatically routed to your department for resolution.",
                    'complaint_assigned'
                );
            }

            // Notify administrators
            NotificationService::notifyAdmins(
                $inserted_db_id,
                'New Complaint Registered',
                "New issue {$complaint_id} reported in category '{$category}' (Assigned to: {$assigned_authority['department']}).",
                'submitted'
            );

            $db->commit();

            flash('success', "Complaint {$complaint_id} submitted! Automatically assigned to {$assigned_authority['department']}. Log in as the authority below to inspect and solve this issue.");
            redirect('authority_login.php?dept=' . ($assigned_authority_id ?? 1) . '&ref=' . urlencode($complaint_id));
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error while saving your complaint: ' . (APP_DEBUG ? $e->getMessage() : 'Please try again.');
        }
    }
}

$page_title = __('report_issue') . ' — ' . __('citizen_portal');
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 720px; margin: 1rem auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= url('dashboard.php') ?>" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Dashboard</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📝 <?= e(__('report_issue')) ?></h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="report_issue.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Category Selection -->
            <div class="form-group">
                <label class="form-label" for="category"><?= e(__('category')) ?> <span class="required">*</span></label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">-- Select Issue Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Issue Title -->
            <div class="form-group">
                <label class="form-label" for="title"><?= e(__('title')) ?> <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-control" value="<?= e($title) ?>" placeholder="Brief summary (e.g. Broken water pipe leaking on Main St)" minlength="5" maxlength="200" required>
            </div>

            <!-- Location / Landmark -->
            <div class="form-group">
                <label class="form-label" for="location"><?= e(__('location')) ?> <span class="required">*</span></label>
                <input type="text" id="location" name="location" class="form-control" value="<?= e($location) ?>" placeholder="Exact street, landmark, ward, or GPS coordinate" required>
            </div>

            <!-- Detailed Description -->
            <div class="form-group">
                <label class="form-label" for="description"><?= e(__('description')) ?> <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" rows="5" placeholder="Describe the severity, duration, and details of the problem..." minlength="15" required><?= e($description) ?></textarea>
                <div class="form-hint">Provide clear context to help officials investigate swiftly.</div>
            </div>

            <!-- Optional Photograph -->
            <div class="form-group">
                <label class="form-label" for="photo"><?= e(__('photo')) ?></label>
                <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" data-preview-target="photo_preview">
                <div class="form-hint">Accepted formats: JPG, PNG, WebP (Max 5MB).</div>
                <img id="photo_preview" src="" alt="Preview" style="display: none; max-width: 200px; max-height: 200px; margin-top: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex: 2;">
                    Submit Verified Report &rarr;
                </button>
                <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary btn-lg" style="flex: 1;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
