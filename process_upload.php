<?php
session_start();
require("db.php");
if (!$_SERVER['REQUEST_METHOD'] == 'POST') {
    die('invalid method');
}
if (!isset($_SESSION["user_id"])) {
    die('Unable to save note (Not logged in)');
}
$user_id = $_SESSION['user_id'];
$richHtml = $_POST['richNote'] ?? '';
$title = '';
if (!isset($_POST['noteTitle'])) {
    die('Missing information (note name)');
} else {
    $title = $_POST['noteTitle'];
}
define('dir', $uploadDir);
file_put_contents($uploadDir . 'note_' . time() . '.html', $richHtml);
function create_note($richHtml)
{
    $conn = create_connection();
    $stmt = $conn->prepare('insert into items (user_id, name) values (?, ?)');
    $stmt->bind_param('ss', $user_id, $title);
    file_put_contents(dir . 'note_' . time() . '.html', $richHtml);
}
// Handle image uploads
foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
        $name = basename($_FILES['images']['name'][$i]);
        $path = $uploadDir . uniqid() . '-' . $name;
        move_uploaded_file($tmp, $path);
    }
}

echo "Note saved!";
?>
