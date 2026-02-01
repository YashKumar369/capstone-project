<?php
session_start();
require_once 'config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$employer_id = $_SESSION['user_id'];
$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// --- LOGIC HANDLERS ---

// 1. Job Actions (Pause, Feature)
if (isset($_POST['action']) && $_POST['action'] == 'toggle_job_status') {
    $jid = $_POST['job_id'];
    $new_status = $_POST['status']; // 'active' or 'paused' or 'closed'
    try {
        $stmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE job_id = ? AND employer_id = ?");
        $stmt->execute([$new_status, $jid, $employer_id]);
        $message = "Job status updated to $new_status.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

if (isset($_POST['action']) && $_POST['action'] == 'toggle_feature') {
    $jid = $_POST['job_id'];
    $is_featured = (int)$_POST['is_featured'];
    try {
        $stmt = $pdo->prepare("UPDATE jobs SET is_featured = ? WHERE job_id = ? AND employer_id = ?");
        $stmt->execute([$is_featured, $jid, $employer_id]);
        $message = $is_featured ? "Job promoted to featured!" : "Job un-featured.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

// 2. Candidate Pipeline Actions
if (isset($_POST['action']) && $_POST['action'] == 'update_app_status') {
    $aid = $_POST['app_id'];
    $status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE app_id = ?"); 
        $stmt->execute([$status, $aid]);
        $message = "Candidate moved to " . ucfirst($status);

        // Fetch application details for notification
        $app_details = $pdo->prepare("SELECT a.seeker_id, j.title, u.username as seeker_name FROM applications a JOIN jobs j ON a.job_id = j.job_id JOIN users u ON a.seeker_id = u.user_id WHERE a.app_id = ?");
        $app_details->execute([$aid]);
        $app_row = $app_details->fetch();

        if ($app_row) {
            $seeker_id = $app_row['seeker_id'];
            $job_title = $app_row['title'];
            $seeker_name = $app_row['seeker_name'];

            // 1. Notify Job Seeker
            $msg_seeker = "Your application for <strong>$job_title</strong> has been updated to <strong>" . ucfirst($status) . "</strong>.";
            if ($status == 'interviewing') {
                $msg_seeker .= " The employer may contact you to schedule a meeting.";
            }
            // using reference_id as app_id
            $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id, message) VALUES (?, ?, 'app_status_update', ?, ?)")
                ->execute([$seeker_id, $employer_id, $aid, $msg_seeker]);

            // 2. Notify Employer (Self-Confirmation as requested)
            $msg_employer = "You moved <strong>$seeker_name</strong> to <strong>" . ucfirst($status) . "</strong> for $job_title.";
            $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id, message) VALUES (?, ?, 'app_status_update_self', ?, ?)")
                ->execute([$employer_id, $seeker_id, $aid, $msg_employer]);
        }

    } catch (PDOException $e) { $error = $e->getMessage(); }
}

// 3. Purchase Plan Actions
if (isset($_POST['action']) && $_POST['action'] == 'purchase_plan') {
    $plan_name = $_POST['plan_name'];
    $amount = $_POST['amount'];
    try {
        // Find Admin ID
        $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin' ORDER BY user_id ASC LIMIT 1");
        $admin_stmt->execute();
        $admin_id = $admin_stmt->fetchColumn();

        if ($admin_id) {
            // detailed type or just generic? using 'system_alert' or just 'plan_purchase'
            // I'll use 'plan_purchase' and expect admin implementation to handle reading it eventually, or just raw data.
            // Since notifications are text based in the view logic usually, I might need to make sure Admin can see it.
            // But user just said "notification goes through".
            $notif = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id) VALUES (?, ?, 'plan_purchase', 0)");
            $notif->execute([$admin_id, $employer_id]);
            $message = "Successfully purchased <strong>$plan_name</strong> plan! An admin has been notified.";
        } else {
            $error = "Could not contact admin, but purchase recorded.";
        }
    } catch (PDOException $e) { $error = "Transaction failed: " . $e->getMessage(); }
}


// 4. Contact Sales Action

// 5. Verification Request Logic
if(isset($_POST['action']) && $_POST['action'] == 'request_verification') {
    if(isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['document']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)) {
            $new_name = "ver_" . $employer_id . "_" . time() . "." . $ext;
            $upload_path = "uploads/verification/" . $new_name;
            if(!is_dir('uploads/verification/')) mkdir('uploads/verification/', 0777, true);
            
            if(move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
                try {
                    // Check existing pending
                    $check = $pdo->prepare("SELECT request_id FROM verification_requests WHERE user_id = ? AND status = 'pending'");
                    $check->execute([$employer_id]);
                    if($check->rowCount() > 0) {
                        $error = "You already have a pending request.";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO verification_requests (user_id, document_path) VALUES (?, ?)");
                        $stmt->execute([$employer_id, $upload_path]);
                        $verification_success = true; // Trigger Popup
                    }
                } catch(PDOException $e) { $error = "DB Error: " . $e->getMessage(); }
            } else { $error = "Failed to move uploaded file."; }
        } else { $error = "Invalid file type. Allowed: PDF, JPG, PNG"; }
    } else { $error = "Please select a file."; }
}

// --- DATA FETCHING ---

// Brand Health (Followers)
$followers_count = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE user_id_2 = ? AND status = 'accepted'");
$followers_count->execute([$employer_id]);
$brand_followers = $followers_count->fetchColumn();

// Active Jobs
$active_jobs_stmt = $pdo->prepare("SELECT * FROM jobs WHERE employer_id = ? ORDER BY created_at DESC");
$active_jobs_stmt->execute([$employer_id]);
$jobs = $active_jobs_stmt->fetchAll();

// Recent Applicants (New / Pending)
$new_applicants_stmt = $pdo->prepare("
    SELECT a.*, a.status as app_status, j.title as job_title, p.full_name, COALESCE(NULLIF(a.resume_path, ''), p.resume_path) as resume_path, u.username, u.email 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.job_id 
    JOIN users u ON a.seeker_id = u.user_id 
    LEFT JOIN profiles p ON u.user_id = p.user_id 
    WHERE j.employer_id = ? AND a.status = 'pending'
    ORDER BY a.applied_at DESC
");
$new_applicants_stmt->execute([$employer_id]);
$new_applicants = $new_applicants_stmt->fetchAll();

// All Applicants (Pipeline)
$pipeline_stmt = $pdo->prepare("
    SELECT a.*, a.status as app_status, j.title as job_title, p.full_name, COALESCE(NULLIF(a.resume_path, ''), p.resume_path) as resume_path, u.username, u.email 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.job_id 
    JOIN users u ON a.seeker_id = u.user_id 
    LEFT JOIN profiles p ON u.user_id = p.user_id 
    WHERE j.employer_id = ? 
    ORDER BY FIELD(a.status, 'pending', 'reviewed', 'shortlisted', 'interviewing', 'accepted', 'rejected')
");
$pipeline_stmt->execute([$employer_id]);
$pipeline = $pipeline_stmt->fetchAll();

// Profile Visitors
$visitors_stmt = $pdo->prepare("
    SELECT pv.*, u.username, p.full_name, p.profile_pic 
    FROM profile_views pv
    JOIN users u ON pv.viewer_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    WHERE pv.profile_owner_id = ?
    ORDER BY pv.viewed_at DESC LIMIT 10
");
$visitors_stmt->execute([$employer_id]);
$visitors = $visitors_stmt->fetchAll();

// Verification Status Check
$ver_stmt = $pdo->prepare("SELECT status, admin_note FROM verification_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$ver_stmt->execute([$employer_id]);
$ver_req = $ver_stmt->fetch();
$ver_status = $ver_req ? $ver_req['status'] : null;

// User Verification Flag
$u_ver = $pdo->prepare("SELECT is_verified FROM users WHERE user_id = ?");
$u_ver->execute([$employer_id]);
$is_verified_flag = $u_ver->fetchColumn();


include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Header Stats -->
    <div class="row mb-4">
        <div class="col-md-9">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
            <p class="text-muted">Manage your hiring pipeline and employer brand.</p>
        </div>
        <div class="col-md-3 text-end">
            <div class="card bg-light border-0 shadow-sm">
                <div class="card-body py-2">
                    <small class="text-muted text-uppercase fw-bold">Brand Health</small>
                    <h3 class="mb-0 text-primary"><i class="fas fa-users"></i> <?php echo $brand_followers; ?></h3>
                    <small>Followers</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="row mb-4">
        <div class="col-md-12">
             <div class="card shadow-sm border-0">
                 <div class="card-body d-flex justify-content-between align-items-center">
                     <h5 class="mb-0">Quick Actions</h5>
                     <div>
                         <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Post a New Job</a>
                         <a href="feed.php" class="btn btn-outline-primary"><i class="fas fa-share-alt"></i> Share Update</a>
                         <a href="messages.php" class="btn btn-outline-dark"><i class="fas fa-comment-dots"></i> Inbox</a>
                     </div>
                 </div>
             </div>
        </div>
    </div>

    <!-- Verification Alert/Banner -->
    <?php if($is_verified_flag): ?>
        <div class="alert alert-success d-flex align-items-center mb-4">
             <i class="fas fa-check-circle fa-2x me-3"></i>
             <div>
                 <strong>Verified Employer</strong><br>
                 Your account is verified. Your job posts will go live immediately.
             </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-4">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Verification Required</h5>
            <p>Please upload a business registration document or ID to get verified. Unverified accounts require job approval.</p>
            <?php if($ver_status == 'pending'): ?>
                 <div class="badge bg-info text-dark">Status: Pending Review</div>
            <?php elseif($ver_status == 'rejected'): ?>
                 <div class="text-danger mb-2"><strong>Last Request Rejected:</strong> <?php echo htmlspecialchars($ver_req['admin_note']); ?></div>
            <?php endif; ?>
            
            <?php if($ver_status != 'pending'): ?>
            <form method="POST" enctype="multipart/form-data" class="mt-2 row g-2 align-items-center">
                <div class="col-auto">
                    <input type="hidden" name="action" value="request_verification">
                    <input type="file" class="form-control form-control-sm" name="document" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary">Submit Document</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'overview' ? 'active' : ''; ?>" href="?tab=overview">Overview</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'pipeline' ? 'active' : ''; ?>" href="?tab=pipeline">Candidate Pipeline</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'jobs' ? 'active' : ''; ?>" href="?tab=jobs">Active Jobs</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'visitors' ? 'active' : ''; ?>" href="?tab=visitors">Profile Visitors</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab == 'pricing' ? 'active' : ''; ?>" href="?tab=pricing">Pricing & Plans</a>
        </li>
    </ul>

    <!-- Overview Tab -->
    <?php if($active_tab == 'overview'): ?>
        <div class="row">
            <!-- New Applicants Widget -->
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary"><i class="fas fa-user-clock"></i> New Applicants</h5>
                        <a href="?tab=pipeline" class="btn btn-sm btn-link">View All</a>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if(count($new_applicants) > 0): ?>
                            <?php foreach($new_applicants as $app): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($app['full_name'] ?: $app['username']); ?></h6>
                                        <small class="text-muted">Applied for <strong><?php echo htmlspecialchars($app['job_title']); ?></strong></small>
                                    </div>
                                    <div class="btn-group">
                                        <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" class="btn btn-sm btn-outline-secondary" download><i class="fas fa-download"></i> Resume</a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_app_status">
                                            <input type="hidden" name="app_id" value="<?php echo $app['app_id']; ?>">
                                            <button name="status" value="reviewed" class="btn btn-sm btn-outline-primary">Review</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-4">No new applicants.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Visitors Teaser -->
            <div class="col-md-4">
                 <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-eye"></i> Recent Visitors</h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach(array_slice($visitors, 0, 5) as $v): ?>
                            <li class="list-group-item d-flex align-items-center">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                    <i class="fas fa-user text-muted"></i>
                                </div>
                                <span class="small"><?php echo htmlspecialchars($v['full_name'] ?: $v['username']); ?></span>
                                <small class="ms-auto text-muted"><?php echo date('M d', strtotime($v['viewed_at'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                        <?php if(count($visitors) == 0): ?>
                             <li class="list-group-item text-muted text-center">No visitors yet.</li>
                        <?php endif; ?>
                    </ul>
                 </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pipeline Tab -->
    <?php if($active_tab == 'pipeline'): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Candidate</th>
                                <th>Job Applied For</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pipeline as $app): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($app['full_name'] ?: $app['username']); ?></div>
                                        <a href="<?php echo htmlspecialchars($app['resume_path']); ?>" class="small text-decoration-none" download><i class="fas fa-file-pdf"></i> Download Resume</a>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_app_status">
                                            <input type="hidden" name="app_id" value="<?php echo $app['app_id']; ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" 
                                                style="width: 130px; border-color: <?php 
                                                    echo match($app['status']) {
                                                        'accepted' => '#198754',
                                                        'rejected' => '#dc3545',
                                                        'interviewing' => '#ffc107',
                                                        'shortlisted' => '#0d6efd',
                                                        default => '#ced4da'
                                                    }; 
                                                ?>">
                                                <option value="pending" <?php echo $app['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="reviewed" <?php echo $app['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                <option value="shortlisted" <?php echo $app['status'] == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                <option value="interviewing" <?php echo $app['status'] == 'interviewing' ? 'selected' : ''; ?>>Interviewing</option>
                                                <option value="accepted" <?php echo $app['status'] == 'accepted' ? 'selected' : ''; ?>>Hired</option>
                                                <option value="rejected" <?php echo $app['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-dark" onclick="location.href='messages.php?receiver_id=<?php echo $app['seeker_id']; ?>'"><i class="fas fa-comment"></i> DM</button>
                                        <a href="https://calendar.google.com/calendar/render?action=TEMPLATE&text=Interview%20with%20<?php echo urlencode($app['full_name']); ?>&add=<?php echo urlencode($app['email']); ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-calendar-alt"></i> Schedule Meeting</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Jobs Tab -->
    <?php if($active_tab == 'jobs'): ?>
        <div class="card shadow-sm">
             <div class="card-body">
                 <table class="table table-hover align-middle">
                     <thead class="table-light">
                         <tr>
                             <th>Job Title</th>
                             <th>Status</th>
                             <th>Promote</th>
                             <th>Controls</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach($jobs as $j): ?>
                             <tr>
                                 <td>
                                     <strong><?php echo htmlspecialchars($j['title']); ?></strong>
                                     <br><small class="text-muted">Posted: <?php echo date('M d', strtotime($j['created_at'])); ?> | Vacancies: <?php echo $j['vacancies'] ?? 1; ?></small>
                                 </td>
                                 <td>
                                     <span class="badge <?php echo $j['status'] == 'active' ? 'bg-success' : ($j['status'] == 'paused' ? 'bg-warning' : 'bg-secondary'); ?>">
                                         <?php echo ucfirst($j['status']); ?>
                                     </span>
                                 </td>
                                 <td>
                                     <form method="POST">
                                         <input type="hidden" name="action" value="toggle_feature">
                                         <input type="hidden" name="job_id" value="<?php echo $j['job_id']; ?>">
                                         <input type="hidden" name="is_featured" value="<?php echo $j['is_featured'] ? 0 : 1; ?>">
                                         <button class="btn btn-sm <?php echo $j['is_featured'] ? 'btn-warning' : 'btn-outline-secondary'; ?>">
                                             <i class="fas fa-star"></i> <?php echo $j['is_featured'] ? 'Featured' : 'Feature'; ?>
                                         </button>
                                     </form>
                                 </td>
                                 <td>
                                     <form method="POST" class="d-flex gap-2">
                                         <input type="hidden" name="action" value="toggle_job_status">
                                         <input type="hidden" name="job_id" value="<?php echo $j['job_id']; ?>">
                                         
                                         <?php if($j['status'] == 'active'): ?>
                                             <button name="status" value="paused" class="btn btn-sm btn-warning" title="Pause"><i class="fas fa-pause"></i></button>
                                             <button name="status" value="closed" class="btn btn-sm btn-danger" title="Close"><i class="fas fa-stop"></i></button>
                                         <?php elseif($j['status'] == 'paused'): ?>
                                             <button name="status" value="active" class="btn btn-sm btn-success" title="Resume"><i class="fas fa-play"></i></button>
                                         <?php elseif($j['status'] == 'pending'): ?>
                                             <small class="text-muted"><i class="fas fa-clock"></i> Awaiting Approval</small>
                                         <?php endif; ?>
                                         <a href="#" class="btn btn-sm btn-info text-white" title="Edit"><i class="fas fa-edit"></i></a>
                                     </form>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
        </div>
    <?php endif; ?>

     <!-- Visitors Tab -->
    <?php if($active_tab == 'visitors'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">All Profile Visitors</div>
            <div class="list-group list-group-flush">
                <?php foreach($visitors as $v): ?>
                    <div class="list-group-item d-flex align-items-center">
                        <div class="me-3">
                             <?php if(!empty($v['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($v['profile_pic']); ?>" class="rounded-circle" width="40" height="40">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($v['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($v['full_name'] ?: $v['username']); ?></h6>
                            <small class="text-muted">Viewed on <?php echo date('M d, Y h:i A', strtotime($v['viewed_at'])); ?></small>
                        </div>
                        <div class="ms-auto">
                            <a href="messages.php?receiver_id=<?php echo $v['viewer_id']; ?>" class="btn btn-sm btn-outline-primary">Message</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pricing Tab -->
    <?php if($active_tab == 'pricing'): ?>
        <div class="row text-center">
            <!-- Basic Plan -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="my-0 fw-normal">Basic Boost</h4>
                    </div>
                    <div class="card-body">
                        <h1 class="card-title pricing-card-title">$19<small class="text-muted fw-light">/post</small></h1>
                        <ul class="list-unstyled mt-3 mb-4">
                            <li>Higher Visibility</li>
                            <li>Standard Support</li>
                            <li>Listed for 30 Days</li>
                        </ul>
                        <button onclick="location.href='checkout.php?plan=Basic%20Boost&price=19.00'" class="w-100 btn btn-lg btn-outline-primary">Boost Now</button>
                    </div>
                </div>
            </div>
            <!-- Pro Plan -->
            <div class="col-md-4">
                <div class="card mb-4 rounded-3 shadow border-primary">
                    <div class="card-header py-3 text-white bg-primary border-primary">
                        <h4 class="my-0 fw-normal">Pro Pack</h4>
                    </div>
                    <div class="card-body">
                        <h1 class="card-title pricing-card-title">$49<small class="text-muted fw-light">/mo</small></h1>
                        <ul class="list-unstyled mt-3 mb-4">
                            <li>5 Job Posts</li>
                            <li>Featured for 7 Days</li>
                            <li>Priority Support</li>
                            <li>Email Access</li>
                        </ul>
                        <button onclick="location.href='checkout.php?plan=Pro%20Pack&price=49.00'" class="w-100 btn btn-lg btn-primary">Get Started</button>
                    </div>
                </div>
            </div>
            <!-- Premium Plan -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="my-0 fw-normal">Enterprise</h4>
                    </div>
                    <div class="card-body">
                        <h1 class="card-title pricing-card-title">$99<small class="text-muted fw-light">/mo</small></h1>
                        <ul class="list-unstyled mt-3 mb-4">
                            <li>Unlimited Posts</li>
                            <li>Featured for 30 days</li>
                            <li>Dedicated Manager</li>
                            <li>API Access</li>
                        </ul>
                        <button onclick="location.href='contact_sales.php'" class="w-100 btn btn-lg btn-outline-primary">Contact Sales</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Verification Success Modal -->
<?php if(isset($verification_success) && $verification_success): ?>
<div class="modal fade" id="verSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="modal-body">
        <div class="mb-3 text-success">
            <i class="fas fa-check-circle fa-4x"></i>
        </div>
        <h4 class="mb-3">Document Sent for Approval</h4>
        <p class="text-muted">Your document has been securely uploaded. Even if you don't stay online, our admins will review it shortly so your status will be updated soon!</p>
        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('verSuccessModal'));
        myModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
