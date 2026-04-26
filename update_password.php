<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $newPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Update password where token matches
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
    $stmt->bind_param("ss", $token, $newPassword);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        echo "Password updated. <a href='login.php'>Log in</a>";
    } else {
        echo "Invalid token or error updating password.";
    }
}
?>