<?php
session_start();
require_once 'config/db.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.username, u.user_type, p.full_name, p.profile_pic, p.bio 
            FROM users u 
            LEFT JOIN profiles p ON u.user_id = p.user_id 
            WHERE u.username LIKE ? OR p.full_name LIKE ?
        ");
        $term = "%$query%";
        $stmt->execute([$term, $term]);
        $results = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Search error.";
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <h3 class="mb-4">Search Results for "<?php echo htmlspecialchars($query); ?>"</h3>
    
    <div class="row">
        <?php if(count($results) > 0): ?>
            <?php foreach($results as $user): ?>
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="me-3">
                                <?php if(!empty($user['profile_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" class="rounded-circle" width="60" height="60" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center" style="width: 60px; height: 60px; font-size: 24px;">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h5>
                                <p class="text-muted small mb-1"><?php echo ucfirst($user['user_type']); ?></p>
                                <p class="text-muted small mb-2 text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></p>
                                <a href="public_profile.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-muted">No users found matching your search.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
