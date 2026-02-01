<?php
session_start();
require_once 'config/db.php';

$provider = isset($_GET['provider']) ? $_GET['provider'] : '';

// Map provider to brand colors/names
$brands = [
    'google' => ['name' => 'Google', 'color' => '#DB4437', 'icon' => 'fab fa-google'],
    'facebook' => ['name' => 'Facebook', 'color' => '#4267B2', 'icon' => 'fab fa-facebook-f'],
    'apple' => ['name' => 'Apple', 'color' => '#000000', 'icon' => 'fab fa-apple']
];

if (!array_key_exists($provider, $brands)) {
    header("Location: login.php");
    exit;
}

$brand = $brands[$provider];

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_mock_login'])) {
    
    // Mock Data
    $mock_email = "test_{$provider}_user@example.com";
    $mock_id = "mock_{$provider}_12345";
    $mock_name = "Test {$brand['name']} User";
    $col_name = "{$provider}_id"; // e.g., google_id

    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE $col_name = ?");
        $stmt->execute([$mock_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Login existing
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Fetch Full Name
            $prof = $pdo->prepare("SELECT full_name FROM profiles WHERE user_id = ?");
            $prof->execute([$user['user_id']]);
            $p = $prof->fetch();
            $_SESSION['full_name'] = $p['full_name'];

        } else {
            // Register new
            // 1. Create User
            $dummy_pass = password_hash('social_dummy_pass', PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (username, email, password, user_type, $col_name) VALUES (?, ?, ?, 'seeker', ?)");
            $ins->execute([$mock_name, $mock_email, $dummy_pass, $mock_id]);
            $new_id = $pdo->lastInsertId();

            // 2. Create Profile
            $ins_prof = $pdo->prepare("INSERT INTO profiles (user_id, full_name, bio) VALUES (?, ?, ?)");
            $ins_prof->execute([$new_id, $mock_name, "I signed up via {$brand['name']}!"]);

            // Login
            $_SESSION['user_id'] = $new_id;
            $_SESSION['username'] = $mock_name;
            $_SESSION['user_type'] = 'seeker'; // Default to seeker for social
            $_SESSION['full_name'] = $mock_name;
        }

        // Redirect
        // Redirect based on role
        if ($_SESSION['user_type'] == 'admin') {
            header("Location: admin.php");
        } elseif ($_SESSION['user_type'] == 'employer') {
            header("Location: employer_dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow text-center">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="<?php echo $brand['icon']; ?> fa-4x" style="color: <?php echo $brand['color']; ?>;"></i>
                    </div>
                    
                    <h3 class="mb-3">Sign in with <?php echo $brand['name']; ?></h3>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> Demo Mode</strong><br>
                        Since this is a demo environment without real API keys, we will simulate a successful OAuth callback from <?php echo $brand['name']; ?>.
                    </div>

                    <p>We will log you in as: <strong>test_<?php echo $provider; ?>_user@example.com</strong></p>

                    <form method="POST">
                        <input type="hidden" name="confirm_mock_login" value="1">
                        <div class="d-grid gap-2">
                            <button class="btn btn-lg text-white" style="background-color: <?php echo $brand['color']; ?>;">
                                Simulate Successful Login
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
