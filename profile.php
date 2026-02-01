<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Handle Text Data Update
    if (isset($_POST['full_name'])) {
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $skills = isset($_POST['skills']) ? trim($_POST['skills']) : '';
        
        try {
            // Check if profile exists
            $check = $pdo->prepare("SELECT profile_id FROM profiles WHERE user_id = ?");
            $check->execute([$user_id]);
            
            if ($check->rowCount() > 0) {
                // Update
                $stmt = $pdo->prepare("UPDATE profiles SET full_name = ?, bio = ?, location = ?, skills = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $bio, $location, $skills, $user_id]);
            } else {
                // Insert (shouldn't happen usually if register works, but good fallback)
                $stmt = $pdo->prepare("INSERT INTO profiles (user_id, full_name, bio, location, skills) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $full_name, $bio, $location, $skills]);
            }
            $_SESSION['full_name'] = $full_name; // Update session
            $message = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

    // 2. Handle Profile Picture Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $pic_path = 'uploads/profile_pics/' . $user_id . '_' . time() . '.' . $ext;
            if (!is_dir('uploads/profile_pics')) {
                mkdir('uploads/profile_pics', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $pic_path)) {
                $stmt = $pdo->prepare("UPDATE profiles SET profile_pic = ? WHERE user_id = ?");
                $stmt->execute([$pic_path, $user_id]);
                $message = "Profile picture updated!";
            } else {
                $error = "Failed to upload picture.";
            }
        } else {
            $error = "Invalid image type. JPG/PNG only.";
        }
    }

    // 3. Handle Resume Upload
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx'];
        $filename = $_FILES['resume']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $resume_path = 'uploads/resumes/' . $user_id . '_' . time() . '.' . $ext;
            if (!is_dir('uploads/resumes')) {
                mkdir('uploads/resumes', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path)) {
                $stmt = $pdo->prepare("UPDATE profiles SET resume_path = ? WHERE user_id = ?");
                $stmt->execute([$resume_path, $user_id]);
                $message = "Resume uploaded successfully!";
            } else {
                $error = "Failed to move uploaded resume.";
            }
        } else {
            $error = "Invalid resume type. PDF/DOC only.";
        }
    }
}

// Fetch Profile Data
try {
    $stmt = $pdo->prepare("SELECT users.username, users.email, profiles.* FROM users LEFT JOIN profiles ON users.user_id = profiles.user_id WHERE users.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <!-- Profile View -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <?php if(!empty($user['profile_pic'])): ?>
                        <div class="rounded-circle mx-auto mb-3" style="width: 120px; height: 120px; background-image: url('<?php echo htmlspecialchars($user['profile_pic']); ?>'); background-size: cover; background-position: center; border: 3px solid #eee;"></div>
                    <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 40px;">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h4>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['location'] ?: 'Location not set'); ?></p>
                    
                    <?php
                        $f_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE user_id_2 = ? AND status = 'accepted'");
                        $f_count_stmt->execute([$user_id]);
                        $follower_count = $f_count_stmt->fetchColumn();
                        
                        $following_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE user_id_1 = ? AND status = 'accepted'");
                        $following_count_stmt->execute([$user_id]);
                        $following_count = $following_count_stmt->fetchColumn();
                    ?>
                    <div class="d-flex justify-content-center gap-3 mb-3">
                        <div class="text-center">
                            <a href="connections_list.php?type=followers" class="text-decoration-none text-dark">
                                <h5 class="mb-0 fw-bold"><?php echo $follower_count; ?></h5>
                                <small class="text-muted">Followers</small>
                            </a>
                        </div>
                        <div class="text-center">
                            <a href="connections_list.php?type=following" class="text-decoration-none text-dark">
                                <h5 class="mb-0 fw-bold"><?php echo $following_count; ?></h5>
                                <small class="text-muted">Following</small>
                            </a>
                        </div>
                    </div>
                    
                    <?php if($_SESSION['user_type'] == 'seeker'): ?>
                    <hr>
                    
                    <h5 class="text-start mb-1"><i class="fas fa-file-alt"></i> Default Resume</h5>
                    <p class="text-muted small mb-3">Upload a resume here to use it for quick applications.</p>
                    <div class="d-grid gap-2">
                         <form method="POST" enctype="multipart/form-data">
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" name="resume" required>
                                <button class="btn btn-outline-primary" type="submit">Upload</button>
                            </div>
                        </form>
                        <?php if($user['resume_path']): ?>
                            <a href="<?php echo htmlspecialchars($user['resume_path']); ?>" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-file-download"></i> Download Resume</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Following / Connections -->
            <div class="card shadow mb-4">
                 <div class="card-header bg-white fw-bold">Connections</div>
                 <div class="card-body">
                     <?php
                        // Fetch connections (sent or received)
                        $c_stmt = $pdo->prepare("SELECT users.username, connections.status FROM connections 
                                                 JOIN users ON (CASE WHEN user_id_1 = ? THEN user_id_2 ELSE user_id_1 END = users.user_id) 
                                                 WHERE (user_id_1 = ? OR user_id_2 = ?)");
                        $c_stmt->execute([$user_id, $user_id, $user_id]);
                        $connections = $c_stmt->fetchAll();
                     ?>
                     
                     <?php if(count($connections) > 0): ?>
                         <ul class="list-unstyled mb-0">
                         <?php foreach($connections as $conn): ?>
                             <li class="mb-2 d-flex justify-content-between align-items-center">
                                 <span><i class="fas fa-user-circle text-secondary"></i> <?php echo htmlspecialchars($conn['username']); ?></span>
                                 <span class="badge bg-light text-dark border"><?php echo ucfirst($conn['status']); ?></span>
                             </li>
                         <?php endforeach; ?>
                         </ul>
                     <?php else: ?>
                         <p class="text-muted small mb-0">No connections yet.</p>
                     <?php endif; ?>
                 </div>
            </div>
            
            <!-- Skills (Seeker Only) -->
            <?php if($_SESSION['user_type'] == 'seeker'): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-white fw-bold">Skills</div>
                <div class="card-body">
                    <?php if($user['skills']): ?>
                        <?php foreach(explode(',', $user['skills']) as $skill): ?>
                            <span class="badge bg-secondary me-1 mb-1"><?php echo trim($skill); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">No skills listed yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Edit Profile Form -->
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <?php if($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" name="profile_pic">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bio</label>
                            <textarea class="form-control" name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                        </div>
                        <?php if($_SESSION['user_type'] == 'seeker'): ?>
                        <div class="mb-3">
                            <label class="form-label">Skills (comma separated)</label>
                            <input type="text" class="form-control" name="skills" value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>" placeholder="PHP, JavaScript, Marketing...">
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
