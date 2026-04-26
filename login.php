<?php
require 'db.php';
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    die();
}

$email = '';
$pass = '';
$error = '';
$email_error_focus = false;
$pass_error_focus = false;
$conn = create_connection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST["email"];
    $pass = $_POST["pass"];
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (empty($email)) {
        $error = "Please enter your email";
        $email_error_focus = true;
    } else if (empty($pass)) {
        $error = "Please enter your password";
        $pass_error_focus = true;
    } else if (strlen($pass) < 6) {
        $error = "Your password must be at least 6 characters";
        $pass_error_focus = true;
    } else if ($user && !password_verify($pass, $user['password'])) {
        $error = "Invalid email or password.";
        $pass_error_focus = true;
    } else if (!$user) {
        $error = "Invalid email or password.";
        $email_error_focus = true;
    } else {
        $_SESSION['user_id'] = $user['id'];
        header("Location: home.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
</head>

<body data-email-error-focus="<?= $email_error_focus ? 'true' : 'false' ?>"
    data-pass-error-focus="<?= $pass_error_focus ? 'true' : 'false' ?>">

    <h2 class="ptitle">Login</h2>
    <form method="POST" action="">
        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Email (example@gmail.com)"
            id="emailField"><br>
        <input type="password" name="pass" value="<?= htmlspecialchars($pass) ?>" placeholder="Password"
            id="passwordField"><br>
        <button type="submit" name="login">Login</button>
        <div class="helper-links">
            <a href="register.php">Register</a>
            <span class="separator">|</span>
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
        <div>
            <?php
            if (!empty($error)) {
                echo ("<div class='alert alert-danger'>$error</div>");
            }
            ?>
        </div>
    </form>

    <script src="login.js"></script>
</body>

</html>