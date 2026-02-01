<?php
session_start();
// Basic support contact form
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);

    if ($name && $email && $message) {
        // Mock email sending
        // mail("support@jbook.com", "Support Request: $subject", $message, "From: $email");
        $success = "Thank you! Your message has been sent to our support team. We will get back to you shortly.";
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 mb-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-headset me-2"></i> Contact Support</h3>
                </div>
                <div class="card-body p-4">
                    <?php if($success): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-2x mb-3"></i><br>
                            <?php echo $success; ?>
                            <div class="mt-3">
                                <a href="login.php" class="btn btn-outline-success">Return to Login</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <p class="text-muted mb-4">Having trouble with your account? Fill out the form below and we'll help you resolve the issue.</p>

                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <select name="subject" class="form-select">
                                    <option value="Account Access">Account Access / Login Issue</option>
                                    <option value="Account Banned">Appeal Account Ban</option>
                                    <option value="Technical Issue">Technical Bug</option>
                                    <option value="Other">Other Inquiry</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea name="message" class="form-control" rows="5" required placeholder="Describe your issue..."></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted text-center">
                    <a href="login.php" class="text-decoration-none">&larr; Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
