<?php
session_start();
require('db.php');
$response = ['result' => '', 'msg' => '', 'name' => '', 'content' => '', 'noteId' => '', 'image' => ''];
if (!$_SERVER['REQUEST_METHOD'] == 'POST') {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to lock - invalid method';
    echo json_encode($response);
    exit();
}
if (!isset($_SESSION['user_id'])) {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to lock - not logged in';
    echo json_encode($response);
    exit();
}

if (empty($_REQUEST['lockNoteId'])) {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to lock note - Invalid note id';
    echo json_encode($response);
    die();
} else {
    $note_id = $_REQUEST['lockNoteId'];
}
$hashedPassword = password_hash($_REQUEST['lockPassword'], PASSWORD_DEFAULT);
$conn = create_connection();
$stmt = $conn->prepare('select * from items where global_id = ?');
$stmt->bind_param('i', $note_id, );
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    $response['result'] = 'failed';
    $response['msg'] = 'Failed to lock note - couldnt find note';
    echo json_encode($response);
    exit();
}
$item = $result->fetch_assoc();
$stmt = $conn->prepare('update items set password = ? where global_id = ?');
$stmt->bind_param('si', $hashedPassword, $note_id);
if ($stmt->execute()) {
    $response['msg'] = 'Note locked!';
    echo json_encode($response);
    die();
} else {
    $response['msg'] = 'Failed to lock note - database error';
    $response['result'] = 'failed';
    echo json_encode($response);
    die();
}
?>