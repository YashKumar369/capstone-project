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

// Handle Actions (Accept/Decline Follow)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'accept_follow') {
        $actor_id = $_POST['actor_id'];
        $notif_id = $_POST['notification_id'];
        
        try {
            // Update Connection
            $update = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE user_id_1 = ? AND user_id_2 = ?");
            $update->execute([$actor_id, $user_id]);
            
            // Mark Notif Read
            $mark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
            $mark->execute([$notif_id]);
            
            // Notify Actor (that follow was accepted)
            $notify_back = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type) VALUES (?, ?, 'follow_accepted')");
            $notify_back->execute([$actor_id, $user_id]);
            
            $message = "You are now connected!";
        } catch (PDOException $e) { $error = "Error accepting request."; }
        
    } elseif ($_POST['action'] == 'decline_follow') {
        $actor_id = $_POST['actor_id'];
        $notif_id = $_POST['notification_id'];
        
        try {
            // Delete Connection Request
            $del = $pdo->prepare("DELETE FROM connections WHERE user_id_1 = ? AND user_id_2 = ?");
            $del->execute([$actor_id, $user_id]);
            
            // Mark Notif Read (or delete it? usually just read)
            $mark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
            $mark->execute([$notif_id]);
            
            $message = "Request declined.";
        } catch (PDOException $e) { $error = "Error declining request."; }
    } elseif ($_POST['action'] == 'mark_read') {
         $notif_id = $_POST['notification_id'];
         $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?")->execute([$notif_id]);
    }
}

// Mark all as read logic (optional)
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ?")->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

// Fetch Notifications
$stmt = $pdo->prepare("
    SELECT n.*, u.username, u.user_type, p.profile_pic, j.title as job_title 
    FROM notifications n
    JOIN users u ON n.actor_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    LEFT JOIN jobs j ON n.reference_id = j.job_id
    WHERE n.recipient_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Set Last Check Time for Header Badge clearing
$_SESSION['last_notif_check'] = date('Y-m-d H:i:s');

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bell"></i> Notifications</h5>
                    <?php if(count($notifications) > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-sm btn-outline-secondary">Mark all read</a>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <?php if(count($notifications) > 0): ?>
                        <?php foreach($notifications as $n): ?>
                            <!-- Highlight Unread: Use blue/info tint -->
                            <div class="list-group-item d-flex align-items-start <?php echo !$n['is_read'] ? 'list-group-item-info' : ''; ?>">
                                <div class="me-3 mt-1">
                                    <?php if(!empty($n['profile_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($n['profile_pic']); ?>" class="rounded-circle" width="40" height="40">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($n['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <!-- Clickable Username -->
                                        <strong><a href="public_profile.php?user_id=<?php echo $n['actor_id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($n['username']); ?></a></strong>
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
                                    </div>
                                    
                                    <?php if($n['type'] == 'new_follow'): ?>
                                        <p class="mb-1">sent you a connection request.</p>
                                        <?php if(!$n['is_read']): ?> <!-- Logic: Show buttons if unread/unhandled -->
                                            <div class="mt-2">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="accept_follow">
                                                    <input type="hidden" name="actor_id" value="<?php echo $n['actor_id']; ?>">
                                                    <input type="hidden" name="notification_id" value="<?php echo $n['notification_id']; ?>">
                                                    <button class="btn btn-sm btn-primary">Accept</button>
                                                </form>
                                                <form method="POST" class="d-inline ms-1">
                                                    <input type="hidden" name="action" value="decline_follow">
                                                    <input type="hidden" name="actor_id" value="<?php echo $n['actor_id']; ?>">
                                                    <input type="hidden" name="notification_id" value="<?php echo $n['notification_id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger">Decline</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted">Request handled.</small>
                                        <?php endif; ?>
                                        
                                    <?php elseif($n['type'] == 'follow_accepted'): ?>
                                        <p class="mb-1">accepted your connection request.</p>
                                        
                                    <?php elseif($n['type'] == 'new_job'): ?>
                                        <p class="mb-1">posted a new job: <a href="mark_read_and_redirect.php?id=<?php echo $n['notification_id']; ?>&url=job_details.php?id=<?php echo $n['reference_id']; ?>"><?php echo htmlspecialchars($n['job_title']); ?></a></p>
                                    
                                    <?php elseif(!empty($n['message'])): ?>
                                        <!-- Generic Message / Context Display -->
                                        <p class="mb-1 text-truncate" style="max-width: 500px;"><?php echo strip_tags($n['message']); ?></p>
                                        <button class="btn btn-sm btn-outline-info mt-1" onclick="showContext('<?php echo htmlspecialchars(addslashes($n['message'])); ?>')">
                                            <i class="fas fa-eye"></i> View Context
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">No notifications.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Context Modal -->
<div class="modal fade" id="contextModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Notification Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="contextModalBody">
        <!-- Content -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function showContext(msg) {
    document.getElementById('contextModalBody').innerHTML = msg; // msg contains HTML (strong tags etc)
    var myModal = new bootstrap.Modal(document.getElementById('contextModal'));
    myModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
