</div> <!-- End Container -->

<footer class="bg-dark text-white text-center py-4 mt-auto">
    <div class="container">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> JBook. All rights reserved.</p>
        <small class="text-white-50">Social Network for Job Placement</small>
    </div>
</footer>

<!-- AI Resume Builder Widget -->
<style>
    #resume-chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1050;
    }
    #resume-chat-toggle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s;
    }
    #resume-chat-toggle:hover {
        transform: scale(1.1);
    }
    #resume-chat-window {
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 400px;
        max-width: 90vw;
        height: 600px;
        max-height: 80vh;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 1050;
    }
    .chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chat-body {
        padding: 15px;
        flex: 1;
        overflow-y: auto;
        background: #f8f9fa;
    }
    .resume-preview {
        background: white;
        padding: 20px;
        border: 1px solid #ddd;
        font-size: 12px;
    }
</style>

<!-- Only for Job Seekers (or Public) -->
<?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] == 'seeker'): ?>
<div id="resume-chat-widget">
    <button id="resume-chat-toggle" class="btn btn-primary">
        <i class="fas fa-file-alt"></i>
    </button>
</div>

<div id="resume-chat-window" class="d-none">
    <div class="chat-header">
        <h5 class="mb-0"><i class="fas fa-robot"></i> AI Resume Builder</h5>
        <!-- We can add close button logic if needed, or rely on toggle -->
    </div>
    <div class="chat-body">
        <p id="resume-helper-text" class="text-muted small">Fill in your details to generate a professional resume.</p>
        
        <form id="resume-form">
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm" id="resume-name" placeholder="Full Name *" required>
            </div>
            <div class="mb-2">
                <input type="email" class="form-control form-control-sm" id="resume-email" placeholder="Email Contact">
            </div>
            <div class="mb-2">
                <input type="text" class="form-control form-control-sm" id="resume-phone" placeholder="Phone Number">
            </div>
            <div class="mb-2">
                <textarea class="form-control form-control-sm" id="resume-skills" rows="2" placeholder="Skills (e.g. PHP, JavaScript, Teamwork)" required></textarea>
            </div>
            <div class="mb-2">
                <textarea class="form-control form-control-sm" id="resume-experience" rows="3" placeholder="Experience (e.g. Web Dev at XYZ Corp 2020-2022)"></textarea>
            </div>
            <div class="mb-2">
                <textarea class="form-control form-control-sm" id="resume-education" rows="2" placeholder="Education (e.g. BS CS, University of Tech)"></textarea>
            </div>
            <div class="mb-3">
                <textarea class="form-control form-control-sm" id="resume-achievements" rows="2" placeholder="Achievements (Optional)"></textarea>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-sm">Generate Resume</button>
            </div>
        </form>

        <div id="resume-result" class="d-none">
            <div id="resume-preview-content" class="resume-preview mb-3">
                <!-- Resume HTML goes here -->
            </div>
            <div class="d-grid gap-2">
                <button id="download-pdf-btn" class="btn btn-success btn-sm"><i class="fas fa-download"></i> Download PDF</button>
                <button id="back-to-form-btn" class="btn btn-outline-secondary btn-sm">Edit / Try Again</button>
            </div>
        </div>
    </div>
</div>

<!-- Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="js/resume_bot.js"></script>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
