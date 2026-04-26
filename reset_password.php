<?php
session_start();
require 'db.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/Exception.php';
require 'PHPMailer/SMTP.php';

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}
loadEnv(__DIR__ . '/.env');

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    die();
}

$user_id = $_SESSION['user_id'];
$conn = create_connection();

// Xử lý yêu cầu từ modal (kiểm tra password hiện tại và gửi email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'])) {
    $current_password = $_POST['current_password'];

    // Kiểm tra password hiện tại
    $stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current_password, $user['password'])) {
        // Hiển thị thông báo lỗi trực tiếp
        echo '<div id="feedback" class="alert-danger">Current password is incorrect!</div>';
        exit;
    }

    // Tạo token xác thực
    $token = bin2hex(random_bytes(32));
    $expiration_time = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Lưu token vào cơ sở dữ liệu
    $stmt = $conn->prepare("UPDATE users SET token_reset = ?, expiration_time = ? WHERE id = ?");
    $stmt->bind_param("sss", $token, $expiration_time, $user_id);
    if ($stmt->execute()) {
        // Gửi email xác nhận bằng PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];

        $mail->setFrom($_ENV['SMTP_USER'], 'Note App');
        $mail->addAddress($user['email']);
        $mail->Subject = 'Reset Password';
        $reset_link = "http://localhost/Note_app/reset_password.php?token=$token&user_id=$user_id";
        $mail->Body = "Please click the following link to confirm your password change:\n$reset_link\nThis link will expire in 1 hour.";

        if ($mail->send()) {
            // Hiển thị thông báo thành công trực tiếp
            echo '<div id="feedback" class="alert-success">A confirmation email has been sent to ' . htmlspecialchars($user['email']) . '. Please check your mailbox.</div>';
        } else {
            echo '<div id="feedback" class="alert-danger">Error sending email: ' . $mail->ErrorInfo . '</div>';
        }
    } else {
        echo '<div id="feedback" class="alert-danger">Error generating authentication token: ' . $conn->error . '</div>';
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Xử lý xác nhận qua email
if (isset($_GET['token']) && isset($_GET['user_id'])) {
    $token = $_GET['token'];
    $user_id = $_GET['user_id'];

    // Kiểm tra token
    $stmt = $conn->prepare("SELECT token_reset, expiration_time, email FROM users WHERE id = ? AND token_reset = ?");
    $stmt->bind_param("ss", $user_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $expiration_time = strtotime($user['expiration_time']);
        $current_time = time();

        if ($current_time > $expiration_time) {
            die("The confirmation link has expired. Please try again..");
        }

        // Lấy password mới từ POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if ($new_password !== $confirm_password) {
                die("Confirmation password does not match!");
            }

            if (strlen($new_password) < 6) {
                die("Password must be at least 6 characters!");
            }

            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Cập nhật password mới
            $stmt = $conn->prepare("UPDATE users SET password = ?, token_reset = NULL, expiration_time = NULL WHERE id = ?");
            $stmt->bind_param("ss", $new_hashed_password, $user_id);
            if ($stmt->execute()) {
                session_destroy();
                header("Location: login.php?message=Password has been changed successfully. Please log in again..");
                exit;
            } else {
                echo "Error while updating password: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Hiển thị form nhập password mới với giao diện theo tông màu
            echo '<!DOCTYPE html>
                  <html lang="vi">
                  <head>
                      <meta charset="UTF-8">
                      <meta name="viewport" content="width=device-width, initial-scale=1.0">
                      <title>Confirm New Password</title>
                      <link rel="stylesheet" href="reset_password.css">
                  </head>
                  <body>
                      <div class="reset-password-container">
                          <div class="reset-password-card">
                              <h2>Confirm New Password</h2>
                              <form method="post" action="">
                                  <div class="mb-3">
                                      <label for="new_password" class="form-label"></label>
                                      <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Enter new password">
                                  </div>
                                  <div class="mb-3">
                                      <label for="confirm_password" class="form-label"></label>
                                      <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Confirm password">
                                  </div>
                                  <button type="submit">Confirm</button>
                                  <p class="text-muted">Password must be at least 6 characters.</p>
                              </form>
                          </div>
                      </div>
                      
                  </body>
                  </html>';
        }
    } else {
        die("Invalid confirmation link!");
    }
}

$conn->close();
?>