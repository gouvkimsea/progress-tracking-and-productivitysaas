<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Fetch User Info
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = :uid");
$stmtUser->execute(['uid' => $userId]);
$user = $stmtUser->fetch();

// Fetch Uploaded Files from SQL Database
$stmtFiles = $db->prepare("SELECT * FROM files WHERE user_id = :uid ORDER BY id DESC");
$stmtFiles->execute(['uid' => $userId]);
$files = $stmtFiles->fetchAll();

if (!function_exists('getFileIcon')) {
    function getFileIcon(string $name): string {
        $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION)) ?: 'FILE';
        return '<span class="badge badge-neutral" style="font-size:10px; font-weight:700; padding:2px 6px;">' . htmlspecialchars($ext) . '</span>';
    }
}

$pageTitle = 'Mindrift — File Manager';
include __DIR__ . '/includes/head.php';
?>
<style>
  .files-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .files-title-wrap { display: flex; align-items: center; gap: 10px; }
  .files-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }

  .upload-dropzone {
    border: 1px dashed var(--border); border-radius: var(--radius-md); padding: 28px 20px; text-align: center;
    background: var(--panel-bg); margin-bottom: 20px; transition: border-color 0.15s ease; cursor: pointer;
  }
  .upload-dropzone:hover { border-color: var(--border-focus); }

  .files-grid-wrap { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
  .file-card {
    background: var(--panel-bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px;
    display: flex; flex-direction: column; align-items: center; text-align: center; transition: border-color 0.15s ease;
  }
  .file-card:hover { border-color: var(--border-focus); }
  .file-icon { margin-bottom: 8px; }
  .file-name { font-size: 13px; font-weight: 600; color: var(--ink); margin: 0 0 4px; word-break: break-all; }
  .file-size { font-size: 11px; color: var(--muted); }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="files-page-header">
      <div class="files-title-wrap">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        <h2 class="files-title">Files</h2>
      </div>
    </div>

    <!-- Upload Dropzone -->
    <div class="upload-dropzone" onclick="document.getElementById('fileUploadInput').click();">
      <div style="margin-bottom:8px; color:var(--muted);">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </div>
      <h3 style="margin:0 0 4px; font-size:14px; font-weight:600; color:var(--ink);">Upload a file or drag and drop</h3>
      <p style="margin:0; font-size:12px; color:var(--muted);">PDF, DOCX, PNG, JPG, ZIP</p>
      <input type="file" id="fileUploadInput" style="display:none;" onchange="uploadFile(this.files[0])" />
    </div>

    <!-- Files Grid -->
    <div class="files-grid-wrap">
      <?php if (empty($files)): ?>
        <div style="grid-column: 1 / -1; text-align:center; padding:36px 20px; color:var(--muted); background:var(--panel-bg); border:1px dashed var(--border); border-radius:8px;">
          <div style="font-size:14px; font-weight:600; color:var(--ink); margin-bottom:4px;">No files uploaded</div>
          <div style="font-size:12px; margin-bottom:14px;">Upload files to attach them to your workspace.</div>
          <button type="button" class="btn btn-secondary" style="margin:0 auto; display:inline-flex;" onclick="document.getElementById('fileUploadInput').click();">Upload File</button>
        </div>
      <?php else: ?>
        <?php foreach ($files as $f): 
          $sizeKb = round(($f['file_size'] ?? 0) / 1024, 1);
          $uploadDate = !empty($f['uploaded_at']) ? date('M j, Y', strtotime($f['uploaded_at'])) : 'Recent';
        ?>
          <div class="file-card" id="fileCard-<?= $f['id']; ?>">
            <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
              <div class="file-icon"><?= getFileIcon($f['file_name']); ?></div>
              <button type="button" title="Delete File" onclick="deleteFile(<?= $f['id']; ?>)" style="background:none; border:none; color:var(--muted); font-size:14px; cursor:pointer; padding:2px 6px; border-radius:4px;">✕</button>
            </div>
            <h4 class="file-name" title="<?= htmlspecialchars($f['file_name']); ?>" style="margin:6px 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%;"><?= htmlspecialchars($f['file_name']); ?></h4>
            <span class="file-size" style="color:var(--muted); font-size:11px;"><?= $sizeKb; ?> KB • <?= $uploadDate; ?></span>
            <div style="display:flex; gap:10px; margin-top:10px; align-items:center;">
              <a href="<?= htmlspecialchars($f['file_path']); ?>" download style="color:var(--blue); font-size:12px; font-weight:600; text-decoration:none;">Download</a>
              <a href="<?= htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:var(--muted); font-size:12px; font-weight:500; text-decoration:none;">Preview</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
const dropzone = document.querySelector('.upload-dropzone');
if (dropzone) {
  ['dragenter', 'dragover'].forEach(name => {
    dropzone.addEventListener(name, (e) => {
      e.preventDefault();
      dropzone.style.borderColor = '#0E65C7';
      dropzone.style.background = 'rgba(14, 101, 199, 0.08)';
    });
  });
  ['dragleave', 'drop'].forEach(name => {
    dropzone.addEventListener(name, (e) => {
      e.preventDefault();
      dropzone.style.borderColor = 'var(--border)';
      dropzone.style.background = 'var(--panel-bg)';
    });
  });
  dropzone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      uploadFile(e.dataTransfer.files[0]);
    }
  });
}

async function uploadFile(file) {
  if (!file) return;
  const formData = new FormData();
  formData.append('file', file);

  const dropzoneH3 = dropzone ? dropzone.querySelector('h3') : null;
  const originalH3 = dropzoneH3 ? dropzoneH3.textContent : '';
  if (dropzoneH3) dropzoneH3.textContent = '⏳ Uploading and encrypting file...';

  try {
    const res = await secureFetch('api/files.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') {
        showToast('File uploaded.');
      }
      setTimeout(() => location.reload(), 400);
    } else {
      alert('Upload failed: ' + (data.message || 'Unknown error'));
    }
  } catch (err) {
    console.error(err);
    alert('Upload failed. Check your file format and try again.');
  } finally {
    if (dropzoneH3) dropzoneH3.textContent = originalH3;
  }
}

async function deleteFile(fileId) {
  if (!confirm('Delete this file?')) return;
  try {
    const res = await secureFetch('api/files.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', file_id: fileId })
    });
    const data = await res.json();
    if (data.success) {
      const card = document.getElementById(`fileCard-${fileId}`);
      if (card) card.remove();
      if (typeof showToast === 'function') {
        showToast('File deleted.');
      }
    } else {
      alert('Failed to delete file: ' + (data.message || 'Unknown error'));
    }
  } catch (err) {
    console.error(err);
  }
}
</script>
</body>
</html>
