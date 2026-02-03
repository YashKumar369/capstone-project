<?php
session_start();
require_once 'config/db.php';

if (!isset($_GET['id'])) {
    header("Location: jobs.php");
    exit;
}

// Check if user has profile resume (for display later)
$profile_resume = null;
if (isset($_SESSION['user_id'])) {
    $p_stmt = $pdo->prepare("SELECT resume_path FROM profiles WHERE user_id = ?");
    $p_stmt->execute([$_SESSION['user_id']]);
    $profile_resume = $p_stmt->fetchColumn();
}

$job_id = $_GET['id'];
$message = '';
$error = '';

// Fetch Job Details
try {
    $stmt = $pdo->prepare("SELECT jobs.*, users.username, users.email FROM jobs JOIN users ON jobs.employer_id = users.user_id WHERE job_id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        die("Job not found.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle Application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    
    if ($_SESSION['user_type'] != 'seeker') {
        $error = "Only job seekers can apply.";
    } else {
        // Removed text cover letter handling
        $resume_path = null;

        // Handle Resume
        $resume_choice = $_POST['resume_choice'] ?? 'upload';
        
        if ($resume_choice === 'profile' && $profile_resume) {
             $resume_path = $profile_resume;
        } else {
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
                $allowed = ['pdf', 'doc', 'docx'];
                $filename = $_FILES['resume']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $upload_path = 'uploads/applications/' . $_SESSION['user_id'] . '_' . time() . '_resume.' . $ext;
                    if (!is_dir('uploads/applications')) {
                         mkdir('uploads/applications', 0777, true);
                    }
                    if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
                        $resume_path = $upload_path;
                    } else { $error = "Failed to upload resume."; }
                } else { $error = "Invalid resume type."; }
            } else { $error = "Resume is required."; }
        }

        // Handle Cover Letter Upload (New)
        $cover_letter_path = null;
        if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] == 0) {
            $allowed = ['pdf', 'doc', 'docx'];
            $filename = $_FILES['cover_letter_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $cl_path = 'uploads/applications/' . $_SESSION['user_id'] . '_' . time() . '_cl.' . $ext;
                if (move_uploaded_file($_FILES['cover_letter_file']['tmp_name'], $cl_path)) {
                    $cover_letter_path = $cl_path;
                }
            } else { $error = "Invalid cover letter type."; }
        }

        if (!$error) {
            try {
                // Check if already applied
                $check = $pdo->prepare("SELECT app_id FROM applications WHERE job_id = ? AND seeker_id = ?");
                $check->execute([$job_id, $_SESSION['user_id']]);
                
                if ($check->rowCount() > 0) {
                    $error = "You have already applied for this job.";
                } else {
                    // updated insert to include cover_letter_path
                    $insert = $pdo->prepare("INSERT INTO applications (job_id, seeker_id, cover_letter, cover_letter_path, resume_path, status) VALUES (?, ?, '', ?, ?, 'pending')");
                    // Using empty string for old text column
                    if ($insert->execute([$job_id, $_SESSION['user_id'], $cover_letter_path, $resume_path])) {
                        $message = "Application submitted successfully!";
                    } else {
                        $error = "Failed to submit application.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="card-title text-primary">
                        <?php echo htmlspecialchars($job['title']); ?>
                        <?php if($job['is_featured']): ?>
                            <span class="badge bg-warning text-dark ms-2" style="font-size: 0.5em; vertical-align: middle;"><i class="fas fa-star"></i> FEATURED</span>
                        <?php endif; ?>
                    </h2>
                    <h5 class="text-muted mb-3">
                        <a href="public_profile.php?user_id=<?php echo $job['employer_id']; ?>" class="text-decoration-none text-muted">
                            <?php echo htmlspecialchars($job['username']); ?>
                        </a>
                    </h5>
                    
                    <div class="mb-3">
                        <span class="badge bg-primary me-2"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars(ucfirst($job['type'])); ?></span>
                        <span class="badge bg-secondary me-2"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                        <?php if($job['salary_range']): ?>
                            <span class="badge bg-success me-2"><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                        <?php endif; ?>
                        <span class="badge bg-info text-dark"><i class="fas fa-users"></i> Vacancies: <?php echo htmlspecialchars($job['vacancies'] ?? 1); ?></span>
                    </div>

                    <hr>

                    <h5>Job Description</h5>
                    <p class="card-text" style="white-space: pre-wrap;"><?php echo htmlspecialchars($job['description']); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Apply Now</h5>
                </div>
                <div class="card-body">
                    <?php if($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                        <button class="btn btn-secondary w-100" disabled>Applied</button>
                    <?php elseif(!isset($_SESSION['user_id'])): ?>
                        <p>Please login to apply for this position.</p>
                        <a href="login.php?redirect=<?php echo urlencode('job_details.php?id=' . $job['job_id']); ?>" class="btn btn-primary w-100">Login to Apply</a>

                    <?php else: ?>
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="cover_letter_file" class="form-label">Upload Cover Letter (Optional)</label>
                                <input type="file" class="form-control" name="cover_letter_file">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Resume</label>
                                <?php if($profile_resume): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="resume_choice" value="profile" id="useProfile" checked>
                                        <label class="form-check-label" for="useProfile">
                                            Use Profile Resume (<a href="<?php echo htmlspecialchars($profile_resume); ?>" target="_blank">View</a>)
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="resume_choice" value="upload" id="useUpload">
                                        <label class="form-check-label" for="useUpload">
                                            Upload New
                                        </label>
                                    </div>
                                    <input type="file" class="form-control d-none" name="resume" id="resumeUploadInput">
                                    <script>
                                        document.querySelectorAll('input[name="resume_choice"]').forEach(r => {
                                            r.addEventListener('change', function() {
                                                document.getElementById('resumeUploadInput').classList.toggle('d-none', this.value !== 'upload');
                                                document.getElementById('resumeUploadInput').required = (this.value === 'upload');
                                            });
                                        });
                                    </script>
                                <?php else: ?>
                                    <input type="hidden" name="resume_choice" value="upload">
                                    <input type="file" class="form-control" name="resume" required>
                                <?php endif; ?>
                            </div>

                            <button type="submit" name="apply" class="btn btn-primary w-100">Submit Application</button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] != $job['employer_id']): ?>
                        <hr>
                        <a href="messages.php?receiver_id=<?php echo $job['employer_id']; ?>" class="btn btn-outline-info w-100"><i class="fas fa-comment"></i> Message Employer</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
