<?php
session_start();
require('db.php');
header('Content-Type: application/json');
$response = [
    'items' => [],
    'shareItems' => [],
    'success' => false,
    'msg' => ''
];

if (!isset($_SESSION['user_id'])) {
    error_log("Authentication failed: No user_id in session for get_note.php");
    $response['msg'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = create_connection();

if (!$conn) {
    error_log("Database connection failed in get_note.php: " . mysqli_connect_error());
    $response['msg'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

// Fetch user's own notes
$label_id_filter = isset($_GET['label_id']) && !empty($_GET['label_id']) ? (int) $_GET['label_id'] : null;

$types_items = "";
$params_to_bind = [];

if ($label_id_filter !== null) {
    $sql_items = "SELECT i.*, GROUP_CONCAT(DISTINCT l.id) as label_ids, GROUP_CONCAT(DISTINCT l.name SEPARATOR '|||') as label_names
                  FROM items i
                  INNER JOIN note_labels nl_filter ON i.global_id = nl_filter.note_id AND nl_filter.label_id = ?
                  LEFT JOIN note_labels nl_all ON i.global_id = nl_all.note_id
                  LEFT JOIN labels l ON nl_all.label_id = l.id
                  WHERE i.user_id = ?
                  GROUP BY i.global_id
                  ORDER BY i.pinned DESC, i.created_at DESC";
    $types_items = "ii"; // For label_id_filter (int) and user_id (int)
    $params_to_bind[] = $label_id_filter;
    $params_to_bind[] = $user_id;
} else {
    // Fetches all notes for the user and all their associated labels.
    $sql_items = "SELECT i.*, GROUP_CONCAT(DISTINCT l.id) as label_ids, GROUP_CONCAT(DISTINCT l.name SEPARATOR '|||') as label_names
                  FROM items i
                  LEFT JOIN note_labels nl ON i.global_id = nl.note_id
                  LEFT JOIN labels l ON nl.label_id = l.id
                  WHERE i.user_id = ?
                  GROUP BY i.global_id
                  ORDER BY i.pinned DESC, i.created_at DESC";
    $types_items = "i"; // For user_id (int)
    $params_to_bind[] = $user_id;
}

$stmt_items = $conn->prepare($sql_items);

if (!$stmt_items) {
    error_log("Prepare failed (items) in get_note.php: " . $conn->error . " SQL: " . $sql_items);
    $response['msg'] = 'Error preparing to fetch items.';
    echo json_encode($response);
    exit;
}

if (!empty($types_items)) {
    $stmt_items->bind_param($types_items, ...$params_to_bind);
}

if (!$stmt_items->execute()) {
    error_log("Execute failed (items) in get_note.php: " . $stmt_items->error);
    $response['msg'] = 'Error executing items fetch.';
    echo json_encode($response);
    exit;
}

$result_items = $stmt_items->get_result();
$items_data = [];
while ($row_item = $result_items->fetch_assoc()) {
    $note = [
        'global_id' => $row_item['global_id'],
        'user_id' => $row_item['user_id'],
        'item_id' => $row_item['item_id'],
        'name' => $row_item['name'],
        'color' => $row_item['color'],
        'pinned' => $row_item['pinned'],
        'content' => '',
        'image' => '',
        'locked' => true,
        'label_ids' => $row_item['label_ids'] ? explode(',', $row_item['label_ids']) : [],
        'label_names' => $row_item['label_names'] ? explode('|||', $row_item['label_names']) : []
    ];
    if (empty($row_item['password'])) {
        $note['content'] = $row_item['content'];
        $note['image'] = $row_item['image'];
        $note['locked'] = false;
    }
    $items_data[] = $note;
}
$response['items'] = $items_data;
$stmt_items->close();

// Fetch notes shared with the user
$sql_shared_items = "SELECT i.*, GROUP_CONCAT(DISTINCT l.id) as label_ids, GROUP_CONCAT(DISTINCT l.name SEPARATOR '|||') as label_names,
                     i.user_id as owner_id
                     FROM shared_notes sn
                     JOIN items i ON sn.item_global_id = i.global_id
                     LEFT JOIN note_labels nl ON i.global_id = nl.note_id
                     LEFT JOIN labels l ON nl.label_id = l.id
                     WHERE sn.receiver_id = ? AND i.user_id != ?
                     GROUP BY i.global_id
                     ORDER BY sn.shared_at DESC";

$stmt_shared = $conn->prepare($sql_shared_items);
if (!$stmt_shared) {
    error_log("Prepare failed (shared_items) in get_note.php: " . $conn->error);
    $response['msg'] = 'Error preparing to fetch shared items.';
    echo json_encode($response);
    exit;
}
$stmt_shared->bind_param("ii", $user_id, $user_id);

if (!$stmt_shared->execute()) {
    error_log("Execute failed (shared_items) in get_note.php: " . $stmt_shared->error);
    $response['msg'] = 'Error fetching shared items.';
    echo json_encode($response);
    exit;
}

$result_shared = $stmt_shared->get_result();
$shared_items_data = [];
while ($row_shared = $result_shared->fetch_assoc()) {
    $note = [
        'global_id' => $row_shared['global_id'],
        'user_id' => $row_shared['user_id'], // Original owner's ID
        'item_id' => $row_shared['item_id'],
        'name' => $row_shared['name'],
        'color' => $row_shared['color'],
        'pinned' => $row_shared['pinned'],
        'content' => '',
        'image' => '',
        'locked' => true,
        'shared_by_owner_id' => $row_shared['owner_id'],
        'label_ids' => $row_shared['label_ids'] ? explode(',', $row_shared['label_ids']) : [],
        'label_names' => $row_shared['label_names'] ? explode('|||', $row_shared['label_names']) : []
    ];
    if (empty($row_shared['password'])) {
        $note['content'] = $row_shared['content'];
        $note['image'] = $row_shared['image'];
        $note['locked'] = false;
    }
    $shared_items_data[] = $note;
}
$response['shareItems'] = $shared_items_data;
$stmt_shared->close();

$response['success'] = true;
echo json_encode($response);
$conn->close();
?>