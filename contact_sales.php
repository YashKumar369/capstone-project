<?php
session_start();
require_once 'config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employer') {
    header("Location: login.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_inquiry') {
    $employer_id = $_SESSION['user_id'];
    try {
        // Find Admin ID for notification
        $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin' ORDER BY user_id ASC LIMIT 1");
        $admin_stmt->execute();
        $admin_id = $admin_stmt->fetchColumn();

        if ($admin_id) {
            $notif = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id) VALUES (?, ?, 'sales_inquiry', 0)");
            $notif->execute([$admin_id, $employer_id]);
        }
        
        $_SESSION['success_message'] = "Your inquiry has been sent! Our team will be in touch shortly.";
        header("Location: employer_dashboard.php?tab=overview");
        exit;
    } catch (PDOException $e) {
        $error = "Failed to send inquiry: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <h1 class="fw-bold display-5">Scale Your Global Talent Strategy</h1>
                <p class="lead text-muted">Join industry leaders who trust JBook for their enterprise hiring needs.</p>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="send_inquiry">

                        <!-- Firmographics -->
                        <h5 class="text-primary mb-3">Company Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="company_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Company Size</label>
                                <select class="form-select" name="company_size" required>
                                    <option value="">Select size...</option>
                                    <option>1-50 employees</option>
                                    <option>51-200 employees</option>
                                    <option>201-500 employees</option>
                                    <option>500+ employees</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Industry</label>
                                <select class="form-select" name="industry" required>
                                    <option value="">Select industry...</option>
                                    <option>Technology</option>
                                    <option>Finance</option>
                                    <option>Healthcare</option>
                                    <option>Education</option>
                                    <option>Retail</option>
                                    <option>Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <h5 class="text-primary mb-3">Requirements</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Expected Hiring Volume (Yearly)</label>
                                <select class="form-select" name="hiring_volume" required>
                                    <option value="">Select volume...</option>
                                    <option>1-10 hires</option>
                                    <option>11-50 hires</option>
                                    <option>50+ hires</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Primary Goal</label>
                                <select class="form-select" name="goal">
                                    <option>Speed of hiring</option>
                                    <option>Quality of candidates</option>
                                    <option>Employer branding</option>
                                    <option>Cost reduction</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="integration">
                                    <label class="form-check-label">We need ATS Integration / API Access</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sso">
                                    <label class="form-check-label">We require SSO (Single Sign-On)</label>
                                </div>
                            </div>
                        </div>

                        <!-- Identity -->
                        <h5 class="text-primary mb-3">Your Information</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Job Title</label>
                                <input type="text" class="form-control" name="job_title" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Work Email</label>
                                <input type="email" class="form-control" name="work_email" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Request Custom Quote</button>
                            <a href="employer_dashboard.php?tab=pricing" class="btn btn-link text-decoration-none text-muted">Back to Pricing</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
