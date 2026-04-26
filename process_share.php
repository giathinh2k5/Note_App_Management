<?php
session_start();
require('db.php');

$response = ['success' => false, 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['msg'] = 'Invalid method';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $response['msg'] = 'Authentication failed - not logged in';
    error_log("Authentication failed: No user_id in session");
    echo json_encode($response);
    exit;
}

$conn = create_connection();
if (!$conn) {
    $response['msg'] = 'Database connection failed';
    error_log("Database connection failed: " . mysqli_connect_error());
    echo json_encode($response);
    exit;
}
$action = $_POST['action'];

$user_id = $_SESSION['user_id'];
$note_id = $_POST['note_id'] ?? '';
if ($action == 'share') {
    $recipient = $_POST['email'] ?? $_POST['username'] ?? '';
    if (!empty($_POST['email'])) {
        $NameEmail = false;
        $recipient = $_POST['email'];
    } else if (!empty($_POST['username'])) {
        $NameEmail = true;
        $recipient = $_POST['username'];
    } else {
        $response['msg'] = 'failed to share note - invalid recipient';
        $response['result'] = 'failed';
        echo json_encode($response);
        exit;
    }

    if (empty($note_id)) {
        $response['msg'] = 'failed to share note - invalid note id';
        $response['result'] = 'failed';
        echo json_encode($response);
        exit;
    }
    if ($NameEmail) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $recipient);
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $recipient);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $receiver = $result->fetch_assoc();
    if ($result->num_rows != 1) {
        $response["msg"] = "failed to share - can't find recipient in database";
        $response["result"] = "failed";
        echo json_encode($response);
        exit;
    }
    if ($receiver['id'] == $user_id) {
        $response['msg'] = 'You cannot share a note with yourself.';
        $response['result'] = 'failed';
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("SELECT * FROM shared_notes WHERE receiver_id = ? AND item_global_id = ?");
    $stmt->bind_param("ss", $recipient, $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $response["msg"] = "failed to share - note already shared!";
        $response["result"] = "failed";
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO shared_notes (sender_id, receiver_id, item_global_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user_id, $receiver['id'], $note_id);
    if ($stmt->execute()) {
        $response['msg'] = 'Shared note successfully';
        echo json_encode($response);
        exit;
    } else {
        $response['result'] = 'failed';
        $response['msg'] = 'failed to share note - cannot insert in database';
        echo json_encode($response);
        exit;
    }

} else if ($action == 'get_shared_with') {
    if (empty($note_id)) {
        $response['msg'] = 'Note ID is required.';
        echo json_encode($response);
        exit;
    }
    $stmt_check_owner = $conn->prepare("SELECT user_id FROM items WHERE global_id = ? AND user_id = ?");
    $stmt_check_owner->bind_param("si", $note_id, $user_id);
    $stmt_check_owner->execute();
    $result_owner = $stmt_check_owner->get_result();
    if ($result_owner->num_rows == 0) {
        $response['msg'] = 'You are not the owner of this note or note not found.';
        $stmt_check_owner->close();
        echo json_encode($response);
        exit;
    }
    $stmt_check_owner->close();

    $shared_with_users = [];
    $stmt_get_list = $conn->prepare("SELECT u.id as receiver_user_id, u.username, u.email, sn.id as shared_note_record_id 
                                     FROM shared_notes sn 
                                     JOIN users u ON sn.receiver_id = u.id 
                                     WHERE sn.item_global_id = ?");
    $stmt_get_list->bind_param("s", $note_id);

    if ($stmt_get_list->execute()) {
        $result_list = $stmt_get_list->get_result();
        while ($row_share = $result_list->fetch_assoc()) {
            $identifier = !empty($row_share['username']) ? $row_share['username'] : $row_share['email'];
            $shared_with_users[] = [
                'identifier' => $identifier,
                'receiver_user_id' => $row_share['receiver_user_id'],
                'shared_note_record_id' => $row_share['shared_note_record_id']
            ];
        }
        $response['success'] = true;
        $response['shared_with'] = $shared_with_users;
    } else {
        $response['msg'] = 'Failed to retrieve shared list: ' . $stmt_get_list->error;
        error_log("Error fetching shared list for note $note_id: " . $stmt_get_list->error);
    }
    $stmt_get_list->close();
} else if ($action == 'unshare') {
    $shared_note_record_id_to_delete = $_POST['shared_note_record_id'] ?? '';

    if (empty($note_id) || empty($shared_note_record_id_to_delete)) {
        $response['msg'] = 'Required information missing to unshare (note_id or shared_note_record_id).';
        echo json_encode($response);
        exit;
    }

    $stmt_verify_unshare = $conn->prepare(
        "SELECT i.user_id as owner_id 
         FROM shared_notes sn
         JOIN items i ON sn.item_global_id = i.global_id
         WHERE sn.id = ? AND sn.item_global_id = ?"
    );
    $stmt_verify_unshare->bind_param("is", $shared_note_record_id_to_delete, $note_id);
    $stmt_verify_unshare->execute();
    $result_verify = $stmt_verify_unshare->get_result();

    if ($share_info = $result_verify->fetch_assoc()) {
        if ($share_info['owner_id'] != $user_id) {
            $response['msg'] = 'You are not authorized to modify sharing for this note (not owner).';
            $stmt_verify_unshare->close();
            echo json_encode($response);
            exit;
        }
    } else {
        $response['msg'] = 'Share record not found or does not match the note.';
        $stmt_verify_unshare->close();
        echo json_encode($response);
        exit;
    }
    $stmt_verify_unshare->close();

    $stmt_delete_share = $conn->prepare("DELETE FROM shared_notes WHERE id = ?");
    $stmt_delete_share->bind_param("i", $shared_note_record_id_to_delete);

    if ($stmt_delete_share->execute()) {
        if ($stmt_delete_share->affected_rows > 0) {
            $response['success'] = true;
            $response['msg'] = 'Successfully stopped sharing.';
        } else {
            $response['msg'] = 'Could not find the share record to delete (it may have already been removed).';
        }
    } else {
        $response['msg'] = 'Failed to stop sharing: ' . $stmt_delete_share->error;
        error_log("Error unsharing (shared_notes_id: $shared_note_record_id_to_delete): " . $stmt_delete_share->error);
    }
    $stmt_delete_share->close();

} else {
    $response['msg'] = 'Invalid action specified.';
}
echo json_encode($response);
$conn->close();
?>