<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    // $salary_range removed from raw post, constructed later
    $type = $_POST['type'];

    if (empty($title) || empty($description) || empty($location)) {
        $error = "Title, description, and location are required.";
    } else {
        try {
            // Construct salary_range string for backward compatibility (e.g. "USD 50000")
            $currency = $_POST['currency'];
            $salary = $_POST['salary'];
            $salary_display = $currency . ' ' . number_format($salary);
            
            // Check Verified Status
            $v_stmt = $pdo->prepare("SELECT is_verified FROM users WHERE user_id = ?");
            $v_stmt->execute([$_SESSION['user_id']]);
            $is_verified = $v_stmt->fetchColumn(); 
            
            // If verified, auto-approve (active), else pending
            $initial_status = $is_verified ? 'active' : 'pending';
            $vacancies = (int)$_POST['vacancies'];

            $stmt = $pdo->prepare("INSERT INTO jobs (employer_id, title, description, location, salary_range, salary, currency, type, status, vacancies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $title, $description, $location, $salary_display, $salary, $currency, $type, $initial_status, $vacancies])) {
                $job_id = $pdo->lastInsertId();
                
                // Notify Followers
                $followers_stmt = $pdo->prepare("SELECT user_id_1 FROM connections WHERE user_id_2 = ? AND status = 'accepted'");
                $followers_stmt->execute([$_SESSION['user_id']]);
                $followers = $followers_stmt->fetchAll();
                
                $notif_sql = "INSERT INTO notifications (recipient_id, actor_id, type, reference_id) VALUES (?, ?, 'new_job', ?)";
                $notif_stmt = $pdo->prepare($notif_sql);
                
                foreach($followers as $f) {
                    $notif_stmt->execute([$f['user_id_1'], $_SESSION['user_id'], $job_id]);
                }

                $success = "Job posted successfully!";
                if ($initial_status == 'pending') {
                    $success .= " It is pending admin approval (Verification required).";
                } else {
                    $success .= " It is now live!";
                }
            } else {
                $error = "Failed to post job.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Post a New Job</h4>
                </div>
                <div class="card-body">
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="post_job.php">
                        <div class="mb-3">
                            <label for="title" class="form-label">Job Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Job Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vacancies" class="form-label">Number of Vacancies</label>
                                <input type="number" class="form-control" id="vacancies" name="vacancies" min="1" value="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salary Amount</label>
                                <div class="input-group">
                                    <select class="form-select" name="currency" style="max-width: 100px;">
                                        <option value="USD">USD ($)</option>
                                        <option value="AUD">AUD (A$)</option>
                                        <option value="EUR">EUR (€)</option>
                                        <option value="GBP">GBP (£)</option>
                                        <option value="CAD">CAD (C$)</option>
                                    </select>
                                    <input type="number" class="form-control" id="salary" name="salary" placeholder="e.g. 60000" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Job Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="full-time">Full Time</option>
                                <option value="part-time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="freelance">Freelance</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Submit Job Post</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
