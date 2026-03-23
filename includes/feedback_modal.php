<?php
/**
 * Feedback Modal Component
 * Shows popup feedback form when staff/driver clicks logout
 */
?>
<!-- Feedback Modal -->
<div id="feedbackModal" class="feedback-modal" style="display: none;">
    <div class="feedback-modal-overlay"></div>
    <div class="feedback-modal-content">
        <div class="feedback-modal-header">
            <h2>📊 Help Us Improve</h2>
            <p>How was your experience today?</p>
        </div>
        
        <form id="feedbackForm" method="POST">
            <input type="hidden" name="csrf_token" id="feedback_csrf" value="">
            <input type="hidden" name="submit_feedback" value="1">
            
            <div class="feedback-question">
                <label>How likely are you to recommend this system? *</label>
                <div class="rating-scale">
                    <div class="rating-labels">
                        <span>Not Likely</span>
                        <span>Very Likely</span>
                    </div>
                    <div class="rating-buttons">
                        <?php for ($i = 0; $i <= 10; $i++): ?>
                            <button type="button" class="rating-btn" data-rating="<?= $i ?>"><?= $i ?></button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="selectedRating" required>
                </div>
            </div>
            
            <div class="feedback-comment">
                <label>Any comments or suggestions? (Optional)</label>
                <textarea name="message" rows="4" placeholder="Tell us what you think..."></textarea>
            </div>
            
            <div class="feedback-actions">
                <button type="button" class="btn-skip" onclick="skipFeedback()">
                    Skip
                </button>
                <button type="submit" class="btn-submit">
                    Submit Feedback
                </button>
            </div>
        </form>
        
        <div id="feedbackSuccess" style="display: none; text-align: center; padding: 30px;">
            <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
            <h3>Thank You!</h3>
            <p>Your feedback helps us improve the system.</p>
            <p style="font-size: 14px; color: #666; margin-top: 20px;">Redirecting to login...</p>
        </div>
    </div>
</div>

<style>
.feedback-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.feedback-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}

.feedback-modal-content {
    position: relative;
    background: white;
    border-radius: 20px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.feedback-modal-header {
    text-align: center;
    margin-bottom: 30px;
}

.feedback-modal-header h2 {
    font-size: 28px;
    color: #333;
    margin: 0 0 10px 0;
}

.feedback-modal-header p {
    color: #666;
    margin: 0;
    font-size: 16px;
}

.feedback-question {
    margin-bottom: 25px;
}

.feedback-question label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    font-size: 15px;
}

.rating-scale {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
}

.rating-labels {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.rating-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}

.rating-btn {
    width: 45px;
    height: 45px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    color: #666;
    cursor: pointer;
    transition: all 0.2s ease;
}

.rating-btn:hover {
    border-color: #667eea;
    color: #667eea;
    transform: scale(1.1);
}

.rating-btn.selected {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: white;
    transform: scale(1.15);
}

.feedback-comment {
    margin-bottom: 25px;
}

.feedback-comment label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 10px;
    font-size: 15px;
}

.feedback-comment textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    transition: border-color 0.2s;
}

.feedback-comment textarea:focus {
    outline: none;
    border-color: #667eea;
}

.feedback-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn-skip, .btn-submit {
    padding: 14px 30px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.btn-skip {
    background: #f1f5f9;
    color: #666;
}

.btn-skip:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}

.btn-submit {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    min-width: 180px;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<script>
// Feedback Modal JavaScript
let selectedRating = null;

// Rating button click handler
document.addEventListener('DOMContentLoaded', function() {
    const ratingButtons = document.querySelectorAll('.rating-btn');
    
    ratingButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            selectedRating = this.getAttribute('data-rating');
            document.getElementById('selectedRating').value = selectedRating;
            
            // Update UI
            ratingButtons.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
});

// Show feedback modal
function showFeedbackModal(csrfToken) {
    document.getElementById('feedback_csrf').value = csrfToken;
    document.getElementById('feedbackModal').style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
}

// Skip feedback and logout
function skipFeedback() {
    if (confirm('Are you sure you want to skip feedback?')) {
        // Perform actual logout
        window.location.href = '<?= SITE_URL ?>/logout.php?skip=1';
    }
}

// Handle feedback form submission
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!selectedRating) {
        alert('Please select a rating before submitting.');
        return;
    }
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    fetch('<?= SITE_URL ?>/api/submit_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            document.getElementById('feedbackForm').style.display = 'none';
            document.getElementById('feedbackSuccess').style.display = 'block';
            
            // Redirect to logout after 2 seconds
            setTimeout(() => {
                window.location.href = '<?= SITE_URL ?>/logout.php?skip=1';
            }, 2000);
        } else {
            alert('Error: ' + (data.message || 'Failed to submit feedback'));
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Feedback';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Feedback';
    });
});
</script>
