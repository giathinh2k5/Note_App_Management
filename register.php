<?php
require 'db.php';
session_start();
if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    die();
}

$email = '';
$username = '';
$password = '';
?>
<!DOCTYPE html>
<html>

<head>
  <title>Register</title>
  <link rel="stylesheet" href="register.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
  <h2 class="ptitle">Register</h2>
  <form id="registerForm">
    <input type="email" name="email" placeholder="Email (example@gmail.com)">
    <input type="text" name="name" placeholder="Username">
    <input type="password" name="password" placeholder="Password">
    <input type="password" name="confirm_password" placeholder="Confirm Password">
    <button type="submit">Register</button>
    <div class="helper-links">
      <a href="login.php">Back to Login</a>
    </div>
    <div id="feedback" class="mt-3" style="display: none;"></div>
</form>
  <script src="register.js?v=<?= time() ?>"></script>
</body>

</html>