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
    error_log("Authentication failed: No user_id in session for process_note.php");
    echo json_encode($response);
    exit;
}

$conn = create_connection(); 
if (!$conn) {
    $response['msg'] = 'Database connection failed';
    error_log("Database connection failed in process_note.php: " . mysqli_connect_error());
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id']; 
$action = $_POST['action'] ?? ''; 

if ($action === 'delete') {
    $note_id_to_delete = $_POST['note_id'] ?? '';
    if (!empty($note_id_to_delete)) {
        $stmt_delete_labels = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?");
        $stmt_delete_labels->bind_param("i", $note_id_to_delete);
        $stmt_delete_labels->execute();
        $stmt_delete_labels->close();
        $stmt_delete_item = $conn->prepare("DELETE FROM items WHERE global_id = ? AND user_id = ?");
        $stmt_delete_item->bind_param("ii", $note_id_to_delete, $user_id);
        $success_item = $stmt_delete_item->execute();
        $stmt_delete_item->close();

        $response['success'] = $success_item;
        $response['msg'] = $success_item ? 'Note deleted successfully' : 'Failed to delete note';
    } else {
        $response['msg'] = 'Invalid note ID for deletion';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'toggle_pin') {
    $note_id_pin = $_POST['note_id'] ?? '';
    if (empty($note_id_pin)) {
        $response['msg'] = 'Invalid note ID for pin toggle';
        echo json_encode($response);
        exit;
    }
    $stmt_get_pin = $conn->prepare("SELECT pinned FROM items WHERE global_id = ? AND user_id = ?");
    $stmt_get_pin->bind_param("ii", $note_id_pin, $user_id); // global_id is INT
    $stmt_get_pin->execute();
    $result_pin = $stmt_get_pin->get_result();
    $item_pin = $result_pin->fetch_assoc();
    $stmt_get_pin->close();

    if (!$item_pin) {
        $response['msg'] = 'Note not found for pin toggle';
        echo json_encode($response);
        exit;
    }
    $new_pinned_status = !$item_pin['pinned'];
    $stmt_update_pin = $conn->prepare("UPDATE items SET pinned = ? WHERE global_id = ? AND user_id = ?");
    $stmt_update_pin->bind_param("iii", $new_pinned_status, $note_id_pin, $user_id);
    $success_pin = $stmt_update_pin->execute();
    $stmt_update_pin->close();
    $response['success'] = $success_pin;
    $response['msg'] = $success_pin ? 'Pin toggled successfully' : 'Failed to toggle pin';
    echo json_encode($response);
    exit;
}


// Save/Create Note Logic
$save_mode = $_POST['save'] ?? ''; // '0' for new, '1' for update
$note_global_id = $_POST['note_id'] ?? '';
$noteTitle = $_POST['noteTitle'] ?? 'Untitled Note';
$noteContent = $_POST['noteContent'] ?? '';
$noteColor = $_POST['noteColor'] ?? '#FFFFFF';
$noteLabels = isset($_POST['noteLabels']) && is_array($_POST['noteLabels']) ? $_POST['noteLabels'] : [];

$imagePath = null;
if (isset($_FILES['noteImage']) && $_FILES['noteImage']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $imageName = basename($_FILES['noteImage']['name']);
    $imageExtension = pathinfo($imageName, PATHINFO_EXTENSION);
    $uniqueImageName = uniqid('img_', true) . '.' . $imageExtension;
    $targetPath = $uploadDir . $uniqueImageName;

    if (move_uploaded_file($_FILES['noteImage']['tmp_name'], $targetPath)) {
        $imagePath = $targetPath;
    } else {
        error_log("Failed to move uploaded file to $targetPath: " . print_r(error_get_last(), true));
        $response['msg'] = 'Failed to upload image - check server permissions';
        echo json_encode($response);
        exit;
    }
}


if ($save_mode == '0') { // Create new note
    $stmt_create = $conn->prepare("INSERT INTO items (user_id, name, content, color, image, pinned) VALUES (?, ?, ?, ?, ?, 0)");
    if (!$stmt_create) {
        $response['msg'] = 'Prepare failed (create note): ' . $conn->error;
        error_log($response['msg']);
        echo json_encode($response);
        exit;
    }
    $stmt_create->bind_param("issss", $user_id, $noteTitle, $noteContent, $noteColor, $imagePath);
    $success_create = $stmt_create->execute();

    if ($success_create) {
        $new_note_global_id = $conn->insert_id; 
        $response['success'] = true;
        $response['msg'] = 'Note created successfully';
        $response['noteId'] = $new_note_global_id; 

        if (!empty($noteLabels)) {
            $stmt_label = $conn->prepare("INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)");
            if ($stmt_label) {
                foreach ($noteLabels as $label_id) {
                    if (!empty($label_id)) { 
                        $stmt_label->bind_param("ii", $new_note_global_id, $label_id);
                        if (!$stmt_label->execute()) {
                            error_log("Failed to associate label ID {$label_id} with note ID {$new_note_global_id}: " . $stmt_label->error);
                        }
                    }
                }
                $stmt_label->close();
            } else {
                error_log("Failed to prepare statement for note_labels: " . $conn->error);
            }
        }
    } else {
        $response['msg'] = 'Failed to create note: ' . $stmt_create->error;
        error_log($response['msg']);
    }
    $stmt_create->close();

} else if ($save_mode == '1' && !empty($note_global_id)) { // Update existing note
    $current_item_stmt = $conn->prepare("SELECT image FROM items WHERE global_id = ? AND user_id = ?");
    $current_item_stmt->bind_param("ii", $note_global_id, $user_id);
    $current_item_stmt->execute();
    $current_item_result = $current_item_stmt->get_result();
    $current_item = $current_item_result->fetch_assoc();
    $current_item_stmt->close();

    if (!$current_item) {
        $response['msg'] = 'Note not found or you do not own this note.';
        echo json_encode($response);
        exit;
    }

    $final_image_path = $current_item['image']; // Default to existing image

    if ($imagePath) { // New image uploaded
        // Optionally delete old image if it exists and is different
        if ($current_item['image'] && $current_item['image'] !== $imagePath && file_exists($current_item['image'])) {
            unlink($current_item['image']);
        }
        $final_image_path = $imagePath;
    } else if (isset($_POST['change']) && $_POST['change'] == '1' && !isset($_FILES['noteImage'])) {
        if (isset($_POST['clearImage']) && $_POST['clearImage'] == '1') {
            if ($current_item['image'] && file_exists($current_item['image'])) {
                unlink($current_item['image']);
            }
            $final_image_path = NULL;
            $response['imageCleared'] = true;
        }
    }


    $stmt_update = $conn->prepare("UPDATE items SET name = ?, content = ?, color = ?, image = ? WHERE global_id = ? AND user_id = ?");
    if (!$stmt_update) {
        $response['msg'] = 'Prepare failed (update note): ' . $conn->error;
        error_log($response['msg']);
        echo json_encode($response);
        exit;
    }
    $stmt_update->bind_param("ssssii", $noteTitle, $noteContent, $noteColor, $final_image_path, $note_global_id, $user_id);
    $success_update = $stmt_update->execute();

    if ($success_update) {
        $response['success'] = true;
        $response['msg'] = 'Note saved successfully';
        $response['imagePath'] = $final_image_path; // Send back the path of the image used

        // Update labels: delete existing and insert new ones
        $stmt_delete_labels = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?");
        $stmt_delete_labels->bind_param("i", $note_global_id);
        $stmt_delete_labels->execute();
        $stmt_delete_labels->close();

        if (!empty($noteLabels)) {
            $stmt_label = $conn->prepare("INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)");
            if ($stmt_label) {
                foreach ($noteLabels as $label_id) {
                    if (!empty($label_id)) {
                        $stmt_label->bind_param("ii", $note_global_id, $label_id);
                        if (!$stmt_label->execute()) {
                            error_log("Failed to associate label ID {$label_id} with note ID {$note_global_id} on update: " . $stmt_label->error);
                        }
                    }
                }
                $stmt_label->close();
            } else {
                error_log("Failed to prepare statement for note_labels on update: " . $conn->error);
            }
        }
    } else {
        $response['msg'] = 'Failed to save note: ' . $stmt_update->error;
        error_log($response['msg']);
    }
    $stmt_update->close();

} else {
    $response['msg'] = 'Invalid save operation or Note ID missing for update.';
}

echo json_encode($response);
$conn->close();
?>