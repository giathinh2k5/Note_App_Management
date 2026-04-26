const editorEl = document.getElementById("editor");
const titleEl = document.getElementById("noteTitle");
const saveBtn = document.getElementById("saveBtn");
const createBtn = document.getElementById("createBtn");
const saveStatus = document.getElementById("status");
const noteId = document.getElementById("note_id");
const noteList = document.querySelector("#notes ul");
const img = document.getElementById("modalImage");
const imgInput = document.getElementById("images");
let imgChange = false;
let checkClear = false;
const noteColor = document.getElementById('noteColor');
const imgDiv = document.getElementById('imgDiv');
const imgClear = document.getElementById('imgClear');
let temp = "";
const modalEl = document.getElementById('TextModal');
const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
const shareModalEl = document.getElementById('shareNoteModal');
const shareModal = shareModalEl ? new bootstrap.Modal(shareModalEl) : null;
const lockModalEl = document.getElementById('lockNoteModal');
const lockModal = lockModalEl ? new bootstrap.Modal(lockModalEl) : null;
const lockError = document.getElementById("passwordError");
const lockNoteId = document.getElementById('lockNoteId');
const unlockModalEl = document.getElementById('unlockNoteModal');
const unlockModal = unlockModalEl ? new bootstrap.Modal(unlockModalEl) : null;
const unlockNoteId = document.getElementById('unlockNoteId');
const unlockError = document.getElementById("unlockError");
const sharedNoteList = document.querySelector("#shared-notes ul");
const emptySharedMessage = document.getElementById("empty-shared-message");
const sharedNoteListUL = document.querySelector("#shared-notes ul");
const labelListUL = document.getElementById("labelList");
const noLabelsMessageLI = document.getElementById("noLabelsMessage");
const newLabelNameInput = document.getElementById("newLabelName");
const addLabelBtn = document.getElementById("addLabelBtn");
const filterLabelSelect = document.getElementById("filterLabel");
const noteLabelsSelect = document.getElementById("noteLabels");
const labelErrorDiv = document.getElementById("labelError");
const shareOptionSelect = document.getElementById('shareOption');
const shareByEmailDiv = document.getElementById('shareByEmail');
const shareByUsernameDiv = document.getElementById('shareByUsername');

function setView(view) {
  console.log("setView() is called with view:", view);
  const notesContainer = document.getElementById("notes");
  notesContainer.classList.remove("list-view", "grid-view");
  notesContainer.classList.add(`${view}-view`);
  const sharedNotesContainer = document.getElementById("shared-notes");
  if (sharedNotesContainer) {
    sharedNotesContainer.classList.remove("list-view", "grid-view");
    sharedNotesContainer.classList.add(`${view}-view`);
  }
  localStorage.setItem("view", view);
}

document.getElementById("settings-btn").addEventListener("click", function (e) {
  e.stopPropagation();
  document.getElementById("view-dropdown").classList.toggle("show");
});

function toggleNoteMenu(event, icon) {
  event.stopPropagation();
  const noteMenu = icon.nextElementSibling;
  noteMenu.classList.toggle("show");
}

// === LABEL MANAGEMENT FUNCTIONS ===
function fetchLabels() {
  return fetch('process_label.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=fetch'
  })
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error ${res.status} fetching labels`);
      return res.json();
    })
    .then(data => {
      if (data.success) return data.labels || [];
      console.error("Failed to fetch labels:", data.msg);
      return [];
    })
    .catch(err => {
      console.error("Error in fetchLabels:", err);
      return [];
    });
}

function renderLabelList(labels) {
  if (!labelListUL || !noLabelsMessageLI) return;
  labelListUL.innerHTML = "";
  if (labels.length === 0) {
    noLabelsMessageLI.style.display = 'list-item';
    return;
  }
  noLabelsMessageLI.style.display = 'none';

  labels.forEach(label => {
    const li = document.createElement("li");
    li.className = "list-group-item d-flex justify-content-between align-items-center py-1 px-2";

    const labelNameSpan = document.createElement("span");
    labelNameSpan.textContent = label.name;
    labelNameSpan.setAttribute("contenteditable", "true");
    labelNameSpan.classList.add("flex-grow-1", "me-2");
    labelNameSpan.style.cursor = "text";
    labelNameSpan.onblur = function () { renameLabel(label.id, this.innerText); };

    labelNameSpan.onkeydown = function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        this.blur();
      }
    };

    const deleteBtn = document.createElement("button");
    deleteBtn.className = "btn btn-sm btn-outline-danger py-0 px-1";
    deleteBtn.innerHTML = '<i class="fas fa-trash-alt fa-xs"></i>';
    deleteBtn.title = "Delete label";
    deleteBtn.onclick = function () { deleteLabel(label.id); };

    li.appendChild(labelNameSpan);
    li.appendChild(deleteBtn);
    labelListUL.appendChild(li);
  });
}

function renderLabelFilter(labels) {
  if (!filterLabelSelect) return;
  filterLabelSelect.innerHTML = '<option value="">Filter by All Labels</option>';
  labels.forEach(label => {
    const opt = document.createElement("option");
    opt.value = label.id;
    opt.textContent = label.name;
    filterLabelSelect.appendChild(opt);
  });
}

function renderNoteLabelSelect(note = null) {
  if (!noteLabelsSelect) return;

  fetchLabels().then(labels => {
    noteLabelsSelect.innerHTML = "";

    if (labels.length === 0) {
      noteLabelsSelect.innerHTML = '<option value="" disabled>No labels available</option>';
      return;
    }

    labels.forEach(label => {
      const option = document.createElement("option");
      option.value = label.id;
      option.textContent = label.name;

      if (note && note.label_ids && note.label_ids.includes(String(label.id))) {
        option.selected = true;
      }
      noteLabelsSelect.appendChild(option);
    });
  });
}

function addLabel() {
  if (!newLabelNameInput || !labelErrorDiv) return;
  const name = newLabelNameInput.value.trim();
  labelErrorDiv.style.display = 'none';
  labelErrorDiv.textContent = '';

  if (!name) {
    labelErrorDiv.textContent = 'Label name cannot be empty.';
    labelErrorDiv.style.display = 'block';
    return;
  }
  fetch('process_label.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=create&name=${encodeURIComponent(name)}`
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        fetchLabels().then(updatedLabels => {
          renderLabelList(updatedLabels);
          renderLabelFilter(updatedLabels);
          if (modalEl.classList.contains('show') && !noteId.value) {
            renderNoteLabelSelect();
          }
        });
        newLabelNameInput.value = "";
      } else {
        labelErrorDiv.textContent = data.msg || 'Failed to create label.';
        labelErrorDiv.style.display = 'block';
      }
    })
    .catch(err => {
      labelErrorDiv.textContent = 'Error creating label: ' + err;
      labelErrorDiv.style.display = 'block';
      console.error("Error in addLabel:", err);
    });
}

function renameLabel(id, newName) {
  newName = newName.trim();
  if (!newName) {
    alert("Label name cannot be empty. Reverting.");
    renderLabels();
    return;
  }
  fetch('process_label.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=rename&label_id=${id}&name=${encodeURIComponent(newName)}`
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        fetchLabels().then(updatedLabels => {
          renderLabelList(updatedLabels);
          renderLabelFilter(updatedLabels);
        });
        renderNotes(filterLabelSelect.value);
      } else {
        alert(data.msg || "Failed to rename label.");
        renderLabels();
      }
    })
    .catch(err => {
      alert("Error renaming label.");
      console.error("Error in renameLabel:", err);
      renderLabels();
    });
}

function deleteLabel(id) {
  if (confirm("Are you sure you want to delete this label? This will remove it from all notes.")) {
    fetch('process_label.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=delete&label_id=${id}`
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          fetchLabels().then(updatedLabels => {
            renderLabelList(updatedLabels);
            renderLabelFilter(updatedLabels);
          });
          renderNotes(filterLabelSelect.value);
        } else {
          alert(data.msg || "Failed to delete label.");
        }
      })
      .catch(err => {
        alert("Error deleting label.");
        console.error("Error in deleteLabel:", err);
      });
  }
}

function shareNote(noteId_param) {
  const shareNoteId = document.getElementById("shareNoteId");
  const shareError = document.getElementById("shareError");
  const alreadySharedListUL = document.getElementById("alreadySharedList");
  const noOneSharedMessageLI = document.getElementById("noOneSharedMessage");

  shareNoteId.value = noteId_param;
  shareError.style = "display: none;"

  if (alreadySharedListUL) alreadySharedListUL.innerHTML = '';
  if (noOneSharedMessageLI) noOneSharedMessageLI.style.display = 'block';

  if (noteId_param) {
    const targetId = noteId_param;
    const formData = new FormData();
    formData.append('action', 'get_shared_with');
    formData.append('note_id', targetId);
    fetch('process_share.php', {
      method: 'POST',
      body: formData
    })
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        if (data.success && data.shared_with) {
          if (data.shared_with.length > 0) {
            if (noOneSharedMessageLI) noOneSharedMessageLI.style.display = 'none';
            data.shared_with.forEach(user => {
              const listItem = document.createElement('li');
              listItem.className = 'list-group-item d-flex justify-content-between align-items-center py-1 px-2';

              const userNameSpan = document.createElement('span');
              userNameSpan.textContent = user.identifier;
              userNameSpan.title = `Shared with: ${user.identifier}`;
              listItem.appendChild(userNameSpan);

              const unshareBtn = document.createElement('button');
              unshareBtn.className = 'btn btn-sm btn-outline-danger py-0 px-1';
              unshareBtn.innerHTML = '<i class="fas fa-times fa-xs"></i>';
              unshareBtn.title = `Stop sharing with ${user.identifier}`;
              unshareBtn.onclick = function () {
                stopSharingNoteWithUser(targetId, user.shared_note_record_id, listItem);
              };
              listItem.appendChild(unshareBtn);
              if (alreadySharedListUL) alreadySharedListUL.appendChild(listItem);
            });
          } else { // Success but list is empty
            if (noOneSharedMessageLI) noOneSharedMessageLI.style.display = 'block';
          }
        } else {
          console.error("Error fetching shared list:", data.msg);
          if (noOneSharedMessageLI) noOneSharedMessageLI.textContent = data.msg || 'Could not load shared list.';
          if (noOneSharedMessageLI) noOneSharedMessageLI.style.display = 'block';
        }
      })
      .catch(err => {
        console.error("Fetch error for shared list:", err);
        if (noOneSharedMessageLI) {
          noOneSharedMessageLI.textContent = 'Error loading shared list. Check console.';
          noOneSharedMessageLI.style.display = 'block';
        }
      });
  } else {
    if (noOneSharedMessageLI) noOneSharedMessageLI.style.display = 'block';
  }

  const shareEmailInput = document.getElementById('shareEmail');
  if (shareEmailInput) shareEmailInput.value = '';
  const shareUsernameInput = document.getElementById('shareUsername');
  if (shareUsernameInput) shareUsernameInput.value = '';

  shareModal.show();
}

function stopSharingNoteWithUser(noteGlobalId, sharedNoteRecordId, listItemElement) {
  if (!confirm("Are you sure you want to stop sharing this note with this user?")) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'unshare');
  formData.append('note_id', noteGlobalId);
  formData.append('shared_note_record_id', sharedNoteRecordId);

  fetch('process_share.php', {
    method: 'POST',
    body: formData
  })
    .then(res => {
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res.json();
    })
    .then(data => {
      if (data.success) {
        alert(data.msg || 'Successfully stopped sharing.');
        if (listItemElement) {
          listItemElement.remove();
        }
        const alreadySharedListUL = document.getElementById("alreadySharedList");
        const noOneSharedMessageLI = document.getElementById("noOneSharedMessage");
        if (alreadySharedListUL && alreadySharedListUL.children.length === 0 && noOneSharedMessageLI) {
          noOneSharedMessageLI.style.display = 'block';
        }
      } else {
        alert('Failed to stop sharing: ' + (data.msg || 'Unknown error'));
      }
    })
    .catch(err => {
      console.error('Error stopping sharing:', err);
      alert('An error occurred while trying to stop sharing. Check console.');
    });
}


function process_share() {
  const note_id_to_share = document.getElementById("shareNoteId").value;
  const shareOption = document.getElementById("shareOption").value;
  const shareError = document.getElementById("shareError");
  const email = document.getElementById("shareEmail").value;
  const username = document.getElementById("shareUsername").value;

  const formData = new FormData();
  formData.append('action', 'share');
  formData.append('note_id', note_id_to_share);

  if (shareOption === 'email') {
    if (!email) {
      shareError.innerText = "Email cannot be empty.";
      shareError.style.display = "block";
      return;
    }
    formData.append('email', email);
  } else if (shareOption === 'username') {
    if (!username) {
      shareError.innerText = "Username cannot be empty.";
      shareError.style.display = "block";
      return;
    }
    formData.append('username', username);
  }
  if (shareError) shareError.style.display = "none";
  fetch('process_share.php', {
    method: 'POST',
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (data.result === 'failed') {
        shareError.innerText = data.msg;
        shareError.style.display = 'block';
      } else {
        shareError.style.display = 'none';
        shareModal.hide();
        alert(data.msg || 'Note shared successfully!');
        renderNotes();
      }
    })
    .catch(err => {
      shareError.innerText = 'An error occurred: ' + err;
      shareError.style.display = 'block';
    });
}

function lockNote(noteIdParam) {
  lockNoteId.value = noteIdParam;
  lockError.style = "display: none;"
  lockModal.show();
}

function process_lock() {
  const password = document.getElementById('lockPassword').value;
  const confirmPassword = document.getElementById('confirmLockPassword').value;
  if (password !== confirmPassword) {
    lockError.innerText = "Passwords don't match!"
    lockError.style = "display: block;"
  } else {
    const formData = new FormData();
    formData.append("lockNoteId", lockNoteId.value);
    formData.append("lockPassword", document.getElementById("lockPassword").value);
    fetch("process_lock.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.result) {
          lockError.innerText = data.msg;
          lockError.style = "display: block;";
        } else {
          lockModal.hide();
          renderNotes();
        }
      })
      .catch(err => {
        lockError.innerText = err;
        lockError.style = "display: block;";
      });
  }
}

function unlockNote(noteIdParam) {
  unlockNoteId.value = noteIdParam;
  unlockError.style.display = "none";
  unlockModal.show();
}

function process_unlock() {
  const unlockPassword = document.getElementById('unlockPassword').value;
  const removePassword = document.getElementById('removePassword');
  const currentUnlockNoteId = unlockNoteId.value;
  if (!currentUnlockNoteId) {
    if (unlockError) {
      unlockError.innerText = "No note ID specified for unlocking.";
      unlockError.style.display = "block";
    }
    return;
  }

  const formData = new FormData();
  formData.append("unlockNoteId", unlockNoteId.value);
  formData.append("unlockPassword", unlockPassword);
  formData.append("remove", removePassword.checked ? 1 : 0);
  fetch("process_unlock.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (!data.result) {
        if (data.stat) {
          unlockModal.hide();
          renderNotes(filterLabelSelect ? filterLabelSelect.value : '');
        } else {
          const noteObjectForDisplay = data.note;
          let getLockedNoteId = "note-" + data.note.global_id;
          let currentNoteElement = document.getElementById(getLockedNoteId);
          const labelsHTML = (noteObjectForDisplay.label_names && noteObjectForDisplay.label_names.length > 0)
            ? `<div class="note-labels mb-1" onclick="openNoteInModal(noteObjectForDisplay)">${noteObjectForDisplay.label_names.map(name => `<span class="label-badge">${htmlspecialchars(name)}</span>`).join('')}</div>`
            : '<div class="note-labels mb-1" onclick="openNoteInModal(noteObjectForDisplay)"></div>';
          const newInnerHTML = `
            <h3>${htmlspecialchars(noteObjectForDisplay.name)}</h3>
            <p>${noteObjectForDisplay.content ? htmlspecialchars(noteObjectForDisplay.content.substring(0, 100)) + (noteObjectForDisplay.content.length > 100 ? '...' : '') : 'No content'}</p>
            ${labelsHTML}
            <div class="note-actions">
              <a href="#" onclick="editNote('${noteObjectForDisplay.global_id}', this)" title="Edit"><i class="fa fa-pencil" style="color: blue; font-size: 20px;"></i></a>
              <a href="#" onclick="shareNote('${noteObjectForDisplay.global_id}')" title="Share"><i class="fas fa-share" style="color: purple; font-size: 20px;"></i></a>
              <a href="#" onclick="deleteNote('${noteObjectForDisplay.global_id}')" title="Delete"><i class="fa fa-close" style="color: black; font-size: 20px;"></i></a>
              <a href="#" onclick="lockNote('${noteObjectForDisplay.global_id}')" title="Lock Note"><i class="fas fa-lock" style="color: orange; font-size: 20px;"></i></a>
              <a href="#" onclick="togglePin('${noteObjectForDisplay.global_id}')" title="${noteObjectForDisplay.pinned ? 'Unpin' : 'Pin'}"><i class="fas fa-thumbtack" style="color: ${noteObjectForDisplay.pinned ? 'red' : 'green'}; font-size: 20px;"></i></a>
            </div>
          `;
          const newNoteElement = document.createElement('li');
          newNoteElement.setAttribute("data-id", currentNoteElement.getAttribute("data-id"));
          newNoteElement.setAttribute("id", currentNoteElement.getAttribute("id"));
          newNoteElement.setAttribute("style", currentNoteElement.getAttribute("style"));
          newNoteElement.className = currentNoteElement.className;
          newNoteElement.innerHTML = newInnerHTML;
          currentNoteElement.parentNode.replaceChild(newNoteElement, currentNoteElement);
          newNoteElement.addEventListener("click", (e) => {
            if (e.target.closest('.note-actions') || e.target.closest('.note-labels')) return;
            openNoteInModal(noteObjectForDisplay);
          });
          if (unlockModal) unlockModal.hide();
        }
      }
    })
    .catch(err => {
      unlockError.innerText = "An error occurred: " + err;
      unlockError.style.display = "block";
    });
}

function deleteNote(noteId) {
  if (confirm("Are you sure you want to delete this note?")) {
    fetch('process_note.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=delete&note_id=' + encodeURIComponent(noteId) // Sửa lỗi cú pháp
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          renderNotes();
        } else {
          alert('Failed to delete note: ' + data.msg);
        }
      })
      .catch(err => {
        console.error('Error deleting note:', err);
        alert('Error deleting note!');
      });
  }
}

function togglePin(noteId) {
  fetch('process_note.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=toggle_pin&note_id=' + encodeURIComponent(noteId) // Sửa lỗi cú pháp
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        renderNotes();
      } else {
        alert('Failed to toggle pin: ' + data.msg);
      }
    })
    .catch(err => {
      console.error('Error toggling pin:', err);
      alert('Error toggling pin!');
    });
}

function searchNotes() {
  const keyword = document.getElementById("searchInput").value.toLowerCase();
  const notes = document.querySelectorAll(".note-card");
  notes.forEach(note => {
    const title = note.querySelector("h3")?.textContent.toLowerCase() || "";
    const content = note.querySelector("p")?.textContent.toLowerCase() || "";
    if (title.includes(keyword) || content.includes(keyword)) {
      note.style.display = "";
    } else {
      note.style.display = "none";
    }
  });
}
document.getElementById("searchInput").addEventListener("input", searchNotes);

document.addEventListener("click", function () {
  const menus = document.querySelectorAll(".note-menu");
  menus.forEach(menu => {
    menu.classList.remove("show");
  });
  document.getElementById("view-dropdown").classList.remove("show");
});

document.querySelectorAll(".dropdown-menu").forEach(menu => {
  menu.addEventListener("click", function (e) {
    e.stopPropagation();
  });
});

// === RENDERNOTES FUNCTION ===
function renderNotes(labelIdFilter = '') {
  // const noteContainer = document.getElementById("notes"); // div containing the ul
  // noteList is global: const noteList = document.querySelector("#notes ul");
  // sharedNoteListUL is global: const sharedNoteListUL = document.querySelector("#shared-notes ul");
  const emptyUserNotesMessage = document.getElementById("empty-message");

  let fetchURL = "get_note.php";
  if (labelIdFilter) {
    fetchURL += `?label_id=${encodeURIComponent(labelIdFilter)}`;
  }

  fetch(fetchURL)
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      return res.json();
    })
    .then(data => {
      if (!data.success && data.msg) {
        console.error("Error from get_note.php:", data.msg);
        if (noteList) noteList.innerHTML = `<li class='list-group-item text-danger'>${data.msg || 'Could not load notes.'}</li>`;
        if (sharedNoteListUL) sharedNoteListUL.innerHTML = `<li class='list-group-item text-danger'>${data.msg || 'Could not load shared notes.'}</li>`;
        if (emptyUserNotesMessage) emptyUserNotesMessage.style.display = "none";
        if (emptySharedMessage) emptySharedMessage.style.display = "none";
        return;
      }

      if (noteList) noteList.innerHTML = "";
      if (sharedNoteListUL) sharedNoteListUL.innerHTML = "";

      // Handle user's own notes (data.items)
      if (!data.items || data.items.length === 0) {
        if (emptyUserNotesMessage) emptyUserNotesMessage.style.display = "block";
      } else {
        if (emptyUserNotesMessage) emptyUserNotesMessage.style.display = "none";
        data.items.forEach(item => {
          const li = document.createElement("li");
          li.setAttribute("data-id", item.global_id);
          li.setAttribute("id", "note-" + item.global_id);
          li.setAttribute("style", `background-color: ${item.color || '#FFF8EE'}; border-color: ${item.color ? darkenColor(item.color, 10) : '#FDD998'};`);
          li.className = "note-card";

          const labelsHTML = (item.label_names && item.label_names.length > 0)
            ? `<div class="note-labels mb-1">${item.label_names.map(name => `<span class="label-badge">${htmlspecialchars(name)}</span>`).join('')}</div>`
            : '<div class="note-labels mb-1"></div>';

          if (!item.locked) {
            li.innerHTML = `
            <h3>${htmlspecialchars(item.name)}</h3>
            <p>${item.content ? htmlspecialchars(item.content.substring(0, 100)) + (item.content.length > 100 ? '...' : '') : 'No content'}</p>
            ${labelsHTML}
            <div class="note-actions">
              <a href="#" onclick="editNote('${item.global_id}', this)" title="Edit"><i class="fa fa-pencil" style="color: blue; font-size: 20px;"></i></a>
              <a href="#" onclick="shareNote('${item.global_id}')" title="Share"><i class="fas fa-share" style="color: purple; font-size: 20px;"></i></a>
              <a href="#" onclick="deleteNote('${item.global_id}')" title="Delete"><i class="fa fa-close" style="color: black; font-size: 20px;"></i></a>
              <a href="#" onclick="lockNote('${item.global_id}')" title="Lock"><i class="fas fa-lock" style="color: orange; font-size: 20px;"></i></a>
              <a href="#" onclick="togglePin('${item.global_id}')" title="${item.pinned ? 'Unpin' : 'Pin'}"><i class="fas fa-thumbtack" style="color: ${item.pinned ? 'red' : 'green'}; font-size: 20px;"></i></a>
            </div>
            `;
            li.addEventListener("click", (e) => {
              if (e.target.closest('.note-actions') || e.target.closest('.note-labels')) return;
              openNoteInModal(item);
            });
          } else { // Locked note
            li.innerHTML = `
            <h3>${htmlspecialchars(item.name)}</h3>
            <p><i class="fas fa-lock"></i> Note is locked.</p>
            ${labelsHTML}
            <div class="note-actions">
              <a href="#" onclick="unlockNote('${item.global_id}')" title="Unlock"><i class="fas fa-unlock" style="color: blue; font-size: 20px;"></i></a>
              <a href="#" onclick="shareNote('${item.global_id}')" title="Share (Locked)"><i class="fas fa-share" style="color: purple; font-size: 20px;"></i></a>
              <a href="#" onclick="unlockNote('${item.global_id}')" title="Delete"><i class="fa fa-close" style="color: black; font-size: 20px;"></i></a>
              <a href="#" onclick="unlockNote('${item.global_id}')" title="Unlock"><i class="fas fa-lock-open" style="color: red; font-size: 20px;"></i></a> 
              <a href="#" onclick="togglePin('${item.global_id}')" title="${item.pinned ? 'Unpin' : 'Pin'}"><i class="fas fa-thumbtack" style="color: ${item.pinned ? 'red' : 'green'}; font-size: 20px;"></i></a>
            </div>
            `;
            li.addEventListener("click", (e) => lockHandlerWrapper(e, item.global_id));
          }
          if (noteList) noteList.appendChild(li);
        });
      }

      // Handle shared notes (data.shareItems)
      if (sharedNoteListUL) {
        if (!data.shareItems || data.shareItems.length === 0) {
          if (emptySharedMessage) emptySharedMessage.style.display = "block";
        } else {
          if (emptySharedMessage) emptySharedMessage.style.display = "none";
          data.shareItems.forEach(item => {
            const li = document.createElement("li");
            li.setAttribute("data-id", item.global_id);
            li.setAttribute("id", "shared-" + item.global_id);
            li.setAttribute("style", `background-color: ${item.color || '#FFF8EE'}; border-color: ${item.color ? darkenColor(item.color, 10) : '#FDD998'};`);
            li.className = "note-card";

            let ownerInfo = item.shared_by_owner_id ? ` (Owner: User ${item.shared_by_owner_id})` : '';
            const sharedLabelsHTML = (item.label_names && item.label_names.length > 0)
              ? `<div class="note-labels mb-1">${item.label_names.map(name => `<span class="label-badge">${htmlspecialchars(name)}</span>`).join('')}</div>`
              : '<div class="note-labels mb-1"></div>';


            if (!item.locked) {
              li.innerHTML = `
                <h3>${htmlspecialchars(item.name)}${ownerInfo}</h3>
                <p>${item.content ? htmlspecialchars(item.content.substring(0, 100)) + (item.content.length > 100 ? '...' : '') : 'No content'}</p>
                ${sharedLabelsHTML}
                <div class="note-actions">
                  <a href="#" onclick="viewSharedNoteModal('${item.global_id}', '${jsEscape(item.name)}', '${jsEscape(item.content || '')}', '${jsEscape(item.image || '')}', '${jsEscape(item.color || '#FFFFFF')}', '${jsEscape(String(item.shared_by_owner_id) || 'Unknown')}')" title="View"><i class="fas fa-eye" style="color: green; font-size: 20px;"></i></a>
                </div>
              `;
              li.addEventListener("click", (e) => {
                if (e.target.closest('.note-actions') || e.target.closest('.note-labels')) return;
                viewSharedNoteModal(item.global_id, item.name, item.content || '', item.image || '', item.color || '#FFFFFF', String(item.shared_by_owner_id) || 'Unknown');
              });
            } else { // Locked shared note
              li.innerHTML = `
                <h3>${htmlspecialchars(item.name)}${ownerInfo}</h3>
                <p><i class="fas fa-lock"></i> Shared note is locked by owner.</p>
                ${sharedLabelsHTML}
                <div class="note-actions">
                  <a href="#" title="Locked by owner"><i class="fas fa-eye-slash" style="color: grey; font-size: 20px;"></i></a>
                </div>
              `;
            }
            sharedNoteListUL.appendChild(li);
          });
        }
      }
    })
    .catch(err => {
      console.error('Fetch error in renderNotes:', err);
      if (noteList) {
        noteList.innerHTML = "<li class='list-group-item text-danger'>Cannot fetch your notes.</li>";
      }
      if (emptyUserNotesMessage) emptyUserNotesMessage.style.display = "none";

      if (sharedNoteListUL) {
        sharedNoteListUL.innerHTML = "<li class='list-group-item text-danger'>Cannot fetch shared notes.</li>";
      }
      if (emptySharedMessage) emptySharedMessage.style.display = "none";
    });
}

// Helper to escape strings for use in JS function calls within HTML
function jsEscape(str) {
  if (typeof str !== 'string') return '';
  return str.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}

function htmlspecialchars(str) {
  if (typeof str !== 'string') return '';
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return str.replace(/[&<>"']/g, function (m) { return map[m]; });
}


function openNoteInModal(item) {
  if (titleEl) titleEl.innerText = item.name;
  if (editorEl) editorEl.value = item.content;
  if (noteId) noteId.value = item.global_id;
  if (noteColor) noteColor.value = item.color || '#FFFFFF';

  renderNoteLabelSelect(item);

  if (item.image) {
    if (img) { img.src = item.image; img.style.display = "block"; }
    if (imgDiv) imgDiv.style.display = "none";
    if (imgClear) imgClear.style.display = "block";
  } else {
    if (img) { img.src = ""; img.style.display = "none"; }
    if (imgDiv) imgDiv.style.display = "block";
    if (imgClear) imgClear.style.display = "none";
  }
  imgChange = false;
  checkClear = false;
  if (modal) modal.show();
}


// Create Note Button
if (createBtn) createBtn.addEventListener("click", () => {
  if (titleEl) titleEl.innerText = "New Untitled Note (Click to change title)";
  if (editorEl) editorEl.value = "";
  if (noteId) noteId.value = "";
  if (noteColor) noteColor.value = "#FFFFFF";
  if (img) { img.src = ""; img.style.display = "none"; }
  if (imgInput) imgInput.value = "";
  if (imgClear) imgClear.style.display = "none";
  if (imgDiv) imgDiv.style.display = "block";
  imgChange = false;
  checkClear = false;

  renderNoteLabelSelect();

  const formData = new FormData();
  formData.append("save", "0");
  formData.append("noteTitle", titleEl.innerText);
  formData.append("noteContent", editorEl.value);
  formData.append("noteColor", noteColor.value);

  fetch("process_note.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (saveStatus) saveStatus.textContent = data.msg;
      if (data.success && data.noteId) {
        noteId.value = data.noteId;
      }
    })
    .catch(err => {
      if (saveStatus) saveStatus.textContent = "Error creating note!";
      console.error(err);
    });
});

// Auto-save function
let saveTimer;
function triggerAutoSave() {
  if (saveStatus) saveStatus.textContent = "Typing...";
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    saveNoteData(true);
  }, 1200);
}
if (editorEl) editorEl.addEventListener("input", triggerAutoSave);
if (titleEl) titleEl.addEventListener("input", triggerAutoSave);
if (noteColor) noteColor.addEventListener("input", triggerAutoSave);
if (noteLabelsSelect) noteLabelsSelect.addEventListener("change", triggerAutoSave);


// Manual Save Button in Modal
if (saveBtn) saveBtn.addEventListener("click", () => {
  clearTimeout(saveTimer); // Prevent autosave collision
  saveNoteData(false);
});

// Consolidated Save Function
function saveNoteData(isAutoSave = false) {
  const formData = new FormData();
  formData.append("save", noteId.value ? "1" : "0"); // "1" for update, "0" for new (if somehow noteId isn't set)
  formData.append("note_id", noteId.value);
  formData.append("noteTitle", titleEl.innerText);
  formData.append("noteContent", editorEl.value);
  formData.append("noteColor", noteColor.value);

  if (noteLabelsSelect) {
    const selectedLabels = Array.from(noteLabelsSelect.selectedOptions).map(opt => opt.value);
    selectedLabels.forEach(labelId => formData.append("noteLabels[]", labelId));
  }

  if (imgChange && imgInput.files[0]) {
    formData.append("noteImage", imgInput.files[0]);
    formData.append("change", "1");
  } else if (checkClear) { // Image was cleared
    formData.append("noteImage", "");
    formData.append("clearImage", "1");
    formData.append("change", "1");
  } else {
    formData.append("change", "1"); // Assume something might have changed if saving
  }

  if (saveStatus) saveStatus.textContent = isAutoSave ? "Autosaving..." : "Saving...";

  fetch("process_note.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (saveStatus) saveStatus.textContent = data.msg;
      if (data.success) {
        if (data.noteId && !noteId.value) { // If it was a new note and backend returned ID
          noteId.value = data.noteId;
        }
        imgChange = false;
        checkClear = false;
        if (data.imagePath !== undefined) {
          if (img) { img.src = data.imagePath; img.style.display = data.imagePath ? "block" : "none"; }
          if (imgClear) imgClear.style.display = data.imagePath ? "block" : "none";
          if (imgDiv) imgDiv.style.display = data.imagePath ? "none" : "block";
        } else if (data.imageCleared) {
          if (img) { img.src = ""; img.style.display = "none"; }
          if (imgClear) imgClear.style.display = "none";
          if (imgDiv) imgDiv.style.display = "block";
        }
        if (!isAutoSave) { // If manual save, re-render immediately
          renderNotes(filterLabelSelect ? filterLabelSelect.value : '');
          modal.hide(); // Close modal on successful manual save
        } else if (data.msg !== 'No changes to save') { // For autosave, render if changes were made
          renderNotes(filterLabelSelect ? filterLabelSelect.value : '');
        }
      }
    })
    .catch(err => {
      if (saveStatus) saveStatus.textContent = "Error saving note!";
      console.error("Error saving note:", err);
    });
}


// === DOMContentLoaded  ===
document.addEventListener("DOMContentLoaded", function () {
  const savedView = localStorage.getItem("view") || "grid"; // Default to grid
  setView(savedView);

  const darkModeToggle = document.getElementById('dark-mode-toggle');
  const body = document.body;
  if (localStorage.getItem('darkMode') === 'enabled') {
    body.classList.add('dark-mode');
    if (darkModeToggle) darkModeToggle.innerHTML = '<i class="fas fa-sun"></i> Light mode';
  } else {
    if (darkModeToggle) darkModeToggle.innerHTML = '<i class="fas fa-moon"></i> Dark mode';
  }
  if (darkModeToggle) darkModeToggle.addEventListener('click', (e) => {
    e.preventDefault();
    body.classList.toggle('dark-mode');
    if (body.classList.contains('dark-mode')) {
      localStorage.setItem('darkMode', 'enabled');
      darkModeToggle.innerHTML = '<i class="fas fa-sun"></i> Light mode';
    } else {
      localStorage.setItem('darkMode', 'disabled');
      darkModeToggle.innerHTML = '<i class="fas fa-moon"></i> Dark mode';
    }
  });

  fetchLabels().then(labels => {
    renderLabelList(labels);
    renderLabelFilter(labels);
    renderNoteLabelSelect();
  });
  renderNotes(); // Initial render of all notes


  if (filterLabelSelect) {
    filterLabelSelect.addEventListener("change", () => {
      renderNotes(filterLabelSelect.value);
    });
  }

  if (addLabelBtn) {
    addLabelBtn.addEventListener("click", addLabel);
  }

  if (modalEl) modalEl.addEventListener('hide.bs.modal', e => {
    // Reset fields to default for the next time it's opened for an owned note or new note
    if (editorEl) editorEl.readOnly = false;
    if (titleEl) {
      titleEl.contentEditable = true;
      titleEl.innerText = "New Untitled Note (Click to change title)";
    }
    if (editorEl) editorEl.value = "";
    if (noteId) noteId.value = "";
    if (img) { img.src = ""; img.style.display = "none"; }
    if (imgInput) imgInput.value = "";
    if (imgClear) imgClear.style.display = "none";
    if (imgDiv) imgDiv.style.display = "block";
    if (noteColor) { noteColor.value = "#FFFFFF"; noteColor.disabled = false; }
    if (saveBtn) saveBtn.style.display = 'inline-block';
    const imagesFileInput = document.getElementById('images');
    if (imagesFileInput) { imagesFileInput.style.display = 'block'; imagesFileInput.disabled = false; }
    if (noteLabelsSelect) noteLabelsSelect.innerHTML = "";

    imgChange = false;
    checkClear = false;
    if (saveStatus) saveStatus.textContent = "";

    renderNotes(filterLabelSelect ? filterLabelSelect.value : ''); // Refresh note list
  });

});

function viewSharedNoteModal(id, name, content, imagePath, color, ownerId) {
  if (titleEl) titleEl.innerText = name + (ownerId !== 'Unknown' ? ` (Shared by user ${ownerId})` : " (Shared)");
  if (editorEl) editorEl.value = content;
  if (noteColor) noteColor.value = color || '#FFFFFF';
  if (noteId) noteId.value = id;

  if (imagePath && imagePath !== 'null' && imagePath !== 'undefined' && imagePath.trim() !== '') {
    if (img) img.src = imagePath;
    if (img) img.style.display = "block";
    if (imgDiv) imgDiv.style.display = "none";
    if (imgClear) imgClear.style.display = "none";
  } else {
    if (img) img.src = "";
    if (img) img.style.display = "none";
    if (imgDiv) imgDiv.style.display = "block";
    if (imgClear) imgClear.style.display = "none";
  }

  // Make modal read-only
  if (editorEl) editorEl.readOnly = true;
  if (titleEl) titleEl.contentEditable = false;
  if (saveBtn) saveBtn.style.display = 'none';
  const imagesInput = document.getElementById('images');
  if (imagesInput) {
    imagesInput.style.display = 'none';
    imagesInput.disabled = true;
  }
  if (imgClear) imgClear.style.display = 'none';
  if (noteColor) noteColor.disabled = true;

  if (modal) modal.show();
}


function lockHandlerWrapper(event, noteIdParam) { // Parameter named 'event'
  if (event.target.closest('.note-actions') || event.target.closest('.note-labels')) return;
  unlockNote(noteIdParam);
}

function editNote(noteId, element) {
  element.closest('.note-card').click();
}

function darkenColor(color, percent) {
  let num = parseInt(color.replace("#", ""), 16),
    amt = Math.round(2.55 * percent),
    R = (num >> 16) - amt,
    G = (num >> 8 & 0x00FF) - amt,
    B = (num & 0x0000FF) - amt;
  return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 + (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 + (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
}

imgInput.addEventListener("change", function () {
  const file = this.files[0];
  imgChange = true;
  checkClear = false;
  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      img.src = e.target.result;
      img.style.display = "block";
      imgClear.setAttribute("style", "display: block");
      imgDiv.setAttribute("style", "display: none");
    };
    reader.readAsDataURL(file);
  } else {
    img.style.display = "none";
    img.src = "";
    imgClear.setAttribute("style", "display: none");
    imgDiv.setAttribute("style", "display: block");
  }
});


shareModalEl.addEventListener('show.bs.modal', function (event) {
  // Reset share option dropdown and visibility
  if (shareOptionSelect) shareOptionSelect.value = 'email'; // Default to email
  if (shareOptionSelect.value = 'email') {
    shareByEmailDiv.style.display = 'block';
    shareByUsernameDiv.style.display = 'none';
  } else {
    shareByEmailDiv.style.display = 'none';
    shareByUsernameDiv.style.display = 'block';
  }
});

// Biến để theo dõi trạng thái thông báo cho từng modal
let resetPasswordFeedback = false;
let deleteAccountFeedback = false;

// Reset password form handling
const resetPasswordForm = document.getElementById('resetPasswordForm');
const feedback = document.getElementById('feedback');
if (resetPasswordForm) {
  resetPasswordForm.addEventListener('submit', function (e) {
    e.preventDefault();

    if (resetPasswordFeedback) {
      const modal = bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal'));
      modal.hide();
      resetPasswordFeedback = false;
      feedback.innerHTML = '';
      document.getElementById('reset_current_password').value = '';
      return;
    }

    const currentPassword = document.getElementById('reset_current_password').value.trim();
    console.log('Password before sending (reset):', currentPassword);

    fetch('reset_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'current_password=' + encodeURIComponent(currentPassword)
    })
      .then(response => response.text())
      .then(data => {
        console.log('Response data:', data);
        feedback.innerHTML = data;
        resetPasswordFeedback = true;
      })
      .catch(error => {
        console.error('Fetch error:', error);
        feedback.innerHTML = '<div class="alert-danger">Lỗi: ' + error.message + '</div>';
        resetPasswordFeedback = true;
      });
  });
}

// Delete account form handling
const deleteAccountForm = document.getElementById('deleteAccountForm');
if (deleteAccountForm) {
  deleteAccountForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const modal = document.getElementById('deleteAccountModal');
    const deleteFeedback = modal.querySelector('#deleteFeedback');

    if (deleteAccountFeedback) {
      bootstrap.Modal.getInstance(modal).hide();
      deleteAccountFeedback = false;
      deleteFeedback.innerHTML = '';
      document.getElementById('delete_current_password').value = '';
      return;
    }

    const currentPassword = document.getElementById('delete_current_password').value.trim();
    console.log('Password before sending (delete):', currentPassword);

    fetch('delete_account.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'current_password=' + encodeURIComponent(currentPassword)
    })
      .then(response => response.text())
      .then(data => {
        console.log('Response data:', data);
        deleteFeedback.innerHTML = data;
        deleteAccountFeedback = true;
      })
      .catch(error => {
        console.error('Fetch error:', error);
        deleteFeedback.innerHTML = '<div class="alert-danger">Lỗi: ' + error.message + '</div>';
        deleteAccountFeedback = true;
      });
  });
}