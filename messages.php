<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? $_GET['receiver_id'] : null;
$message = '';
$error = '';

// Send Message
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $receiver_id = $_POST['receiver_id'];
    $content = trim($_POST['content']);
    
    if (!empty($content) && !empty($receiver_id)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
            if ($stmt->execute([$user_id, $receiver_id, $content])) {
                // Redirect to avoid resubmission
                header("Location: messages.php?receiver_id=" . $receiver_id);
                exit;
            } else {
                $error = "Failed to send message.";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Conversations (Users communicated with)
$conversations = [];
try {
    $sql = "SELECT DISTINCT users.user_id, users.username, users.user_type 
            FROM users 
            JOIN messages ON (users.user_id = messages.sender_id AND messages.receiver_id = ?) 
                          OR (users.user_id = messages.receiver_id AND messages.sender_id = ?)
            WHERE users.user_id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Could not fetch conversations.";
}

// Fetch Messages for current selected user
$current_chat = [];
$receiver_details = null;
if ($receiver_id) {
    // Get receiver details
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->execute([$receiver_id]);
        $receiver_details = $stmt->fetch();
        
        // Mark as read
        $update = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $update->execute([$receiver_id, $user_id]);

        // Get chat history
        // Get chat history with Sender Type
        $sql = "SELECT messages.*, users.user_type as sender_type, users.username as sender_name 
                FROM messages 
                JOIN users ON messages.sender_id = users.user_id
                WHERE (sender_id = ? AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = ?) 
                ORDER BY created_at ASC";
        $chatStmt = $pdo->prepare($sql);
        $chatStmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
        $current_chat = $chatStmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error loading chat.";
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <!-- Sidebar List of Conversations -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Messages</h5>
                </div>
                <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                    <?php if (count($conversations) > 0): ?>
                        <?php foreach ($conversations as $convo): ?>
                            <a href="messages.php?receiver_id=<?php echo $convo['user_id']; ?>" 
                               class="list-group-item list-group-item-action d-flex align-items-center <?php echo ($receiver_id == $convo['user_id']) ? 'active' : ''; ?>">
                                <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                                    <?php echo strtoupper(substr($convo['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 <?php echo ($receiver_id == $convo['user_id']) ? 'text-white' : 'text-dark'; ?>"><?php echo htmlspecialchars($convo['username']); ?></h6>
                                    <small class="<?php echo ($receiver_id == $convo['user_id']) ? 'text-light text-opacity-75' : 'text-muted'; ?>"><?php echo ucfirst($convo['user_type']); ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">No conversations yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <?php if ($receiver_details): ?>
                        <h5 class="mb-0">Chat with <?php echo htmlspecialchars($receiver_details['username']); ?></h5>
                    <?php else: ?>
                        <h5 class="mb-0">Select a conversation</h5>
                    <?php endif; ?>
                </div>
                
                <div class="card-body d-flex flex-column" style="height: 500px;">
                    <!-- Messages Display -->
                    <div class="flex-grow-1 overflow-auto mb-3 px-2" id="chat-box">
                        <?php if ($receiver_id): ?>
                            <?php if (count($current_chat) > 0): ?>
                                <?php foreach ($current_chat as $msg): ?>
                                    <div class="d-flex <?php echo ($msg['sender_id'] == $user_id) ? 'justify-content-end' : 'justify-content-start'; ?> mb-3">
                                        <?php
                                            // Determine Bubble Style
                                            if ($msg['sender_id'] == $user_id) {
                                                // Me
                                                $bubbleClass = 'bg-primary text-white';
                                                $mutedClass = 'text-white-50';
                                            } else {
                                                // Them
                                                $mutedClass = 'text-muted';
                                                if ($msg['sender_type'] == 'employer') {
                                                    $bubbleClass = 'bg-warning bg-opacity-25 border border-warning text-dark';
                                                } else {
                                                    $bubbleClass = 'bg-light border text-dark';
                                                }
                                            }
                                        ?>
                                        <div class="card <?php echo $bubbleClass; ?>" style="max-width: 70%;">
                                            <div class="card-body py-2 px-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <!-- Optional: Show Name/Role for clarity in group context (though this is 1-on-1) -->
                                                    <?php if($msg['sender_id'] != $user_id && $msg['sender_type'] == 'employer'): ?>
                                                        <small class="fw-bold me-2"><i class="fas fa-briefcase"></i> Employer</small>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mb-1"><?php echo htmlspecialchars($msg['content']); ?></p>
                                                <small class="<?php echo $mutedClass; ?>" style="font-size: 0.75rem;">
                                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted mt-5">No messages yet. Say hello!</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="d-flex justify-content-center align-items-center h-100">
                                <div class="text-center text-muted">
                                    <i class="far fa-comments fa-3x mb-3"></i>
                                    <h5>Welcome to Messages</h5>
                                    <p>Select a contact from the left to start chatting.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Input Area -->
                    <?php if ($receiver_id): ?>
                        <form method="POST" class="mt-auto">
                            <input type="hidden" name="receiver_id" value="<?php echo $receiver_id; ?>">
                            <div class="input-group">
                                <input type="text" name="content" class="form-control" placeholder="Type a message..." required autocomplete="off">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Send</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-scroll to bottom of chat
    var chatBox = document.getElementById("chat-box");
    if(chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
</script>

<?php include 'includes/footer.php'; ?>
