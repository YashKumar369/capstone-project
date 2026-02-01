<?php
session_start();
require_once 'config/db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Fetch distinct locations for dropdown
$loc_stmt = $pdo->query("SELECT DISTINCT location FROM jobs WHERE status = 'active' AND location IS NOT NULL AND location != '' ORDER BY location ASC");
$locations = $loc_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build Query
$sql = "SELECT jobs.*, users.username as company_name FROM jobs JOIN users ON jobs.employer_id = users.user_id WHERE jobs.status = 'active'";
$params = [];

if ($search) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($location) {
    $sql .= " AND location LIKE ?";
    $params[] = "%$location%";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
    $error = "Could not fetch jobs: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-light border-0 p-4">
                <h3 class="mb-3">Find your next opportunity</h3>
                <form action="jobs.php" method="GET" class="row g-2">
                    <div class="col-md-5 position-relative">
                        <input type="text" class="form-control" name="search" id="search-input" placeholder="Job title or keywords" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        <div id="suggestion-box" class="list-group position-absolute shadow start-0 end-0" style="z-index: 1000; display:none; top: 100%;"></div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="location">
                            <option value="">All Locations</option>
                            <?php foreach($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($location == $loc) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (count($jobs) > 0): ?>
                <?php foreach ($jobs as $job): ?>
                    <!-- Standard card without clean hover (hover-shadow removed elsewhere if requested, but user said 'home page is okay' for popups, implying standard hover is fine here, but earlier I removed it? Wait. I should keep it consistent. The instructions in the last turn were applied. I won't change card classes now unless needed. Just context.) -->
                    <div class="card mb-3 shadow-sm" style="cursor: pointer;" onclick="window.location='job_details.php?id=<?php echo $job['job_id']; ?>';">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title text-primary mb-1">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </h5>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($job['type'])); ?></span>
                            </div>
                            <h6 class="card-subtitle mb-2 text-muted">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?> &nbsp; 
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?> &nbsp;
                                <i class="fas fa-users"></i> Vacancies: <?php echo htmlspecialchars($job['vacancies'] ?? 1); ?>
                            </h6>
                            <p class="card-text text-truncate"><?php echo strip_tags(substr($job['description'], 0, 200)) . '...'; ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Posted on <?php echo date('M d, Y', strtotime($job['created_at'])); ?></small>
                                <span class="btn btn-outline-primary btn-sm">View Details</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h4>No jobs found</h4>
                    <p>Try adjusting your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const suggestionBox = document.getElementById('suggestion-box');

    searchInput.addEventListener('input', function() {
        const query = this.value;

        if (query.length < 2) {
            suggestionBox.style.display = 'none';
            suggestionBox.innerHTML = '';
            return;
        }

        fetch('get_job_suggestions.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '';
                    data.forEach(title => {
                        html += `<a href="#" class="list-group-item list-group-item-action suggestion-item">${title}</a>`;
                    });
                    suggestionBox.innerHTML = html;
                    suggestionBox.style.display = 'block';
                } else {
                    suggestionBox.style.display = 'none';
                }
            })
            .catch(err => console.error('Error fetching suggestions:', err));
    });

    // Handle click on suggestion
    suggestionBox.addEventListener('click', function(e) {
        if (e.target.classList.contains('suggestion-item')) {
            e.preventDefault();
            searchInput.value = e.target.innerText;
            suggestionBox.style.display = 'none';
            // Optional: Submit form immediately interactively? Or just fill? 
            // User request: "suggested on a drop down bar". Usually filling is safer so user can add location.
        }
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionBox.contains(e.target)) {
            suggestionBox.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
