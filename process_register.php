<?php
require("db.php");
session_start();
$conn = create_connection();

require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

$response = ['result' => '', 'msg' => '', 'errorField' => ''];
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $name = trim($_POST["name"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Kiểm tra lỗi đầu vào
    if (empty($email)) {
        $response['result'] = 'failed';
        $response['msg'] = 'Please enter your email.';
        $response['errorField'] = 'email';
        echo json_encode($response);
        exit;
    } else if (empty($name)) {
        $response['result'] = 'failed';
        $response['msg'] = 'Please enter your username.';
        $response['errorField'] = 'name';
        echo json_encode($response);
        exit;
    } else if (empty($password)) {
        $response['result'] = 'failed';
        $response['msg'] = 'Please enter your password.';
        $response['errorField'] = 'password';
        echo json_encode($response);
        exit;
    } else if (strlen($password) < 6) {
        $response['result'] = 'failed';
        $response['msg'] = 'Password must be at least 6 characters.';
        $response['errorField'] = 'password';
        echo json_encode($response);
        exit;
    } else if (empty($confirm_password)) {
        $response['result'] = 'failed';
        $response['msg'] = 'Please confirm your password.';
        $response['errorField'] = 'confirm_password';
        echo json_encode($response);
        exit;
    } else if ($password !== $confirm_password) {
        $response['result'] = 'failed';
        $response['msg'] = "Passwords do not match.";
        $response['errorField'] = "confirm_password";
        echo json_encode($response);
        exit;
    }

    // Kiểm tra trùng username hoặc email
    $stm = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stm->bind_param("ss", $name, $email);
    $stm->execute();
    $result = $stm->get_result();
    $exists = $result->fetch_array()[0] === 1;
    if ($exists) {
        $response['result'] = 'failed';
        $response['msg'] = "This username or email already exists";
        $response['errorField'] = 'email';
        echo json_encode($response);
        exit;
    }

    // Tạo tài khoản
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, token_auth) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $token);
    if (!$stmt->execute()) {
        $response['result'] = 'failed';
        $response['msg'] = "Failed to register account";
        echo json_encode($response);
        exit;
    }

    $generatedId = $conn->insert_id;

    // Gửi email xác thực
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom($_ENV['SMTP_USER'], 'Note App');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Authenticate Account';
        $link = "http://localhost/Note_app/process_activate.php?token=$token";
        $mail->Body = "Click the link to authenticate your account: <a href='$link'>$link</a>";
        $mail->send();
    } catch (Exception $e) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $generatedId);
        $stmt->execute();

        $response['result'] = 'failed';
        $response['msg'] = "Failed to send authentication email.";
        echo json_encode($response);
        exit;
    }

    $_SESSION['user_id'] = $generatedId;
    $conn->close();

    $response['result'] = 'success';
    $response['msg'] = 'Registration successful. Please check your email to authenticate.';
    echo json_encode($response);
} else {
    $response['result'] = 'failed';
    $response['msg'] = 'Invalid request method.';
    echo json_encode($response);
}
?>
