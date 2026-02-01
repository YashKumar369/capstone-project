<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$view_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$type = isset($_GET['type']) ? $_GET['type'] : 'following'; // 'followers' or 'following'

// Check if viewing own list
$is_own_list = ($view_user_id == $_SESSION['user_id']);

// Handle Unfollow Action
// Handle Unfollow Action
// Handle Unfollow Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'unfollow') {
    $target_id = $_POST['target_id'];
    
    // Directional Delete: Only remove MY following of THEM.
    try {
        $del_stmt = $pdo->prepare("DELETE FROM connections WHERE user_id_1 = ? AND user_id_2 = ?");
        if ($del_stmt->execute([$_SESSION['user_id'], $target_id])) {
            // Redirect to refresh
            header("Location: connections_list.php?type=following");
            exit;
        } else {
             echo "<script>alert('Failed to unfollow.');</script>";
        }
    } catch (PDOException $e) {
         echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Fetch Data
$title = ($type == 'followers') ? 'Followers' : 'Following';
$list = [];

try {
    if ($type == 'followers') {
        // Who follows View User? (user_id_1 is follower, user_id_2 is View User)
        $sql = "SELECT u.user_id, u.username, u.user_type, p.full_name, p.profile_pic, p.bio 
                FROM connections c 
                JOIN users u ON c.user_id_1 = u.user_id 
                LEFT JOIN profiles p ON u.user_id = p.user_id 
                WHERE c.user_id_2 = ? AND c.status = 'accepted'";
    } else {
        // Who does View User follow? (user_id_1 is View User, user_id_2 is target)
        $sql = "SELECT u.user_id, u.username, u.user_type, p.full_name, p.profile_pic, p.bio 
                FROM connections c 
                JOIN users u ON c.user_id_2 = u.user_id 
                LEFT JOIN profiles p ON u.user_id = p.user_id 
                WHERE c.user_id_1 = ? AND c.status = 'accepted'";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$view_user_id]);
    $list = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><?php echo $title; ?></h3>
        <a href="<?php echo ($is_own_list) ? 'profile.php' : 'public_profile.php?user_id='.$view_user_id; ?>" class="btn btn-outline-secondary">Back to Profile</a>
    </div>

    <div class="row">
        <?php if(count($list) > 0): ?>
            <?php foreach($list as $u): ?>
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                     <?php if(!empty($u['profile_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($u['profile_pic']); ?>" class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center" style="width: 50px; height: 50px; font-size: 20px;">
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></h5>
                                    <small class="text-muted"><?php echo ucfirst($u['user_type']); ?></small>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="public_profile.php?user_id=<?php echo $u['user_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                
                                <?php if($is_own_list && $type == 'following'): ?>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#unfollowModal" 
                                            data-id="<?php echo $u['user_id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?>">
                                        Unfollow
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted">
                <p>No <?php echo strtolower($title); ?> found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Unfollow Confirmation Modal -->
<div class="modal fade" id="unfollowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Unfollow User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to unfollow <strong id="unfollowUserName"></strong>?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" id="unfollowForm">
            <input type="hidden" name="action" value="unfollow">
            <input type="hidden" name="target_id" id="unfollowTargetId">
            <button type="submit" class="btn btn-danger">Unfollow</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Script to populate modal -->
<script>
    const unfollowModal = document.getElementById('unfollowModal');
    unfollowModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        
        document.getElementById('unfollowTargetId').value = id;
        document.getElementById('unfollowUserName').textContent = name;
    });
</script>

<?php include 'includes/footer.php'; ?>
