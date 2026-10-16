/**
 * CivicLens — Frontend JavaScript Utilities
 */

document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss flash alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () {
                alert.remove();
            }, 400);
        }, 6000);
    });

    // Image preview for file upload fields
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview-target]');
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            const targetId = input.getAttribute('data-preview-target');
            const previewEl = document.getElementById(targetId);
            if (!previewEl) return;

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewEl.src = e.target.result;
                    previewEl.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        });
    });

    // Confirm actions (e.g. deletion, reopen complaint, close complaint)
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const message = btn.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
