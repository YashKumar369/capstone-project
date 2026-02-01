<?php
session_start();
require_once 'config/db.php';

if (!isset($_GET['user_id'])) {
    header("Location: index.php");
    exit;
}

$profile_id = $_GET['user_id'];
$viewer_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user = null;
$jobs = [];
$connection_status = null; // null, 'pending', 'accepted'

// 1. Fetch Profile Details
try {
    $stmt = $pdo->prepare("SELECT u.username, u.email, u.user_type, p.* FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?");
    $stmt->execute([$profile_id]);
    $user = $stmt->fetch();

    if (!$user) die("User not found.");

    // 2. Log Profile View (if viewer is different and logged in)
    if ($viewer_id && $viewer_id != $profile_id) {
        $v_check = $pdo->prepare("SELECT view_id FROM profile_views WHERE viewer_id = ? AND profile_owner_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $v_check->execute([$viewer_id, $profile_id]);
        if ($v_check->rowCount() == 0) {
            $pdo->prepare("INSERT INTO profile_views (viewer_id, profile_owner_id) VALUES (?, ?)")->execute([$viewer_id, $profile_id]);
        }
    }

    // 3. Fetch Jobs (if employer)
    if ($user['user_type'] == 'employer') {
        $j_stmt = $pdo->prepare("SELECT * FROM jobs WHERE employer_id = ? AND status = 'active' ORDER BY created_at DESC");
        $j_stmt->execute([$profile_id]);
        $jobs = $j_stmt->fetchAll();
    }

    // 4. Check Connection Status (Unidirectional)
    if ($viewer_id) {
        // My connection to them (Follow status)
        $my_stmt = $pdo->prepare("SELECT status FROM connections WHERE user_id_1 = ? AND user_id_2 = ?");
        $my_stmt->execute([$viewer_id, $profile_id]);
        $my_conn = $my_stmt->fetch();
        
        $my_conn = $my_stmt->fetch();
        
        $connection_status = $my_conn ? $my_conn['status'] : null;

        // Ensure Admin cannot follow, but Seeker/Employer can follow each other
        $can_connect = ($user['user_type'] != 'admin' && isset($_SESSION['user_type']) && $_SESSION['user_type'] != 'admin');

        // Their connection to me (Incoming request)
        $their_stmt = $pdo->prepare("SELECT status FROM connections WHERE user_id_1 = ? AND user_id_2 = ?");
        $their_stmt->execute([$profile_id, $viewer_id]);
        $their_conn = $their_stmt->fetch();
        
        // If they requested to follow me, mark it
        $incoming_request_status = $their_conn ? $their_conn['status'] : null;
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle Connection Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $viewer_id) {
    if ($_POST['action'] == 'connect') {
        // Send request
         $stmt = $pdo->prepare("INSERT INTO connections (user_id_1, user_id_2, status) VALUES (?, ?, 'pending')");
         $stmt->execute([$viewer_id, $profile_id]);
         
         // Notification
         $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type) VALUES (?, ?, 'new_follow')")->execute([$profile_id, $viewer_id]);
         
         header("Refresh:0"); // Reload to see change
         exit;
    } elseif ($_POST['action'] == 'accept_connection') {
        // Accept request from profile owner
        $stmt = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE user_id_1 = ? AND user_id_2 = ?");
        $stmt->execute([$profile_id, $viewer_id]);
        
        // Notify back
        $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type) VALUES (?, ?, 'follow_accepted')")->execute([$profile_id, $viewer_id]);
        
        header("Refresh:0");
        exit;
    } elseif ($_POST['action'] == 'decline_connection') {
        // Decline request from profile owner
        $stmt = $pdo->prepare("DELETE FROM connections WHERE user_id_1 = ? AND user_id_2 = ?");
        $stmt->execute([$profile_id, $viewer_id]);
        
        header("Refresh:0");
        exit;
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <!-- Profile Sidebar -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                     <?php if(!empty($user['profile_pic'])): ?>
                        <div class="rounded-circle mx-auto mb-3" style="width: 140px; height: 140px; background-image: url('<?php echo htmlspecialchars($user['profile_pic']); ?>'); background-size: cover; background-position: center;"></div>
                    <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3" style="width: 120px; height: 120px; font-size: 48px;">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="fw-bold">
                        <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                        <?php if(!empty($user['is_verified'])): ?>
                            <i class="fas fa-check-circle text-primary" title="Verified" style="font-size: 0.6em; vertical-align: middle;"></i>
                        <?php endif; ?>
                    </h3>
                    <span class="badge bg-secondary mb-3"><?php echo ucfirst($user['user_type']); ?></span>
                    
                    <p class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['location'] ?: 'Location N/A'); ?></p>
                    
                    <!-- Helper PHP: Initialize if not set -->
                    <?php 
                        if(!isset($incoming_request_status)) $incoming_request_status = null;
                        if(!isset($connection_status)) $connection_status = null;
                    ?>

                    <!-- Follow / Connect Section -->
                    <?php if($viewer_id && $viewer_id != $profile_id): ?>
                        <div class="d-grid gap-2 mb-3">
                            
                            <!-- 1. Handle Incoming Requests (They want to follow me) -->
                            <?php if($incoming_request_status == 'pending'): ?>
                                <div class="card bg-light border-warning mb-2">
                                    <div class="card-body p-2">
                                        <small class="d-block text-center text-muted mb-2">Requested to follow you</small>
                                        <div class="d-flex gap-2">
                                            <form method="POST" class="flex-grow-1">
                                                <input type="hidden" name="action" value="accept_connection">
                                                <button class="btn btn-sm btn-success w-100"><i class="fas fa-check"></i> Accept</button>
                                            </form>
                                            <form method="POST" class="flex-grow-1">
                                                <input type="hidden" name="action" value="decline_connection">
                                                <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-times"></i> Decline</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- 2. Handle Outgoing Follow (I want to follow them) -->
                            <?php if(!$connection_status): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="connect">
                                    <button class="btn btn-primary w-100"><i class="fas fa-user-plus"></i> Follow</button>
                                </form>
                            <?php elseif($connection_status == 'pending'): ?>
                                <button class="btn btn-secondary w-100" disabled><i class="fas fa-clock"></i> Request Sent</button>
                            <?php elseif($connection_status == 'accepted'): ?>
                                <button class="btn btn-success w-100" disabled><i class="fas fa-check"></i> Following</button>
                            <?php endif; ?>
                            
                            <a href="messages.php?receiver_id=<?php echo $profile_id; ?>" class="btn btn-outline-primary"><i class="fas fa-envelope"></i> Message</a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats -->
                    <?php
                        // Reuse stat logic
                         $stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE user_id_2 = ? AND status = 'accepted'");
                         $stmt->execute([$profile_id]);
                         $followers = $stmt->fetchColumn();
                         
                         $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE user_id_1 = ? AND status = 'accepted'");
                         $stmt2->execute([$profile_id]);
                         $following = $stmt2->fetchColumn();
                    ?>
                     <div class="d-flex justify-content-center gap-4 mt-3">
                        <div class="text-center">
                            <a href="connections_list.php?user_id=<?php echo $profile_id; ?>&type=followers" class="text-decoration-none text-dark">
                                <h5 class="mb-0 fw-bold"><?php echo $followers; ?></h5>
                                <small class="text-muted">Followers</small>
                            </a>
                        </div>
                        <div class="text-center">
                            <a href="connections_list.php?user_id=<?php echo $profile_id; ?>&type=following" class="text-decoration-none text-dark">
                                <h5 class="mb-0 fw-bold"><?php echo $following; ?></h5>
                                <small class="text-muted">Following</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header bg-white fw-bold">About</div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($user['bio'] ?: 'No bio available.')); ?></p>
                    <?php if($user['skills']): ?>
                        <hr>
                        <strong>Skills/Areas:</strong><br>
                        <?php foreach(explode(',', $user['skills']) as $skill): ?>
                            <span class="badge bg-light text-dark border me-1"><?php echo trim($skill); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content (Jobs) -->
        <div class="col-md-8">
            <?php if($user['user_type'] == 'employer'): ?>
                <h4 class="mb-4">Active Job Listings</h4>
                <?php if(count($jobs) > 0): ?>
                    <div class="list-group shadow-sm">
                        <?php foreach($jobs as $job): ?>
                            <a href="job_details.php?id=<?php echo $job['job_id']; ?>" class="list-group-item list-group-item-action p-4">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                    <h5 class="mb-1 text-primary fw-bold"><?php echo htmlspecialchars($job['title']); ?></h5>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($job['created_at'])); ?></small>
                                </div>
                                <p class="mb-2"><i class="fas fa-map-marker-alt text-danger"></i> <?php echo htmlspecialchars($job['location']); ?> &bull; <i class="fas fa-clock text-info"></i> <?php echo ucfirst($job['type']); ?></p>
                                <p class="mb-1 text-secondary"><?php echo substr(htmlspecialchars($job['description']), 0, 150); ?>...</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No active jobs listed at the moment.</div>
                <?php endif; ?>
            <?php else: ?>
                <!-- If regular user, maybe show posts? For now just generic profile -->
                <div class="alert alert-secondary">This user is a Job Seeker.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
