<?php
session_start();
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];

    if (empty($username) || empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email is already registered.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $hashed_password, $user_type])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Create empty profile entry
                    $stmtProfile = $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)");
                    $stmtProfile->execute([$user_id]);

                    $success = "Registration successful! You can now <a href='login.php'>login</a>.";
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Create an Account</h4>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Full Name / Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="user_type" class="form-label">I am a...</label>
                        <select class="form-select" id="user_type" name="user_type">
                            <option value="seeker">Job Seeker</option>
                            <option value="employer">Employer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p class="text-muted small mb-2">Or sign up with</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="social_login.php?provider=google" class="btn btn-google rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-google"></i></a>
                        <a href="social_login.php?provider=facebook" class="btn btn-facebook rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-facebook-f"></i></a>
                        <a href="social_login.php?provider=apple" class="btn btn-apple rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="fab fa-apple"></i></a>
                    </div>
                </div>
            </div>
            <div class="card-footer text-center">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
