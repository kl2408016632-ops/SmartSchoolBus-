<!-- Feedback Modal -->
<?php include '../includes/feedback_modal.php'; ?>

<?php
if (empty($_SESSION['feedback_csrf'])) {
    $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
}
?>

<script>
// Intercept logout link to show feedback modal
document.addEventListener('DOMContentLoaded', function() {
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
    
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Only intercept if not already skipping
            if (!this.href.includes('skip=1')) {
                e.preventDefault();
                
                // Get CSRF token
                const csrfToken = '<?= $_SESSION['feedback_csrf'] ?? '' ?>';
                
                // Show feedback modal
                showFeedbackModal(csrfToken);
            }
        });
    });
});
</script>
