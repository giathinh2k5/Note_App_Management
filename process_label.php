<?php
session_start();
require('db.php');

header('Content-Type: application/json');
$response = ['success' => false, 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['msg'] = 'Invalid method';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $response['msg'] = 'Authentication failed';
    echo json_encode($response);
    exit;
}

$conn = create_connection();
if (!$conn) {
    $response['msg'] = 'Database connection failed';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'fetch') {
    $stmt = $conn->prepare("SELECT id, name FROM labels WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $labels = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row;
    }
    $response['success'] = true;
    $response['labels'] = $labels;
    $stmt->close();
} elseif ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $response['msg'] = 'Label name is required';
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $name);
    $success = $stmt->execute();
    $response['success'] = $success;
    $response['msg'] = $success ? 'Label created successfully' : 'Failed to create label';
    if ($success) {
        $response['label_id'] = $conn->insert_id;
    }
    $stmt->close();
} elseif ($action === 'rename') {
    $label_id = $_POST['label_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    if (empty($label_id) || empty($name)) {
        $response['msg'] = 'Label ID or name is missing';
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("UPDATE labels SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $name, $label_id, $user_id);
    $success = $stmt->execute();
    $response['success'] = $success;
    $response['msg'] = $success ? 'Label renamed successfully' : 'Failed to rename label';
    $stmt->close();
} elseif ($action === 'delete') {
    $label_id = $_POST['label_id'] ?? '';
    if (empty($label_id)) {
        $response['msg'] = 'Label ID is missing';
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $label_id, $user_id);
    $success = $stmt->execute();
    $response['success'] = $success;
    $response['msg'] = $success ? 'Label deleted successfully' : 'Failed to delete label';
    $stmt->close();
} else {
    $response['msg'] = 'Invalid action';
}

echo json_encode($response);
$conn->close();
?>