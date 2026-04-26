<?php
session_start();
require 'db.php';

if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
  die();
}

$user_id = $_SESSION['user_id'];
$uploadDir = 'uploads/avatars/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$conn = create_connection();

// Tạo thư mục nếu chưa tồn tại
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

// Kiểm tra quyền ghi
if (!is_writable($uploadDir)) {
  die('Thư mục uploads/avatars/ không thể ghi được!');
}

// Xử lý xóa ảnh đại diện
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
  // Lấy đường dẫn ảnh hiện tại
  $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $currentAvatar = $user['avatar'];

  // Xóa file ảnh nếu tồn tại và không phải ảnh mặc định
  if ($currentAvatar && $currentAvatar !== 'uploads/avatars/default.png' && file_exists($currentAvatar)) {
    unlink($currentAvatar);
  }

  // Cập nhật cơ sở dữ liệu về NULL
  $stmt = $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
  $stmt->bind_param("s", $user_id);
  if ($stmt->execute()) {
    header("Location: home.php");
  } else {
    echo 'Lỗi khi xóa ảnh đại diện!';
  }
  $stmt->close();
  $conn->close();
  exit;
}

// Xử lý đổi ảnh đại diện
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
  $file = $_FILES['avatar'];
  $fileName = 'user_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
  $filePath = $uploadDir . $fileName;

  // Kiểm tra kích thước và loại file
  if ($file['size'] > $maxFileSize) {
    die('File quá lớn! Dung lượng tối đa là 5MB.');
  }

  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
  if (!in_array($file['type'], $allowedTypes)) {
    die('Chỉ chấp nhận file JPEG, PNG hoặc GIF!');
  }

  // Xóa ảnh cũ nếu có
  $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
  $stmt->bind_param("s", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $currentAvatar = $user['avatar'];

  if ($currentAvatar && $currentAvatar !== 'uploads/avatars/default.png' && file_exists($currentAvatar)) {
    unlink($currentAvatar);
  }

  // Di chuyển file mới
  if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // Cập nhật đường dẫn avatar trong cơ sở dữ liệu
    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("ss", $filePath, $user_id);
    if ($stmt->execute()) {
      header("Location: home.php");
    } else {
      echo 'Lỗi khi cập nhật cơ sở dữ liệu!';
    }
  } else {
    echo 'Lỗi khi tải lên file!';
  }
} else {
  echo 'Không có file được tải lên hoặc có lỗi xảy ra!';
}

$stmt->close();
$conn->close();
?>