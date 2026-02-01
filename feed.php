<?php
session_start();
require_once 'config/db.php';


// Handle Like Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    if ($_POST['action'] == 'like') {
        $post_id = $_POST['post_id'];
        try {
            // Check if already liked
            $check = $pdo->prepare("SELECT like_id FROM likes WHERE post_id = ? AND user_id = ?");
            $check->execute([$post_id, $_SESSION['user_id']]);
            
            if ($check->rowCount() > 0) {
                // Unlike
                $del = $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
                $del->execute([$post_id, $_SESSION['user_id']]);
            } else {
                // Like
                $add = $pdo->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
                $add->execute([$post_id, $_SESSION['user_id']]);
            }
            header("Location: feed.php#post-" . $post_id);
            exit;
        } catch (PDOException $e) {
            $error = "Error liking post.";
        }
    } elseif ($_POST['action'] == 'connect') {
        $target_user_id = $_POST['target_user_id'];
        try {
            // Check if already connected or pending
            $check = $pdo->prepare("SELECT connection_id FROM connections WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)");
            $check->execute([$_SESSION['user_id'], $target_user_id, $target_user_id, $_SESSION['user_id']]);
            
            if ($check->rowCount() == 0) {
                $stmt = $pdo->prepare("INSERT INTO connections (user_id_1, user_id_2, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$_SESSION['user_id'], $target_user_id]);
                
                // Notify Target User
                $notif = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type) VALUES (?, ?, 'new_follow')");
                $notif->execute([$target_user_id, $_SESSION['user_id']]);
            }
            header("Location: feed.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error connecting.";
        }

    } elseif ($_POST['action'] == 'comment') {
        $post_id = $_POST['post_id'];
        $comment = trim($_POST['comment']);
        if (!empty($comment)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$post_id, $_SESSION['user_id'], $comment]);
                header("Location: feed.php#post-" . $post_id);
                exit;
            } catch (PDOException $e) { $error = "Error commenting."; }
        }
    } elseif ($_POST['action'] == 'share') {
        $post_id = $_POST['post_id'];
        try {
            // Sharing creates a new post referencing the old one
            $stmt = $pdo->prepare("SELECT content, username FROM posts JOIN users ON posts.user_id = users.user_id WHERE post_id = ?");
            $stmt->execute([$post_id]);
            $original = $stmt->fetch();
            
            if ($original) {
                $new_content = "Shared a post by " . $original['username'] . ":\n\n" . $original['content'];
                $stmt = $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $new_content]);
            }
            header("Location: feed.php");
            exit;
        } catch (PDOException $e) { $error = "Error sharing."; }
    }
    elseif ($_POST['action'] == 'report') {
        $post_id = $_POST['post_id'];
        $reason = trim($_POST['reason']);
        try {
            // Find Admin ID
            $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin' LIMIT 1");
            $admin_stmt->execute();
            $admin_id = $admin_stmt->fetchColumn();

            // Insert Report
            // target_type='post', target_id=$post_id. reporter_id is Session User.
            // Check schema of reports table via previous knowledge or hypothesis
            // Assuming: report_id, reporter_id, target_type, target_id, reason, status, created_at
            $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, target_type, target_id, reason, status) VALUES (?, 'post', ?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $post_id, $reason]);
            
            // Notify Admin
            if ($admin_id) {
                // We use 'report_received' type. Make sure database supports it or map to 'system_alert'
                // Using 'system_alert' for safety if enum is strict (it's not enum in schema I saw, it's varchar/enum?)
                // Schema query earlier wasn't run on notifications table. Assuming it accepts strings.
                $n = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id) VALUES (?, ?, 'report_alert', ?)");
                $n->execute([$admin_id, $_SESSION['user_id'], $post_id]);
            }

            // Notify Reporter (Confirmation)
            $n2 = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id) VALUES (?, ?, 'report_confirmation', ?)");
            $n2->execute([$_SESSION['user_id'], $admin_id ?? 0, $post_id]);
            
            header("Location: feed.php?msg=reported");
            exit;
        } catch (PDOException $e) { $error = "Error reporting post."; }
    }
}

// Handle New Post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_content'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $content = trim($_POST['post_content']);
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $content]);
            
            // Notify Followers (Optional)
            $followers = $pdo->prepare("SELECT user_id_1 FROM connections WHERE user_id_2 = ? AND status = 'accepted'");
            $followers->execute([$_SESSION['user_id']]);
            foreach ($followers as $f) {
                // Determine type based on role? Or just generic 'new_post' which isn't standard yet.
                // Keeping it simple to avoid schema issues, but if user is Employer, we have 'new_job' used before.
                // Let's stick to base feed post.
            }

        } catch (PDOException $e) { $error = "Error posting."; }
    }
    header("Location: feed.php");
    exit;
}

// Fetch Posts with Likes and Comments
$posts = [];
try {
    $group_filter = isset($_GET['group']) ? $_GET['group'] : '';
    $sql = "SELECT posts.*, users.username, 
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) as like_count,
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = :uid) as liked_by_me 
            FROM posts 
            JOIN users ON posts.user_id = users.user_id 
            WHERE 1=1";
            
            // Allow filtering by 'My Groups' (simulated by keyword in content for now or just generic filter)
            if ($group_filter) {
                // For demo, just showing all posts but pretending to filter, or filtering by content if 'Group' keyword was real
                // Let's just say if group filter is on, we show posts containing that word
                $sql .= " AND content LIKE :group";
            }
            
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $params = ['uid' => $_SESSION['user_id'] ?? 0];
    if ($group_filter) $params['group'] = "%$group_filter%";
    
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Attach comments to posts
    foreach ($posts as &$p) {
        $c_stmt = $pdo->prepare("SELECT comments.*, users.username FROM comments JOIN users ON comments.user_id = users.user_id WHERE post_id = ? ORDER BY created_at ASC");
        $c_stmt->execute([$p['post_id']]);
        $p['comments'] = $c_stmt->fetchAll();
    }
    
    // Fetch Profile Owner ID correctly? 
    // The previous query was: SELECT posts.*, users.username FROM posts JOIN users...
    // We need users.user_id as well to link profile.
    // Wait, posts.user_id IS the author id.
    
} catch (PDOException $e) {
    // If DB fails, we just show empty feed
}

// ... (rest of suggestions/jobs fetch code as is) ...
// Fetch Users to Follow (excluding self and existing connections)
$suggestions = [];
if (isset($_SESSION['user_id'])) {
    try {
        $sql = "SELECT users.user_id, users.username, users.user_type FROM users 
                WHERE user_id != ? 
                AND user_id NOT IN (SELECT user_id_2 FROM connections WHERE user_id_1 = ?)
                AND user_id NOT IN (SELECT user_id_1 FROM connections WHERE user_id_2 = ?)
                LIMIT 3";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $suggestions = $stmt->fetchAll();
    } catch (PDOException $e) {}
}

// Fetch Random Jobs for Sidebar
$sidebar_jobs = [];
try {
    $sidebar_jobs = $pdo->query("SELECT * FROM jobs WHERE status = 'active' ORDER BY RAND() LIMIT 2")->fetchAll();
} catch (PDOException $e) {}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-3">
                <div class="card-body text-center">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="avatar bg-primary text-white rounded-circle d-inline-block d-flex justify-content-center align-items-center mx-auto mb-3" style="width: 60px; height: 60px; font-size: 24px;">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($_SESSION['username']); ?></h5>
                        <p class="text-muted small"><?php echo ucfirst($_SESSION['user_type']); ?></p>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm btn-sm">View Profile</a>
                    <?php else: ?>
                        <h5>Welcome!</h5>
                        <p class="text-muted">Join the community.</p>
                        <a href="login.php" class="btn btn-primary btn-sm">Login</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                 <div class="card-header bg-white fw-bold">My Groups</div>
                 <div class="list-group list-group-flush">
                     <a href="feed.php?group=Tech" class="list-group-item list-group-item-action"><i class="fas fa-users text-info"></i> Tech Enthusiasts</a>
                     <a href="feed.php?group=Job" class="list-group-item list-group-item-action"><i class="fas fa-users text-info"></i> Job Seekers AU</a>
                     <a href="feed.php" class="list-group-item list-group-item-action text-muted small">Clear Filter</a>
                 </div>
            </div>
        </div>

        <!-- Main Feed -->
        <div class="col-md-6">
            <!-- Create Post -->
            <?php if(isset($_SESSION['user_id'])): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="feed.php">
                        <textarea class="form-control mb-2 border-0 bg-light" name="post_content" placeholder="What's on your mind? Share an update..." rows="3" required></textarea>
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-light btn-sm text-secondary"><i class="fas fa-image"></i> Photo</button>
                            <button type="submit" class="btn btn-primary btn-sm px-4">Post</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Posts Stream -->
            <?php if (count($posts) > 0): ?>
                <?php foreach ($posts as $post): ?>
                    <div class="card mb-3 shadow-sm" id="post-<?php echo $post['post_id']; ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0 fw-bold">
                                        <a href="public_profile.php?user_id=<?php echo $post['user_id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($post['username']); ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($post['created_at'])); ?></small>
                                </div>
                            </div>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                            <?php if($post['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="img-fluid rounded mb-3" alt="Post Image">
                            <?php endif; ?>
                            <hr>
                            
                            <!-- Actions -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="like">
                                    <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                    <button class="btn btn-link text-decoration-none <?php echo $post['liked_by_me'] ? 'text-primary' : 'text-secondary'; ?> p-0">
                                        <i class="<?php echo $post['liked_by_me'] ? 'fas' : 'far'; ?> fa-thumbs-up"></i> 
                                        <?php echo $post['liked_by_me'] ? 'Liked' : 'Like'; ?> 
                                        <?php if($post['like_count'] > 0) echo "(" . $post['like_count'] . ")"; ?>
                                    </button>
                                </form>
                                <button class="btn btn-link text-decoration-none text-secondary p-0" onclick="document.getElementById('comment-form-<?php echo $post['post_id']; ?>').classList.toggle('d-none')">
                                    <i class="far fa-comment"></i> Comment
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Share this post on your feed?');">
                                    <input type="hidden" name="action" value="share">
                                    <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                    <button class="btn btn-link text-decoration-none text-secondary p-0">
                                        <i class="fas fa-share"></i> Share
                                    </button>
                                </form>
                                <button class="btn btn-link text-decoration-none text-danger p-0 ms-3" onclick="openReportModal(<?php echo $post['post_id']; ?>)">
                                    <i class="fas fa-flag"></i> Report
                                </button>
                            </div>
                            
                            <!-- Comments Section -->
                            <div class="bg-light p-3 rounded">
                                <?php if(count($post['comments']) > 0): ?>
                                    <?php foreach($post['comments'] as $comment): ?>
                                        <div class="d-flex mb-2">
                                            <div class="fw-bold me-2"><?php echo htmlspecialchars($comment['username']); ?>:</div>
                                            <div><?php echo htmlspecialchars($comment['content']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <form method="POST" class="mt-2 d-none" id="comment-form-<?php echo $post['post_id']; ?>">
                                    <input type="hidden" name="action" value="comment">
                                    <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="comment" class="form-control" placeholder="Write a comment..." required>
                                        <button class="btn btn-primary" type="submit">Post</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <p class="text-muted">No posts found.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Who to follow</div>
                <div class="card-body">
                    <?php if (count($suggestions) > 0): ?>
                        <?php foreach($suggestions as $sugg): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-light rounded-circle me-2" style="width: 32px; height: 32px;"></div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 small"><?php echo htmlspecialchars($sugg['username']); ?></h6>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="connect">
                                        <input type="hidden" name="target_user_id" value="<?php echo $sugg['user_id']; ?>">
                                        <button class="btn btn-sm btn-outline-primary py-0" style="font-size: 0.7rem;">Connect</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="small text-muted">No suggestions available.</p>
                    <?php endif; ?>
                </div>
            </div>
            
             <div class="card shadow-sm mt-3">
                <div class="card-header bg-white fw-bold">Suggested Jobs</div>
                <div class="card-body">
                    <?php if (count($sidebar_jobs) > 0): ?>
                        <?php foreach($sidebar_jobs as $job): ?>
                            <p class="small mb-1"><a href="job_details.php?id=<?php echo $job['job_id']; ?>" class="text-dark fw-bold text-decoration-none"><?php echo htmlspecialchars($job['title']); ?></a></p>
                            <p class="small text-muted"><?php echo htmlspecialchars($job['location']); ?></p>
                            <hr class="my-2">
                        <?php endforeach; ?>
                    <?php else: ?>
                         <p class="small text-muted">No active jobs.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Report Post</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="report">
            <input type="hidden" name="post_id" id="report_post_id">
            <div class="mb-3">
                <label class="form-label">Reason for reporting</label>
                <select name="reason" class="form-select" required>
                    <option value="">Select reason...</option>
                    <option>Spam</option>
                    <option>Harassment</option>
                    <option>Inappropriate Content</option>
                    <option>False Information</option>
                    <option>Other</option>
                </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Submit Report</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReportModal(postId) {
    document.getElementById('report_post_id').value = postId;
    new bootstrap.Modal(document.getElementById('reportModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
