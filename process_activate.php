<?php
require 'db.php';

$token = $_GET['token'] ?? '';

$conn = create_connection();
$sql = "select * from users where token_auth = ?";
$stm = $conn->prepare($sql);
$stm->bind_param("s", $token);
$stm->execute();
$result = $stm->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Kích hoạt tài khoản</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>

<body>
    <div class="container">
        <?php
        if (!($result->num_rows !== 1)) {
            $authenticated_status = 1;
            $stm = $conn->prepare("UPDATE users SET authenticated = ? WHERE token_auth = ?");
            $stm->bind_param("is", $authenticated_status, $token);
            $stm->execute();
            ?>
            <div class="row">
                <div class="col-md-6 mt-5 mx-auto p-3 border rounded">
                    <h4>Account Activation</h4>
                    <p class="text-success">Congratulations! Your account has been activated. You can close this tab now.
                    </p>
                </div>
            </div>
        <?php } else { ?>
            <div class="row">
                <div class="col-md-6 mt-5 mx-auto p-3 border rounded">
                    <h4>Account Activation</h4>
                    <p class="text-danger">This is url or token is invalid.</p>
                    <p>Click <a href="login.php">here</a> to login instead.</p>
                    <a class="btn btn-success px-5" href="login.php">Login</a>
                </div>
            </div>
        <?php } ?>
    </div>
</body>

</html>