<?php
session_start();
require('db.php');
$response = ['stat' => '', 'result' => '', 'msg' => '', 'note' => ''];
if (!$_SERVER['REQUEST_METHOD'] == 'POST') {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to unlock - invalid method';
    echo json_encode($response);
    exit();
}
if (!isset($_SESSION['user_id'])) {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to unlock - not logged in';
    echo json_encode($response);
    exit();
}

if (empty($_REQUEST['unlockNoteId'])) {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to unlock note - Invalid note id';
    echo json_encode($response);
    die();
} else {
    $note_id = $_REQUEST['unlockNoteId'];
}
$password = $_POST['unlockPassword'];
$remove = $_POST['remove'];
$conn = create_connection();
$stmt = $conn->prepare('select * from items where global_id = ?');
$stmt->bind_param('i', $note_id, );
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to unlock note - couldnt find note';
    echo json_encode($response);
    exit();
}
$item = $result->fetch_assoc();
if (password_verify($password, $item['password'])) {
    if ($remove) {
        $update_stmt = $conn->prepare("UPDATE items SET password = NULL WHERE global_id = ?");
        $update_stmt->bind_param("s", $note_id);
        if ($update_stmt->execute()) {
            $response['msg'] = 'Note unlocked! - removed lock';
            $response['note'] = $item;
            echo json_encode($response);
            exit();
        } else {
            $response['result'] = 'failed';
            $response['msg'] = 'Failed to unlock note - couldnt find note';
            echo json_encode($response);
            exit();
        }
    } else {
        $response['msg'] = 'Note unlocked! Viewing note';
        $response['note'] = $item;
        echo json_encode($response);
    }
} else {
    $response['msg'] = "Failed to unlock note - password doesn't match";
    $response['result'] = 'failed';
    echo json_encode($response);
    die();
}
?>