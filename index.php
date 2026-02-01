<?php
session_start();
require_once 'config/db.php';
include 'includes/header.php';
?>

<?php if(isset($_SESSION['user_id'])): ?>
    <div class="mb-5">
        <h1 class="display-5 text-primary fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p class="text-muted">Here's what's happening in your network.</p>
    </div>

    <!-- Personalized Dashboard -->
    <div class="row">
        <!-- Recent Jobs -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary">Recent Jobs</h5>
                    <a href="jobs.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php
                        // Fetch suggested jobs
                        try {
                            $idx_jobs = $pdo->query("SELECT * FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 3")->fetchAll(); 
                            if(count($idx_jobs) > 0) {
                                foreach($idx_jobs as $j) {
                                    echo '<a href="job_details.php?id='.$j['job_id'].'" class="list-group-item list-group-item-action">';
                                    echo '<div class="d-flex w-100 justify-content-between">';
                                    echo '<h6 class="mb-1">'.htmlspecialchars($j['title']).'</h6>';
                                    echo '<small>'.date('M d', strtotime($j['created_at'])).'</small>';
                                    echo '</div>';
                                    echo '<small class="text-muted">'.htmlspecialchars($j['location']).'</small>';
                                    echo '</a>';
                                }
                            } else {
                                echo '<div class="p-3 text-center text-muted">No jobs posted yet.</div>';
                            }
                        } catch(Exception $e) { echo '<div class="p-3 text-danger">Error loading jobs.</div>'; }
                    ?>
                </div>
            </div>
        </div>

        <!-- Recent Feed -->
        <div class="col-md-6">
             <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-success">Community Feed</h5>
                    <a href="feed.php" class="btn btn-sm btn-outline-success">View Feed</a>
                </div>
                 <div class="list-group list-group-flush">
                    <?php
                        // Fetch recent posts
                        try {
                            $idx_posts = $pdo->query("SELECT posts.content, profiles.full_name, users.username FROM posts JOIN users ON posts.user_id = users.user_id LEFT JOIN profiles ON users.user_id = profiles.user_id ORDER BY posts.created_at DESC LIMIT 3")->fetchAll();
                            if(count($idx_posts) > 0) {
                                foreach($idx_posts as $p) {
                                    echo '<div class="list-group-item">';
                                    echo '<strong>'.htmlspecialchars($p['full_name'] ?: $p['username']).'</strong>';
                                    echo '<p class="mb-1 text-muted small">'.substr(htmlspecialchars($p['content']), 0, 80).'...</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="p-3 text-center text-muted">No posts yet.</div>';
                            }
                        } catch(Exception $e) { echo '<div class="p-3 text-danger">Error loading posts.</div>'; }
                    ?>
                 </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Public Landing Page -->
    <div class="row align-items-center mb-5">
        <div class="col-md-6">
            <h1 class="display-4 fw-bold text-primary">Connect. Apply. Grow.</h1>
            <p class="lead text-muted">JBook is the first platform merging professional networking with job hunting. Build your profile, connect with employers, and land your dream job.</p>
            <div class="mt-4">
                <a href="register.php" class="btn btn-primary btn-lg me-2">Get Started</a>
                <a href="jobs.php" class="btn btn-outline-secondary btn-lg">Browse Jobs</a>
            </div>
        </div>
        <!-- (Ideally add a Hero Image here via CSS or img tag if assets available) -->
    </div>

    <div class="row text-center mt-5">
        <div class="col-md-4 mb-4">
            <a href="jobs.php" class="text-decoration-none text-dark">
                <div class="card home-card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-search fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Find Jobs</h5>
                        <p class="card-text">Access thousands of job listings tailored to your skills and preferences.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="feed.php" class="text-decoration-none text-dark">
                 <div class="card home-card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-success mb-3"></i>
                        <h5 class="card-title">Build Connections</h5>
                        <p class="card-text">Network with industry professionals and peers to expand your career opportunities.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="messages.php" class="text-decoration-none text-dark">
                <div class="card home-card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <i class="fas fa-comments fa-3x text-info mb-3"></i>
                        <h5 class="card-title">Chat Directly</h5>
                        <p class="card-text">Communicate directly with employers and connections via our messaging system.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
