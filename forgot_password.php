<?php
require 'db.php';

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/Exception.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$conn = create_connection();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("UPDATE users SET token_reset = ? WHERE email = ?");
        $stmt->bind_param("ss", $token, $email);
        $stmt->execute();

        $reset_link = "http://localhost/Note_app/password_reset.php?token=$token";

        // Cấu hình PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Cấu hình server SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Thiết lập thông tin người gửi và người nhận
            $mail->setFrom('tranvugiathinh@gmail.com', 'Note App');
            $mail->addAddress($email);

            // Nội dung email
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Link - Note App';
            $mail->Body = "
                <h2>Password Reset Request</h2>
                <p>We received a request to reset your password for Note App.</p>
                <p>Click the link below to reset your password:</p>
                <p><a href='$reset_link'>$reset_link</a></p>
                <p>If you did not request this, please ignore this email.</p>
                <p>Thank you,<br>Note App Team</p>
            ";
            $mail->AltBody = "We received a request to reset your password for Note App. Please visit the following link to reset your password: $reset_link. If you did not request this, please ignore this email.";

            // Gửi email
            $mail->send();
            $message = '<div class="alert alert-success" role="alert">A reset link has been sent to your email: <strong>' . htmlspecialchars($email) . '</strong>. Please check your inbox (and spam/junk folder).</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger" role="alert">Failed to send reset link. Error: ' . $mail->ErrorInfo . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning" role="alert">If that email exists, a reset link has been sent.</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="forgot_password.css">
</head>
<body>
    <h2 class="ptitle">Forgot Password</h2>
    <form method="POST">
        <input type="email" name="email" placeholder="Account email" required><br>
        <button type="submit">Send Reset Link</button>
        <div class="helper-links">
            <a href="login.php">Back to Login</a>
        </div>
    </form>
    <?php if (!empty($message)) echo $message; ?>
</body>
</html>