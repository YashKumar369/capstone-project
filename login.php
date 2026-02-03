<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT users.user_id, users.username, users.password, users.user_type, users.status, users.ban_reason, users.email, profiles.full_name FROM users LEFT JOIN profiles ON users.user_id = profiles.user_id WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                if (password_verify($password, $user['password'])) {
                    
                    // Check if Banned
                    if ($user['status'] === 'banned') {
                        $banned_reason = $user['ban_reason'] ?? "Violation of terms of service.";
                        $error = "Your account has been banned.";
                        $show_ban_modal = true;
                    } else {
                        // Login Success
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['full_name'] = $user['full_name'];

                        // Redirect based on role or return url
                        if ($user['user_type'] == 'admin') {
                            header("Location: admin.php");
                        } else {
                            // Check for redirect param
                            if (!empty($redirect)) {
                                header("Location: $redirect");
                            } elseif ($user['user_type'] == 'employer') {
                                header("Location: employer_dashboard.php");
                            } else {
                                header("Location: index.php");
                            }
                        }
                        exit;
                    }
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "No account found with that email.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white text-center">
                <h4 class="mb-0 text-primary fw-bold">Login to JBook</h4>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : ''; ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p class="text-muted small mb-2">Or continue with</p>
                    <div class="d-flex justify-content-center gap-2">
                        <?php $redirParams = isset($_GET['redirect']) ? '&redirect='.urlencode($_GET['redirect']) : ''; ?>
                        <a href="social_login.php?provider=google<?php echo $redirParams; ?>" class="btn btn-google rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-google"></i></a>
                        <a href="social_login.php?provider=facebook<?php echo $redirParams; ?>" class="btn btn-facebook rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-facebook-f"></i></a>
                        <a href="social_login.php?provider=apple<?php echo $redirParams; ?>" class="btn btn-apple rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-apple"></i></a>
                    </div>
                </div>
            </div>
            <div class="card-footer text-center">
                Don't have an account? <a href="register.php" class="btn btn-outline-primary btn-sm ms-2">Register here</a>
            </div>
        </div>
    </div>
</div>

<!-- Ban Reason Modal -->
<?php if (isset($show_ban_modal) && $show_ban_modal): ?>
<div class="modal fade" id="banModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Account Banned</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="lead text-danger">Access Denied</p>
                <p>Your account has been permanently suspended by the administrator.</p>
                <hr>
                <strong>Reason for Ban:</strong>
                <div class="alert alert-secondary mt-2">
                    <?php echo nl2br(htmlspecialchars($banned_reason)); ?>
                </div>
                <p class="small text-muted mb-0">If you believe this is an error, please contact support.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="contact.php?subject=Account%20Banned" class="btn btn-outline-danger">Contact Support</a>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var banModal = new bootstrap.Modal(document.getElementById('banModal'));
        banModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
