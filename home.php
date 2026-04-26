<?php
session_start();
require 'db.php';
if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
  die();
}
$user_id = $_SESSION['user_id'];
$conn = create_connection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$note_id = '';
$defaultavatar = 'uploads/avatars/default.png';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="home.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    html,
    body {
      background-color: rgba(235, 154, 15, 0.112) !important;
    }

    body {
      margin: 0;
      padding: 0;
      background-color: rgba(231, 215, 188, 0.485);
      font-family: Arial, sans-serif;
    }

    /* Thêm style cho thông báo */
    #feedback {
      padding: 10px 15px;
      border-radius: 5px;
      font-weight: bold;
      margin-top: 10px;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    /* Các style khác giữ nguyên như trước */
    .shared-notes-title {
      text-align: center;
      font-size: 30px;
      /* Adjusted size */
      font-weight: bold;
      color: #F5B971;
      /* Consistent color */
      margin: 30px 0 20px;
      /* Adjusted margin */
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 10px;
    }

    .shared-notes-title i {
      color: #FDD998;
      /* Consistent icon color */
      font-size: 24px;
      /* Adjusted icon size */
    }
  </style>
</head>

<body>
  <div class="layout-container">
    <div class="right-content">
      <div class="user-header d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-3">
          <div class="dropdown">
            <img src="<?php echo htmlspecialchars($user['avatar'] ?? 'uploads/avatars/default.png'); ?>" alt="Avatar"
              class="avatar-img rounded-circle" id="avatarDropdown" data-bs-toggle="dropdown" title="User settings"
              aria-expanded="false">
            <ul class="dropdown-menu" aria-labelledby="avatarDropdown">
              <li>
                <a class="dropdown-item" href="#" data-bs-toggle="collapse" data-bs-target="#settingsSubmenu"
                  aria-expanded="false" aria-controls="settingsSubmenu">
                  <i class="fas fa-cog"></i> Settings
                </a>
                <ul class="collapse list-unstyled ps-3" id="settingsSubmenu">
                  <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadAvatarModal"><i
                        class="fas fa-camera"></i> Upload avatar</a></li>
                  <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewAvatarModal"><i
                        class="fas fa-eye"></i> View avatar</a></li>
                  <li><a class="dropdown-item" href="upload_avatar.php?action=delete"
                      onclick="return confirm('Are you sure to delete your avatar?')"><i class="fas fa-trash"></i>
                      Delete avatar</a></li>
                  <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"><i
                        class="fas fa-key"></i> Reset password</a></li>
                  <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal"
                      data-bs-target="#deleteAccountModal"><i class="fas fa-user-times"></i> Delete account</a></li>
                </ul>
              </li>
              <li><a class="dropdown-item" href="#" id="dark-mode-toggle"><i class="fas fa-moon"></i> Dark mode</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item logout-btn" href="logout.php"
                  onclick="return confirm('Are you sure to logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
              </li>
            </ul>
          </div>
          <h2 class="mb-0 text">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
        </div>
        <?php
        if (!$user['authenticated']) {
          ?>
          <div style="border: 4px solid red; padding: 10px; background-color: white; color: red; border-radius: 8px;">
            Your account has not been authenticated yet.</div>
        <?php } ?>
      </div>

      <div class="notes-title">
        <i class="fas fa-sticky-note"></i> Your Notes
      </div>

      <div class="note-wrapper">
        <div class="action-buttons" style="margin-bottom: 15px; display: flex; gap: 10px;">
          <button class="btn btn-primary" id="createBtn" data-bs-toggle="modal" data-bs-target="#TextModal">Create
            Note</button>
          <button class="btn btn-info btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#labelModal">
            <i class="fas fa-tags me-1"></i> Manage Labels
          </button>
        </div>


        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
          <div class="search-bar" style="flex-grow: 1;">
            <input type="text" id="searchInput" placeholder="Search keyword..."
              style="padding: 6px; width: 100%; max-width: 300px; border-radius: 8px; border: 1px solid #ccc;">
          </div>
          <div class="col-auto"> <select id="filterLabel" class="form-select form-select-sm" style="min-width: 180px;">
              <option value="">Filter by All Labels</option>
            </select>
          </div>
          <div class="setting-icon">
            <span id="settings-btn"><i class="fas fa-chevron-down"></i></span>
            <div id="view-dropdown" class="view-toggle">
              <button onclick="setView('grid')" title="Grid View"><i class="fas fa-th"></i></button>
              <button onclick="setView('list')" title="List View"><i class="fas fa-list"></i></button>
            </div>
          </div>
        </div>

        <div id="notes" class="note-container grid-view">
          <ul></ul>
        </div>
        <br><br>
      </div>

      <div class="shared-notes-title">
        <i class="fas fa-share-alt"></i> Shared With You
      </div>
      <div class="note-wrapper" id="shared-notes-wrapper">
        <div id="shared-notes" class="note-container grid-view">
          <ul>
          </ul>
        </div>
        <div id="empty-shared-message" class="empty-message" style="display: none; text-align:center; padding: 20px;">
          No notes have been shared with you yet.
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="labelModal" tabindex="-1" aria-labelledby="labelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="labelModalLabel"><i class="fas fa-tags me-2"></i>Manage Labels</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h6>Existing Labels:</h6>
          <ul id="labelList" class="list-group mb-3" style="max-height: 200px; overflow-y: auto;">
            <li id="noLabelsMessage" class="list-group-item text-muted" style="display:none;"><i>No labels created
                yet.</i></li>
          </ul>
          <hr>
          <h6>Create New Label:</h6>
          <div class="input-group">
            <input type="text" id="newLabelName" class="form-control form-control-sm"
              placeholder="Enter new label name">
            <button id="addLabelBtn" class="btn btn-sm btn-primary" type="button">
              <i class="fas fa-plus me-1"></i> Add Label
            </button>
          </div>
          <div id="labelError" class="text-danger mt-2" style="display:none;"></div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for uploading avatar -->
  <div class="modal fade" id="uploadAvatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="upload_avatar.php" method="post" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title">Upload Avatar</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="avatarInput" class="form-label">Choose an image (JPEG, PNG, GIF, max 2MB):</label>
              <input type="file" class="form-control" id="avatarInput" name="avatar"
                accept="image/jpeg,image/png,image/gif" required>
            </div>
            <div id="avatarPreview" class="text-center">
              <img src="<?php echo htmlspecialchars($user['avatar'] ?? $defaultavatar); ?>" alt="Current Avatar"
                class="rounded-circle mb-2" style="width: 100px; height: 100px; object-fit: cover;">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Upload</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal for viewing avatar -->
  <div class="modal fade" id="viewAvatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Your Avatar</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <img src="<?php echo htmlspecialchars($user['avatar'] ?? $defaultavatar); ?>" alt="Avatar"
            class="rounded-circle" style="width: 200px; height: 200px; object-fit: cover;">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for resetting password -->
  <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="resetPasswordForm" method="post">
          <div class="modal-header">
            <h5 class="modal-title">Confirm to reset password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="reset_current_password" class="form-label">Current password</label>
              <input type="password" class="form-control" id="reset_current_password" name="current_password" required>
            </div>
            <div id="feedback"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal for confirming delete account -->
  <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="deleteAccountForm" method="post" action="delete_account.php">
          <div class="modal-header">
            <h5 class="modal-title">Confirm to delete account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="text-danger">This action cannot be undone. All your data will be permanently deleted.</p>
            <div class="mb-3">
              <label for="delete_current_password" class="form-label">Current password</label>
              <input type="password" class="form-control" id="delete_current_password" name="current_password" required>
            </div>
            <div id="deleteFeedback"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal for locking notes with password -->
  <div id="lockNoteModal" class="modal fade">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Lock Note</h5>
          <button type="button" class="btn-close close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="lockNoteId">
          <div class="form-group">
            <label for="lockPassword">Password:</label>
            <input type="password" class="form-control" id="lockPassword">
          </div>
          <div class="form-group">
            <label for="confirmLockPassword">Confirm Password:</label>
            <input type="password" class="form-control" id="confirmLockPassword">
          </div>
          <div id="passwordError" class="text-danger" style="display: none;">Passwords do not match.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="process_lock()">Lock</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for unlocking notes -->
  <div id="unlockNoteModal" class="modal fade">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Unlock Note</h5>
          <button type="button" class="btn-close close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="unlockNoteId">
          <div class="form-group">
            <label for="unlockPassword">Password:</label>
            <input type="password" class="form-control" id="unlockPassword">
            <div style="margin-top: 10px">
              <label for="removePassword">Remove Lock</label>
              <input type="checkbox" class="form-check-input" id="removePassword">
            </div>
          </div>
          <div id="unlockError" class="text-danger" style="display: none;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="process_unlock()">Unlock</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for sharing notes -->
  <div id="shareNoteModal" class="modal fade">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Share Note</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="shareNoteId">

          <div class="mb-3">
            <h6><i class="fas fa-users me-2"></i>Already shared with:</h6>
            <ul id="alreadySharedList" class="list-group list-group-flush border rounded"
              style="max-height: 150px; overflow-y: auto;">
              <li id="noOneSharedMessage" class="list-group-item text-muted" style="display: none;"><i>Not shared with
                  anyone yet.</i></li>
            </ul>
          </div>
          <hr class="my-3">
          <h6><i class="fas fa-share-alt me-2"></i>Share with new person:</h6>
          <div>
            <label for="shareOption" class="form-label">Share via:</label>
            <select class="form-select form-select-sm" id="shareOption">
              <option value="email">Email</option>
              <option value="username">Username</option>
            </select>
          </div>
          <div id="shareByEmail" class="mt-2"> <label for="shareEmail" class="form-label">Recipient Email:</label>
            <input type="email" class="form-control form-control-sm" id="shareEmail" placeholder="Enter email">
          </div>
          <div id="shareByUsername" class="mt-2" style="display: none;"> <label for="shareUsername"
              class="form-label">Recipient Username:</label>
            <input type="text" class="form-control form-control-sm" id="shareUsername" placeholder="Enter username">
          </div>
          <div id="shareError" class="text-danger mt-2" style="display: none;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-sm btn-primary" onclick="process_share()">Share</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for creating/editing notes -->
  <div class="modal fade" id="TextModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form action="process_note.php" method="post" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title mb-0 editable-title" id="noteTitle" contenteditable="true" spellcheck="false"
              autocomplete="off" role="textbox">New
              Untitled Note (Click to change title)</h5>
            <button type="button" class="btn-close close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <textarea id="editor" name="noteContent" contenteditable="true" class="form-control mb-3"></textarea>
            <input class="form-control" name="note_id" type="hidden" id="note_id" value="">
            <img id="modalImage" style="max-width: 100%; display: none; margin-top: 10px;" />
            <div class="mb-3" id="imgDiv">
              <label for="images" class="form-label">Attach Image</label>
              <input type="file" class="form-control" name="images" id="images" multiple accept="image/*">
            </div>
            <div class="mb-3">
              <label for="noteLabels" class="form-label"><i class="fas fa-tags me-1"></i>Labels:</label>
              <select id="noteLabels" name="noteLabels[]" class="form-select form-select-sm" multiple>
              </select>
              <small class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to deselect or select multiple
                labels.</small>
            </div>
            <button type="button" id="imgClear" class="btn btn-secondary">Clear image</button>
          </div>
          <div class="modal-footer">
            <label for="noteColor" class="form-label mb-0">Note Color:</label>
            <input type="color" id="noteColor" value="#FFFFFF" class="form-control form-control-color"> <span
              id="status"></span>
            <button class="btn btn-success" id="saveBtn" type="button">Save Note</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="home.js?v=<?= time() ?>"></script>

</body>

</html>
<?php $conn->close(); ?>