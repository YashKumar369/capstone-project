<?php
session_start();
require_once 'config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// --- LOGIC HANDLERS ---

// 1. User Management Actions
if (isset($_POST['action']) && $_POST['action'] == 'update_user_status') {
    $uid = $_POST['user_id'];
    $status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->execute([$status, $uid]);
        $message = "User status updated to $status.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

if (isset($_POST['action']) && $_POST['action'] == 'update_user_role') {
    $uid = $_POST['user_id'];
    $role = $_POST['role'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET user_type = ? WHERE user_id = ?");
        $stmt->execute([$role, $uid]);
        $message = "User role updated to $role.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}


if (isset($_POST['action']) && $_POST['action'] == 'update_user_verification') {
    $uid = $_POST['user_id'];
    $is_ver = (int)$_POST['is_verified'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE user_id = ?");
        $stmt->execute([$is_ver, $uid]);
        $message = "User verification updated.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

if (isset($_POST['action']) && $_POST['action'] == 'ban_user_with_reason') {
    $uid = $_POST['user_id'];
    $reason = $_POST['ban_reason'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = 'banned', ban_reason = ? WHERE user_id = ?");
        $stmt->execute([$reason, $uid]);
        $message = "User has been banned. Reason: " . htmlspecialchars($reason);
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

// 2. Job Management Actions
if (isset($_POST['action']) && $_POST['action'] == 'job_approve_reject') {
    $jid = $_POST['job_id'];
    $status = $_POST['status']; // 'active' or 'closed' or 'rejected'
    try {
        $stmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE job_id = ?");
        $stmt->execute([$status, $jid]);
        $message = "Job status updated to $status.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

if (isset($_POST['action']) && $_POST['action'] == 'update_job_details') {
    $jid = $_POST['job_id'];
    $tags = $_POST['tags'];
    $extend_days = (int)$_POST['extend_days'];
    
    try {
        // Update tags
        $sql = "UPDATE jobs SET tags = ?";
        // Update expiry if requested
        if ($extend_days > 0) {
            $sql .= ", expires_at = DATE_ADD(COALESCE(expires_at, NOW()), INTERVAL $extend_days DAY)";
        }
        $sql .= " WHERE job_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tags, $jid]);
        $message = "Job details updated.";
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

// 3. Verification Request Actions
if (isset($_POST['action']) && $_POST['action'] == 'process_verification') {
    $req_id = $_POST['req_id'];
    $decision = $_POST['decision']; // 'approve' or 'reject'
    $uid = $_POST['user_id'];
    $note = $_POST['admin_note'] ?? '';

    try {
        if ($decision == 'approve') {
            // Update Request
            $pdo->prepare("UPDATE verification_requests SET status = 'approved', admin_note = ? WHERE request_id = ?")->execute([$note, $req_id]);
            // Update User
            $pdo->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ?")->execute([$uid]);
            $message = "Employer Verified Successfully.";
            
            // Notify
            $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, message) VALUES (?, ?, 'system_alert', ?)")
                ->execute([$uid, $_SESSION['user_id'], "Your account verification has been APPROVED."]);
                
        } elseif ($decision == 'reject') {
            // Update Request
            $pdo->prepare("UPDATE verification_requests SET status = 'rejected', admin_note = ? WHERE request_id = ?")->execute([$note, $req_id]);
            // Ensure User Unverified (if they were somehow verified)
            $pdo->prepare("UPDATE users SET is_verified = 0 WHERE user_id = ?")->execute([$uid]);
            $message = "Verification request rejected.";
            
             // Notify
            $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, message) VALUES (?, ?, 'system_alert', ?)")
                ->execute([$uid, $_SESSION['user_id'], "Your verification was REJECTED: $note"]);
        }
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

// 3. System Settings



// --- DATA FETCHING ---

// Dashboard Stats
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'jobs' => $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn(),
    'applications' => $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
    'reports' => $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn()
];

// Users List
$user_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$sql = "SELECT * FROM users";
$params = [];
if ($user_filter != 'all') {
    $sql .= " WHERE user_type = ?";
    $params[] = $user_filter;
}
$sql .= " ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Jobs List
$jobs = $pdo->query("SELECT jobs.*, users.username FROM jobs JOIN users ON jobs.employer_id = users.user_id ORDER BY created_at DESC LIMIT 50")->fetchAll();

// Applications List (Recent)
$recent_apps = [];
try {
    $recent_apps = $pdo->query("SELECT applications.*, jobs.title, users.username FROM applications JOIN jobs ON applications.job_id = jobs.job_id JOIN users ON applications.seeker_id = users.user_id ORDER BY created_at DESC LIMIT 10")->fetchAll();
} catch(Exception $e) {}

// Reports List (Pending)
$pending_reports = [];
try {
    $pending_reports = $pdo->query("SELECT reports.*, users.username as reporter FROM reports JOIN users ON reports.reporter_id = users.user_id WHERE reports.status = 'pending' LIMIT 20")->fetchAll();
} catch(Exception $e) {}

// Analytics Data
$user_analytics = $pdo->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type")->fetchAll(PDO::FETCH_KEY_PAIR);
$job_analytics = $pdo->query("SELECT status, COUNT(*) as count FROM jobs GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Settings


include 'includes/header.php';
// Verification Requests
$verification_requests = [];
if($active_tab == 'verifications') {
    $verification_requests = $pdo->query("SELECT vr.*, u.username, u.email FROM verification_requests vr JOIN users u ON vr.user_id = u.user_id WHERE vr.status = 'pending'")->fetchAll();
}
?>


<div class="container mt-4">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <div class="row">
        <!-- Main Content -->
        <div class="col-md-12">
            <!-- Navigation -->
            <ul class="nav nav-pills mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab=='dashboard'?'active':''; ?>" href="?tab=dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab=='users'?'active':''; ?>" href="?tab=users"><i class="fas fa-users"></i> Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab=='jobs'?'active':''; ?>" href="?tab=jobs"><i class="fas fa-briefcase"></i> Jobs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab=='verifications'?'active':''; ?>" href="?tab=verifications"><i class="fas fa-id-card"></i> Verifications</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab=='feed'?'active':''; ?>" href="?tab=feed"><i class="fas fa-comments"></i> Feed</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab=='analytics'?'active':''; ?>" href="?tab=analytics"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
            </ul>

            <?php if($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <!-- TAB: DASHBOARD -->
            <?php if($active_tab == 'dashboard'): ?>
                <h3 class="mb-4">Admin Overview</h3>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white shadow-sm mb-3" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#usersModal">
                            <div class="card-body">
                                <h5>Total Users</h5>
                                <h2><?php echo $stats['users']; ?></h2>
                                <small>Click to view Quick Stats</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white shadow-sm mb-3" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#jobsModal">
                            <div class="card-body">
                                <h5>Total Jobs</h5>
                                <h2><?php echo $stats['jobs']; ?></h2>
                                <small>Click to view Quick Stats</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white shadow-sm mb-3" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#appsModal">
                            <div class="card-body">
                                <h5>Applications</h5>
                                <h2><?php echo $stats['applications']; ?></h2>
                                <small>Click to view Recent</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark shadow-sm mb-3" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#reportsModal">
                            <div class="card-body">
                                <h5>Pending Reports</h5>
                                <h2><?php echo $stats['reports']; ?></h2>
                                <small>Click to manage</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions Removed as per user request to clean up interface -->

                <!-- MODALS -->
                <!-- Users Modal -->
                <div class="modal fade" id="usersModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Recent Users</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <ul class="list-group">
                                    <?php foreach(array_slice($users ?? [], 0, 5) as $u): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($u['username']); ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($u['user_type']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <a href="?tab=users" class="btn btn-primary w-100">Go to Users Management</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jobs Modal -->
                <div class="modal fade" id="jobsModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Recent Jobs</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <ul class="list-group">
                                    <?php foreach(array_slice($jobs ?? [], 0, 5) as $job): ?>
                                        <li class="list-group-item">
                                            <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
                                            <small class="text-muted">by <?php echo htmlspecialchars($job['username']); ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <a href="?tab=jobs" class="btn btn-primary w-100">Go to Jobs Management</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Apps Modal -->
                <div class="modal fade" id="appsModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Recent Applications</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <ul class="list-group">
                                    <?php foreach($recent_apps as $app): ?>
                                        <li class="list-group-item">
                                            <?php echo htmlspecialchars($app['username']); ?> applied for 
                                            <strong><?php echo htmlspecialchars($app['title']); ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if(empty($recent_apps)) echo '<p>No recent applications.</p>'; ?>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <a href="?tab=jobs" class="btn btn-primary w-100">View Jobs & Applications</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Modal -->
                <div class="modal fade" id="reportsModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Pending Reports</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <ul class="list-group">
                                    <?php foreach($pending_reports as $rep): ?>
                                        <li class="list-group-item">
                                            <strong><?php echo htmlspecialchars($rep['reporter']); ?></strong> reported 
                                            <span class="badge bg-danger"><?php echo htmlspecialchars($rep['target_type']); ?></span><br>
                                            <small><?php echo htmlspecialchars($rep['reason']); ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if(empty($pending_reports)) echo '<p class="text-success">No pending reports!</p>'; ?>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <a href="?tab=feed" class="btn btn-warning w-100">Go to Moderation Queue</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB: USER MANAGEMENT -->
            <?php if($active_tab == 'users'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">User Management</h3>
                    <div class="btn-group">
                        <a href="?tab=users&type=all" class="btn btn-outline-secondary <?php echo $user_filter == 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?tab=users&type=seeker" class="btn btn-outline-secondary <?php echo $user_filter == 'seeker' ? 'active' : ''; ?>">Candidates</a>
                        <a href="?tab=users&type=employer" class="btn btn-outline-secondary <?php echo $user_filter == 'employer' ? 'active' : ''; ?>">Recruiters</a>
                        <a href="?tab=users&type=admin" class="btn btn-outline-secondary <?php echo $user_filter == 'admin' ? 'active' : ''; ?>">Admins</a>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                        <?php if(!empty($u['is_verified'])): ?>
                                            <i class="fas fa-check-circle text-primary" title="Verified"></i>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex">
                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                            <input type="hidden" name="action" value="update_user_role">
                                            <select name="role" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                                                <option value="seeker" <?php echo $u['user_type'] == 'seeker' ? 'selected' : ''; ?>>Candidate</option>
                                                <option value="employer" <?php echo $u['user_type'] == 'employer' ? 'selected' : ''; ?>>Recruiter</option>
                                                <option value="admin" <?php echo $u['user_type'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = match($u['status']) {
                                                'active' => 'bg-success',
                                                'banned' => 'bg-danger',
                                                'suspended' => 'bg-warning',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($u['status'] ?? 'Active'); ?></span>
                                    </td>
                                     <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                            <input type="hidden" name="action" value="update_user_status">
                                            <?php if(($u['status'] ?? 'active') == 'active'): ?>
                                                <button name="status" value="suspended" class="btn btn-sm btn-warning" title="Suspend"><i class="fas fa-pause"></i></button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-danger" title="Ban" onclick="openBanModal(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <?php else: ?>
                                                <button name="status" value="active" class="btn btn-sm btn-success" title="Activate"><i class="fas fa-check"></i></button>
                                            </form>
                                            <?php endif; ?>
                                     </td>
                                 </tr>
                                 <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Ban User Modal -->
            <div class="modal fade" id="banUserModal" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Ban User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to ban <strong id="banUsername"></strong>?</p>
                            <input type="hidden" name="action" value="ban_user_with_reason">
                            <input type="hidden" name="user_id" id="banUserId">
                            <div class="mb-3">
                                <label class="form-label">Reason for Banning (Required)</label>
                                <textarea name="ban_reason" class="form-control" rows="3" required placeholder="Ex: Violation of terms..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Confirm Ban</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                function openBanModal(uid, username) {
                    document.getElementById('banUserId').value = uid;
                    document.getElementById('banUsername').textContent = username;
                    new bootstrap.Modal(document.getElementById('banUserModal')).show();
                }
            </script>

            <!-- TAB: VERIFICATIONS -->
            <?php if($active_tab == 'verifications'): ?>
                <h3 class="mb-4">Employer Verifications</h3>
                 <div class="card shadow-sm">
                     <div class="card-header bg-warning text-dark">Pending Requests</div>
                     <div class="card-body p-0">
                        <?php if(count($verification_requests) > 0): ?>
                             <table class="table table-hover mb-0 align-middle">
                                 <thead>
                                     <tr>
                                         <th>Employer</th>
                                         <th>Document</th>
                                         <th>Date</th>
                                         <th>Actions</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     <?php foreach($verification_requests as $req): ?>
                                         <tr>
                                             <td>
                                                 <strong><?php echo htmlspecialchars($req['username']); ?></strong><br>
                                                 <small><?php echo htmlspecialchars($req['email']); ?></small>
                                             </td>
                                             <td>
                                                 <a href="<?php echo htmlspecialchars($req['document_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                     <i class="fas fa-file-invoice"></i> View Document
                                                 </a>
                                             </td>
                                             <td><?php echo date('M d, H:i', strtotime($req['created_at'])); ?></td>
                                             <td>
                                                 <form method="POST" class="d-flex gap-2">
                                                     <input type="hidden" name="action" value="process_verification">
                                                     <input type="hidden" name="req_id" value="<?php echo $req['request_id']; ?>">
                                                     <input type="hidden" name="user_id" value="<?php echo $req['user_id']; ?>">
                                                     
                                                     <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Reason (for reject)" style="width: 150px;">
                                                     
                                                     <button name="decision" value="approve" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                                     <button name="decision" value="reject" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button>
                                                 </form>
                                             </td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">No pending verification requests.</div>
                        <?php endif; ?>
                     </div>
                 </div>
            <?php endif; ?>

            <!-- TAB: JOB MANAGEMENT -->
            <?php if($active_tab == 'jobs'): ?>
                <h3 class="mb-4">Job Management</h3>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Job Title</th>
                                    <th>Employer</th>
                                    <th>Status</th>
                                    <th>Tags</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($jobs as $j): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($j['title']); ?></strong>
                                        <?php if($j['expires_at']): ?>
                                            <br><small class="text-muted">Exp: <?php echo date('M d', strtotime($j['expires_at'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($j['username']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $j['status'] == 'active' ? 'bg-success' : ($j['status'] == 'pending' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <?php echo ucfirst($j['status']); ?>
                                        </span>
                                    </td>
                                    <form method="POST">
                                        <td>
                                            <input type="text" name="tags" class="form-control form-control-sm" placeholder="Tags..." value="<?php echo htmlspecialchars($j['tags'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="hidden" name="action" value="update_job_details">
                                            <input type="hidden" name="job_id" value="<?php echo $j['job_id']; ?>">
                                            <select name="extend_days" class="form-select form-select-sm d-inline-block w-auto">
                                                <option value="0">Extend...</option>
                                                <option value="7">+7 Days</option>
                                                <option value="30">+30 Days</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button>
                                            
                                            <!-- Approve/Reject Buttons if Pending -->
                                            <?php if($j['status'] == 'pending'): ?>
                                                <div class="btn-group ms-2">
                                                    <!-- Use separate form or button trick. Simplified here with JS or separate forms preferred but keeping inline for brevity -->
                                                     <!-- Actually, nested forms are bad. We should trust the save button for detailed updates. 
                                                          For state change we need another form or just handle it. 
                                                          Let's put approval buttons outside the form above or use js submit. -->
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </form>
                                    <!-- Separate form for status toggle -->
                                    <td>
                                         <?php if($j['status'] == 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="job_id" value="<?php echo $j['job_id']; ?>">
                                                <input type="hidden" name="action" value="job_approve_reject">
                                                <button name="status" value="active" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i></button>
                                                <button name="status" value="closed" class="btn btn-sm btn-danger" title="Reject"><i class="fas fa-times"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- TAB: FEED MODERATION -->
            <?php if($active_tab == 'feed'): ?>
                 <h3 class="mb-4">Feed Moderation</h3>
                 
                 <div class="card shadow-sm">
                     <div class="card-header">Reported Content (<?php echo count($pending_reports); ?> Pending)</div>
                     <div class="card-body p-0">
                         <?php if(count($pending_reports) > 0): ?>
                             <table class="table table-hover mb-0">
                                 <thead>
                                     <tr>
                                         <th>Reporter</th>
                                         <th>Type</th>
                                         <th>Reason</th>
                                         <th>Date</th>
                                         <th>Actions</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     <?php foreach($pending_reports as $rep): ?>
                                         <tr>
                                             <td><?php echo htmlspecialchars($rep['reporter']); ?></td>
                                             <td><span class="badge bg-danger"><?php echo htmlspecialchars($rep['target_type']); ?></span></td>
                                             <td><?php echo htmlspecialchars($rep['reason']); ?></td>
                                             <td><?php echo date('M d, H:i', strtotime($rep['created_at'])); ?></td>
                                             <td>
                                                 <button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Resolve</button>
                                                 <button class="btn btn-sm btn-secondary"><i class="fas fa-times"></i> Dismiss</button>
                                             </td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         <?php else: ?>
                             <div class="p-4 text-center text-muted">
                                 <i class="fas fa-check-circle text-success mb-2" style="font-size: 2rem;"></i><br>
                                 No pending reports. Great job!
                             </div>
                         <?php endif; ?>
                     </div>
                 </div>
            <?php endif; ?>

            <!-- TAB: ANALYTICS -->
            <?php if($active_tab == 'analytics'): ?>
                <h3 class="mb-4">Analytics</h3>
                <div class="row">
                    <!-- User Distribution -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header fw-bold">User Demographics</div>
                            <div class="card-body d-flex align-items-center justify-content-center" style="height: 350px;">
                                <canvas id="userChart"></canvas>
                            </div>
                        </div>
                    </div>
                     <!-- Job Status Distribution -->
                     <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header fw-bold">Job Status Overview</div>
                            <div class="card-body d-flex align-items-center justify-content-center" style="height: 350px;">
                                <canvas id="jobChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        // User Data
                        const userLabels = <?php echo json_encode(array_map('ucfirst', array_keys($user_analytics))); ?>;
                        const userData = <?php echo json_encode(array_values($user_analytics)); ?>;
                        const userCtx = document.getElementById('userChart').getContext('2d');
                        new Chart(userCtx, {
                            type: 'pie',
                            data: {
                                labels: userLabels,
                                datasets: [{
                                    data: userData,
                                    backgroundColor: ['#0d6efd', '#198754', '#6c757d'], // Blue, Green, Gray
                                    hoverOffset: 4
                                }]
                            }
                        });

                        // Job Data
                        const jobLabels = <?php echo json_encode(array_map('ucfirst', array_keys($job_analytics))); ?>;
                        const jobData = <?php echo json_encode(array_values($job_analytics)); ?>;
                        const jobCtx = document.getElementById('jobChart').getContext('2d');
                        new Chart(jobCtx, {
                            type: 'pie', // User requested Pie, though Doughnut is cooler. Keep Pie.
                            data: {
                                labels: jobLabels,
                                datasets: [{
                                    data: jobData,
                                    backgroundColor: ['#ffc107', '#198754', '#dc3545'], // Yellow (Pending), Green (Active), Red (Closed)
                                    hoverOffset: 4
                                }]
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <!-- TAB: SETTINGS -->


        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
