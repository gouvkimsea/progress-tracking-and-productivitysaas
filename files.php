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
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'])) return '🖼️';
        if ($ext === 'pdf') return '📕';
        if (in_array($ext, ['doc', 'docx', 'txt', 'rtf'])) return '📝';
        if (in_array($ext, ['xls', 'xlsx', 'csv'])) return '📊';
        if (in_array($ext, ['zip', 'tar', 'gz', 'rar', '7z'])) return '📦';
        if (in_array($ext, ['js', 'php', 'py', 'html', 'css', 'json'])) return '💻';
        return '📄';
    }
}

$pageTitle = 'Mindrift — File Manager';
include __DIR__ . '/includes/head.php';
?>
<style>
  .files-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .files-title-wrap { display: flex; align-items: center; gap: 10px; }
  .files-title { margin: 0; font-size: 20px; font-weight: 800; color: #111827; }

  .upload-dropzone {
    border: 2px dashed #CBD5E1; border-radius: 12px; padding: 32px 20px; text-align: center;
    background: #FAFAFA; margin-bottom: 24px; transition: border-color 0.15s ease; cursor: pointer;
  }
  .upload-dropzone:hover { border-color: #0E65C7; background: #F0F6FE; }

  .files-grid-wrap { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
  .file-card {
    background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 10px; padding: 16px;
    display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.15s ease;
  }
  .file-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
  .file-icon { font-size: 32px; margin-bottom: 8px; }
  .file-name { font-size: 13.5px; font-weight: 700; color: #111827; margin: 0 0 4px; word-break: break-all; }
  .file-size { font-size: 11.5px; color: #6B7280; }

  [data-theme="dark"] .files-title { color: var(--ink); }
  [data-theme="dark"] .upload-dropzone { border-color: #334155; background: #111827; }
  [data-theme="dark"] .upload-dropzone:hover { border-color: #38BDF8; background: rgba(56, 189, 248, 0.08); }
  [data-theme="dark"] .file-card { background: #111827; border-color: #1F293D; }
  [data-theme="dark"] .file-name { color: #F9FAFB; }
  [data-theme="dark"] .file-size { color: #9CA3AF; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="files-page-header">
      <div class="files-title-wrap">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0E65C7" stroke-width="2.2"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        <h2 class="files-title">Project File Manager</h2>
      </div>
    </div>

    <!-- Upload Dropzone -->
    <div class="upload-dropzone" onclick="document.getElementById('fileUploadInput').click();">
      <div style="font-size:32px; margin-bottom:6px;">📁</div>
      <h3 style="margin:0 0 4px; font-size:15px; font-weight:700;">Click to upload document or drag &amp; drop</h3>
      <p style="margin:0; font-size:12.5px; color:var(--muted);">Supports PDF, DOCX, PNG, JPG, ZIP files</p>
      <input type="file" id="fileUploadInput" style="display:none;" onchange="uploadFile(this.files[0])" />
    </div>

    <!-- Files Grid -->
    <div class="files-grid-wrap">
      <?php if (empty($files)): ?>
        <div style="grid-column: 1 / -1; text-align:center; padding:48px 20px; color:var(--muted); background:var(--panel-bg); border:1px dashed var(--border); border-radius:12px;">
          <div style="font-size:36px; margin-bottom:8px;">📂</div>
          <div style="font-size:16px; font-weight:700; color:var(--ink); margin-bottom:4px;">No files uploaded yet</div>
          <div style="font-size:13px; margin-bottom:16px;">Upload your documentation, assets, and project files directly above.</div>
          <button type="button" class="btn-save" style="margin:0 auto; display:inline-flex;" onclick="document.getElementById('fileUploadInput').click();">+ Upload First File</button>
        </div>
      <?php else: ?>
        <?php foreach ($files as $f): 
          $sizeKb = round(($f['file_size'] ?? 0) / 1024, 1);
          $uploadDate = !empty($f['uploaded_at']) ? date('M j, Y', strtotime($f['uploaded_at'])) : 'Recent';
        ?>
          <div class="file-card" id="fileCard-<?= $f['id']; ?>">
            <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
              <div class="file-icon" style="font-size:28px;"><?= getFileIcon($f['file_name']); ?></div>
              <button type="button" title="Delete File" onclick="deleteFile(<?= $f['id']; ?>)" style="background:none; border:none; color:#EF4444; font-size:15px; cursor:pointer; padding:4px 8px; border-radius:4px; font-weight:bold; transition:all 0.15s ease;">✕</button>
            </div>
            <h4 class="file-name" title="<?= htmlspecialchars($f['file_name']); ?>" style="margin:8px 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%;"><?= htmlspecialchars($f['file_name']); ?></h4>
            <span class="file-size" style="color:var(--muted); font-size:11.5px;"><?= $sizeKb; ?> KB • <?= $uploadDate; ?></span>
            <div style="display:flex; gap:12px; margin-top:12px; align-items:center;">
              <a href="<?= htmlspecialchars($f['file_path']); ?>" download style="color:#0E65C7; font-size:12.5px; font-weight:700; text-decoration:none;">Download</a>
              <a href="<?= htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:var(--muted); font-size:12.5px; font-weight:600; text-decoration:none;">Preview ↗</a>
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
        showToast('File uploaded successfully!');
      }
      setTimeout(() => location.reload(), 400);
    } else {
      alert('Upload failed: ' + (data.message || 'Unknown error'));
    }
  } catch (err) {
    console.error(err);
    alert('Upload failed. Please check network or file format.');
  } finally {
    if (dropzoneH3) dropzoneH3.textContent = originalH3;
  }
}

async function deleteFile(fileId) {
  if (!confirm('Are you sure you want to delete this file? This cannot be undone.')) return;
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
        showToast('File deleted successfully');
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
