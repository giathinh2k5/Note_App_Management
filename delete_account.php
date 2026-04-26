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
    error_log("No session user_id found, redirecting to login.php. Full session: " . print_r($_SESSION, true));
    header("Location: login.php");
    die();
}

$user_id = $_SESSION['user_id'];
error_log("Current user_id from session: " . $user_id);

$conn = create_connection();

// Xử lý yêu cầu từ modal (kiểm tra mật khẩu hiện tại và gửi email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'])) {
    $current_password = trim($_POST['current_password']);
    error_log("Received POST data: " . print_r($_POST, true));
    error_log("Received current_password (trimmed): " . $current_password);

    // Kiểm tra mật khẩu hiện tại
    $stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("User not found for user_id: " . $user_id);
        echo '<div class="alert-danger">Cannot find user in database!</div>';
        $conn->close();
        exit;
    }

    error_log("User data from DB: " . print_r($user, true));

    // Kiểm tra xem mật khẩu trong DB có phải dạng băm không
    if (strpos($user['password'], '$2y$') !== 0) {
        error_log("Password in DB is not hashed for user_id: " . $user_id);
        echo '<div class="alert-danger">Password in database invalid!</div>';
        $conn->close();
        exit;
    }

    $is_valid = password_verify($current_password, $user['password']);
    error_log("Password verification result: " . ($is_valid ? "true" : "false") . " with input: " . $current_password);

    if (!$is_valid) {
        error_log("Password verification failed for user_id: " . $user_id . " with input: " . $current_password);
        echo '<div class="alert-danger">Password is incorrect!</div>';
        $conn->close();
        exit;
    }

    // Tiếp tục với gửi email và tạo token
    $token = bin2hex(random_bytes(32));
    $expiration_time = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $conn->prepare("UPDATE users SET token_reset = ?, expiration_time = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expiration_time, $user_id);
    if ($stmt->execute()) {
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];

        $mail->setFrom('tranvugiathinh@gmail.com', 'Note App');
        $mail->addAddress($user['email']);
        $mail->Subject = 'Delete Account';
        $delete_link = "http://localhost/Note_app/delete_account.php?token=$token&user_id=$user_id";
        $mail->Body = "Please click the following link to confirm deletion of your account:\n$delete_link\nThis link will expire in 1 hour.";

        if ($mail->send()) {
            echo '<div class="alert-success">A confirmation email has been sent to ' . htmlspecialchars($user['email']) . '. Please check your inbox (including spam folder) and click the link to complete account deletion.</div>';
        } else {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            echo '<div class="alert-danger">Error when giving email: ' . $mail->ErrorInfo . '</div>';
        }
    } else {
        error_log("Failed to execute UPDATE query: " . $conn->error);
        echo '<div class="alert-danger">Error generating authentication token: ' . $conn->error . '</div>';
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Xử lý xác nhận qua email
if (isset($_GET['token']) && isset($_GET['user_id'])) {
    $token = $_GET['token'];
    $user_id = $_GET['user_id'];

    $stmt = $conn->prepare("SELECT token_reset, expiration_time FROM users WHERE id = ? AND token_reset = ?");
    $stmt->bind_param("is", $user_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $expiration_time = strtotime($user['expiration_time']);
        $current_time = time();

        if ($current_time > $expiration_time) {
            $conn->close();
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Link</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Confirmation link has expired!</h1>
        <p>Please try again from the beginning.</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
            exit;
        }
        $stmt_shared_items = $conn->prepare("DELETE sn FROM shared_notes sn JOIN items i ON sn.item_global_id = i.global_id WHERE i.user_id = ?");
        $stmt_shared_items->bind_param("i", $user_id);
        if (!$stmt_shared_items->execute()) {
            $conn->close();
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Link</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Failed to delete relevant records</h1>
        <p>Please try again from the beginning.</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
            exit;
        }
        $stmt_shared_items->close();
        $stmt_shared_receiver = $conn->prepare("DELETE FROM shared_notes WHERE receiver_id = ?");
        $stmt_shared_receiver->bind_param("i", $user_id);
        if (!$stmt_shared_receiver->execute()) {
            $conn->close();
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Link</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Failed to delete relevant records</h1>
        <p>Please try again from the beginning.</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
            exit;
        }

        $stmt_labels = $conn->prepare("DELETE FROM labels WHERE user_id = ?");
        $stmt_labels->bind_param("i", $user_id);
        if (!$stmt_labels->execute()) {
            $conn->close();
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Link</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Failed to delete relevant records</h1>
        <p>Please try again from the beginning.</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
            exit;
        }
        $stmt_labels->close();

        $stmt = $conn->prepare("DELETE FROM items WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            $conn->close();
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expired Link</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Failed to delete relevant records</h1>
        <p>Please try again from the beginning.</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            session_destroy();
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account deleted successfully</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #155724; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Your account has been deleted!</h1>
        <p>Thank you for using our service..</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
    window.onload = function () {
    let seconds = 3;
    const countdown = document.getElementById("countdown");

    const timer = setInterval(() => {
        if (seconds <= 1) {
            countdown.textContent = 0;
            clearInterval(timer);
            window.location.href = "/Note_app/login.php";
        } else {
            seconds--;
            countdown.textContent = seconds;
        }
    }, 1000);
};
</script>
</body>
</html>';
            exit;
        } else {
            error_log("Failed to delete user: " . $conn->error);
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error deleting account</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Error when deleting account!</h1>
        <p>' . htmlspecialchars($conn->error) . '</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
            exit;
        }
    } else {
        echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ivalid connection</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f0f0f0; }
        .message { text-align: center; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .message h1 { color: #721c24; }
    </style>
</head>
<body>
    <div class="message">
        <h1>Invalid confirmation link!</h1>
        <p>Please check the link or try again.</p>
        <p>Auto redirect in <span id="countdown">5</span> second...</p>
    </div>
    <script>
        let seconds = 5;
        const countdown = document.getElementById("countdown");
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = "login.php";
            }
        }, 1000);
    </script>
</body>
</html>';
        exit;
    }
}

$conn->close();
