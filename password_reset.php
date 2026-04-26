<?php
require 'db.php';
$conn = create_connection();

$success_message = ''; // Biến để lưu thông báo thành công
$message = ''; // Biến để lưu thông báo lỗi

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE token_reset = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        die("Invalid or expired reset link.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        // Kiểm tra độ dài mật khẩu
        if (strlen($newPassword) < 6) {
            $message = "Password must be at least 6 characters long!";
        }
        // Kiểm tra khớp mật khẩu
        elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match!";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, token_reset = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $user['id']);
            $stmt->execute();

            // Thêm thông báo thành công
            $success_message = "Password changed successfully!";
        }
    }
} else {
    die("No reset token provided.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="forgot_password.css">
</head>
<body>
    <h2 class="ptitle">Reset Your Password</h2>
    <?php if (empty($success_message)): ?>
        <!-- Hiển thị form nếu chưa đổi mật khẩu thành công -->
        <form method="POST">
            <input type="password" name="password" placeholder="New password" required><br>
            <input type="password" name="confirm_password" placeholder="Confirm password" required><br>
            <button type="submit">Change Password</button>
            <div class="helper-links">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Hiển thị thông báo thành công -->
        <div class="alert alert-success">
            <?php echo $success_message; ?>
            <p>You can now <a href="login.php" class="alert-link">log in</a> with your new password.</p>
        </div>
    <?php endif; ?>
</body>
</html>