<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBook - Job Placement & Social Network</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?php 
        if(isset($_SESSION['user_type'])) {
            if($_SESSION['user_type'] == 'admin') echo 'admin.php?tab=dashboard';
            elseif($_SESSION['user_type'] == 'employer') echo 'employer_dashboard.php';
            else echo 'index.php';
        } else {
            echo 'index.php';
        }
    ?>"><i class="fas fa-briefcase"></i> JBook</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <?php 
        // Calculate Counts (Notifications & Messages)
        $unread_notif = 0;
        $unread_msg = 0;
        if(isset($_SESSION['user_id'])) {
            try {
                // Notifications
                $sql = "SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0";
                $params = [$_SESSION['user_id']];
                if (isset($_SESSION['last_notif_check'])) {
                    $sql .= " AND created_at > ?";
                    $params[] = $_SESSION['last_notif_check'];
                }
                $notif_count_stmt = $pdo->prepare($sql);
                $notif_count_stmt->execute($params);
                $unread_notif = $notif_count_stmt->fetchColumn();

                // Messages
                $msg_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
                $msg_count_stmt->execute([$_SESSION['user_id']]);
                $unread_msg = $msg_count_stmt->fetchColumn();

            } catch(Exception $e) {}
        }
      ?>

      <ul class="navbar-nav me-auto">
        <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
            <!-- Admin Navigation -->
            <li class="nav-item">
                <a class="nav-link" href="admin.php?tab=dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin.php?tab=users"><i class="fas fa-users"></i> Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin.php?tab=jobs"><i class="fas fa-briefcase"></i> Jobs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin.php?tab=feed"><i class="fas fa-flag"></i> Feed</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin.php?tab=analytics"><i class="fas fa-chart-line"></i> Analytics</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin.php?tab=settings"><i class="fas fa-cogs"></i> Config</a>
            </li>
        <?php elseif(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'employer'): ?>
             <!-- Employer Navigation -->
            <li class="nav-item">
                <a class="nav-link" href="employer_dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="employer_dashboard.php?tab=pipeline"><i class="fas fa-user-check"></i> Candidates</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="feed.php">Feed</a>
            </li>
             <li class="nav-item">
                <a class="nav-link position-relative" href="messages.php">
                    Messages
                    <?php if($unread_msg > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem;">
                            <?php echo $unread_msg; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php else: ?>
            <!-- Standard Navigation -->
            <li class="nav-item">
                <a class="nav-link" href="index.php">Home</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="jobs.php">Find Jobs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="feed.php">Social Feed</a>
            </li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="messages.php">
                        Messages
                        <?php if($unread_msg > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem;">
                                <?php echo $unread_msg; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Search Bar -->
            <li class="nav-item me-3">
                <form action="search.php" method="GET" class="d-flex">
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="search" name="q" placeholder="Search Users..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </li>

            <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] != 'admin'): ?>
            <li class="nav-item me-2">
                <a class="nav-link position-relative" href="notifications.php">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if($unread_notif > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                            <?php echo $unread_notif; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <?php if($_SESSION['user_type'] == 'employer'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="post_job.php"><i class="fas fa-plus"></i> Post Job</a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> <?php echo htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username']); ?></a>
            </li>
            <li class="nav-item">
                <a class="btn btn-danger btn-sm text-white ms-2" href="logout.php">Logout</a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="login.php">Login</a>
            </li>
            <li class="nav-item ms-2">
                <a class="btn btn-light text-primary fw-bold" href="register.php">Register</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 flex-grow-1">
