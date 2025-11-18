<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$username = $_SESSION['user'];
$basePath = "users/$username/";

$videos = glob($basePath . "videos/*.mp4");
$scheduleFile = $basePath . "schedule.json";
$schedules = file_exists($scheduleFile) ? json_decode(file_get_contents($scheduleFile), true) : [];

$status = [];
for ($i = 1; $i <= 2; $i++) {
  $file = $basePath . "status-$i.json";
  $status[$i] = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
}

$slotAvailable = null;
for ($i = 1; $i <= 2; $i++) {
  if (!$status[$i]) {
    $slotAvailable = $i;
    break;
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Streaming</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/theme.css?v=1" rel="stylesheet">
</head>
<body>
<div class="page-shell">
  <div class="container py-4">
    <div class="d-flex justify-content-end align-items-center mb-3">
      <a href="logout.php" class="logout-link">🚪 Logout</a>
    </div>

    <div class="glass-panel">
      <div class="text-center mb-4">
        <h2 class="section-title">👋 Hi, <?= ucfirst(htmlspecialchars($username)) ?></h2>
        <p class="section-subtitle">Kelola streaming multi-platform dengan kontrol penuh & informasi realtime.</p>
      </div>

      <!-- Stats -->
      <div class="stat-grid">
        <div class="stat-card" data-stat="cpu">
          <div class="stat-icon">🧠</div>
          <div>
            <div class="stat-meta">CPU Load</div>
            <div class="stat-value" id="cpuText">0%</div>
            <span class="stat-subvalue">Aktivitas prosesor 1 menit</span>
          </div>
        </div>
        <div class="stat-card" data-stat="disk">
          <div class="stat-icon">💾</div>
          <div>
            <div class="stat-meta">Storage Usage</div>
            <div class="stat-value" id="storageText">0%</div>
            <span class="stat-subvalue">Total ruang terpakai</span>
          </div>
        </div>
        <div class="stat-card" data-stat="ram">
          <div class="stat-icon">📶</div>
          <div>
            <div class="stat-meta">RAM Usage</div>
            <div class="stat-value" id="ramText">0%</div>
            <span class="stat-subvalue">Memori aktif</span>
          </div>
        </div>
        <div class="stat-card" data-stat="net">
          <div class="stat-icon">🌐</div>
          <div>
            <div class="stat-meta">Network I/O</div>
            <div class="stat-value" id="netRxText">↓ 0 MB/s</div>
            <span class="stat-subvalue" id="netTxText">↑ 0 MB/s</span>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs my-4 justify-content-center flex-wrap">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#streaming">Streaming</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#upload">Upload</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#gallery">Galeri</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#schedule">Jadwal</a></li>
      </ul>

      <div class="tab-content">
    <!-- Streaming Tab -->
    <div class="tab-pane fade show active" id="streaming">
      <div class="row g-4 mb-4">
        <?php foreach ([1, 2] as $slot): ?>
          <?php if ($status[$slot]): ?>
            <div class="col-md-6">
              <div class="card card-stream p-3 h-100">
                <h5>🟢 Streaming Aktif (Slot <?= $slot ?>)</h5>
                <ul class="list-group list-group-flush mt-3">
                  <li class="list-group-item">🎞️ <strong>Video:</strong> <?= basename($status[$slot]['video']) ?></li>
                  <li class="list-group-item">📐 <strong>Resolusi:</strong> <?= strtoupper($status[$slot]['quality']) ?></li>
                  <li class="list-group-item">⚙️ <strong>Encoder:</strong> <?= isset($status[$slot]['encoder_name']) ? htmlspecialchars($status[$slot]['encoder_name']) : (isset($status[$slot]['encoder_type']) && $status[$slot]['encoder_type'] === 'gpu' ? 'GPU (Hardware)' : 'CPU (Software)') ?></li>
                  <?php if (isset($status[$slot]['preset']) && $status[$slot]['preset']): ?>
                  <li class="list-group-item">🎛️ <strong>Preset:</strong> <?= strtoupper($status[$slot]['preset']) ?></li>
                  <?php endif; ?>
                  <?php if (isset($status[$slot]['duration_hours'])): ?>
                  <li class="list-group-item">⏱️ <strong>Durasi:</strong> <?= (int)$status[$slot]['duration_hours'] ?> jam</li>
                  <?php endif; ?>
                  <li class="list-group-item">🔁 <strong>Looping:</strong> <?= !empty($status[$slot]['loop']) ? 'Ya' : 'Tidak' ?></li>
                  <li class="list-group-item">🌐 <strong>Platform:</strong> <?= ucfirst($status[$slot]['platform']) ?></li>
                  <li class="list-group-item">🆔 <strong>PID:</strong> <?= $status[$slot]['pid'] ?? '-' ?></li>
                </ul>
                <a href="stop.php?slot=<?= $slot ?>" class="btn btn-danger btn-glow w-100 mt-3">⛔ Stop Slot <?= $slot ?></a>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($slotAvailable): ?>
        <div class="card card-stream p-4 shadow-sm">
          <h5 class="mb-3">✨ Mulai Streaming Baru</h5>
          <form action="stream.php" method="POST">
            <input type="hidden" name="slot" value="<?= $slotAvailable ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">🌐 Platform</label>
                <select name="platform" class="form-control" required>
                  <option value="youtube">YouTube</option>
                  <option value="facebook">Facebook Live</option>
                  <option value="twitch">Twitch</option>
                  <option value="custom">Custom RTMP</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">🔑 Stream Key / URL</label>
                <input type="text" name="stream_key" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">🎞️ Pilih Video</label>
                <select name="video_file" class="form-control">
                  <?php foreach ($videos as $f): ?>
                    <option value="<?= $f ?>"><?= basename($f) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">📶 Kualitas Streaming</label>
                <select name="quality" class="form-control">
                  <option value="low">Low (480p)</option>
                  <option value="medium" selected>Medium (720p)</option>
                  <option value="high">High (1080p)</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">⏱️ Durasi Streaming</label>
                <select name="duration_hours" class="form-control" required>
                  <?php for ($hours = 1; $hours <= 24; $hours++): ?>
                    <option value="<?= $hours ?>" <?= $hours === 1 ? 'selected' : '' ?>><?= $hours ?> jam</option>
                  <?php endfor; ?>
                </select>
                <small id="duration_warning" class="inline-warning">⛔ Streaming otomatis berhenti sesuai durasi</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">⚙️ Encoder</label>
                <select name="encoder_type" id="encoder_type_<?= $slotAvailable ?>" class="form-control">
                  <option value="cpu" selected>CPU (Software - libx264)</option>
                  <option value="gpu">GPU (Hardware - h264_v4l2m2m)</option>
                </select>
              </div>
              <div class="col-12" id="preset_container_<?= $slotAvailable ?>">
                <label class="form-label">🎛️ Preset (khusus CPU encoder)</label>
                <select name="preset" class="form-control">
                  <option value="ultrafast" selected>Ultrafast (tercepat, kualitas lebih rendah)</option>
                  <option value="superfast">Superfast</option>
                  <option value="veryfast">Veryfast</option>
                  <option value="faster">Faster</option>
                  <option value="fast">Fast</option>
                  <option value="medium">Medium (seimbang)</option>
                  <option value="slow">Slow</option>
                  <option value="slower">Slower</option>
                  <option value="veryslow">Veryslow (terlambat, kualitas terbaik)</option>
                </select>
                <small id="preset_warning" class="inline-warning">⛔ Preset tidak digunakan saat memilih encoder GPU</small>
              </div>
              <div class="col-12">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="looping" id="looping_<?= $slotAvailable ?>">
                  <label class="form-check-label" for="looping_<?= $slotAvailable ?>">🔁 Aktifkan Looping</label>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-success btn-glow w-100">🚀 Mulai Streaming Slot <?= $slotAvailable ?></button>
              </div>
            </div>
          </form>
        </div>
      <?php else: ?>
        <div class="alert alert-warning alert-neon text-center">⚠️ Semua slot sedang digunakan. Silakan hentikan salah satu dulu.</div>
      <?php endif; ?>
    </div>

    <!-- Upload Tab -->
<!-- Upload Tab -->
<div class="tab-pane fade" id="upload">
  <div class="card card-upload p-4">
    <h5 class="mb-3">⬆️ Unggah Video Baru</h5>
    <p class="text-muted">Format yang didukung: <span class="badge-chip chip-info">.mp4</span></p>
    <form id="uploadForm">
      <div class="mb-3">
        <label class="form-label">📁 Pilih file .mp4</label>
        <input type="file" name="video" accept="video/mp4" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-glow">Mulai Upload</button>

      <div class="progress upload-progress mt-3 d-none" id="uploadProgressContainer">
        <div class="progress-bar progress-bar-striped progress-bar-animated"
             role="progressbar" style="width: 0%" id="uploadProgressBar">0%</div>
      </div>
    </form>

    <div id="uploadStatus" class="mt-3 text-highlight"></div>
  </div>
</div>

    <!-- Gallery Tab -->
    <!-- Galeri Tab -->
<div class="tab-pane fade" id="gallery">
  <h5 class="mb-3">🎞️ Semua Video</h5>
  <div class="row g-4">
    <?php foreach ($videos as $f): 
      $sizeMB = round(filesize($f) / 1048576, 2); // 1 MB = 1048576 byte
    ?>
      <div class="col-md-4">
        <div class="card card-gallery video-card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0 text-truncate" title="<?= basename($f) ?>"><?= basename($f) ?></h6>
              <span class="badge-chip chip-info"><?= $sizeMB ?> MB</span>
            </div>
            <video src="<?= htmlspecialchars($f) ?>" controls class="w-100 mb-3" style="max-height: 200px;"></video>

            <!-- Edit Nama Video -->
            <form action="rename_video.php" method="POST" class="mb-2">
              <input type="hidden" name="old_name" value="<?= basename($f) ?>">
              <div class="input-group input-group-sm">
                <input type="text" name="new_name" class="form-control" placeholder="Nama baru.mp4" required>
                <button class="btn btn-warning btn-sm" type="submit">✏️ Ganti Nama</button>
              </div>
            </form>

            <!-- Tombol Hapus -->
            <form action="delete_video.php" method="POST" onsubmit="return confirm('Hapus video ini?')">
              <input type="hidden" name="file" value="<?= $f ?>">
              <button type="submit" class="btn btn-sm btn-danger w-100">🗑 Hapus</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

    <!-- Schedule Tab -->
    <div class="tab-pane fade" id="schedule">
      <div class="card card-schedule p-4 mb-4">
        <h5 class="mb-3">⏰ Tambah Jadwal Streaming</h5>
        <form action="schedule.php" method="POST">
          <input type="hidden" name="action" value="add">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Platform</label>
              <select name="platform" class="form-control" required>
                <option value="youtube">YouTube</option>
                <option value="facebook">Facebook</option>
                <option value="twitch">Twitch</option>
                <option value="custom">Custom RTMP</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Stream Key / URL</label>
              <input type="text" name="stream_key" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Waktu (YYYY-MM-DD HH:MM)</label>
              <input type="datetime-local" name="time" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Video</label>
              <select name="video_file" class="form-control">
                <?php foreach ($videos as $f): ?>
                  <option value="<?= $f ?>"><?= basename($f) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Kualitas</label>
              <select name="quality" class="form-control">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Durasi (jam)</label>
              <select name="duration_hours" class="form-control" required>
                <?php for ($hours = 1; $hours <= 24; $hours++): ?>
                  <option value="<?= $hours ?>" <?= $hours === 1 ? 'selected' : '' ?>><?= $hours ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">⚙️ Encoder</label>
              <select name="encoder_type" id="encoder_type_sched" class="form-control">
                <option value="cpu" selected>CPU (Software - libx264)</option>
                <option value="gpu">GPU (Hardware - h264_v4l2m2m)</option>
              </select>
            </div>
            <div class="col-md-8" id="preset_container_sched">
              <label class="form-label">🎛️ Preset (untuk CPU encoder)</label>
              <select name="preset" class="form-control">
                <option value="ultrafast" selected>Ultrafast (tercepat, kualitas lebih rendah)</option>
                <option value="superfast">Superfast</option>
                <option value="veryfast">Veryfast</option>
                <option value="faster">Faster</option>
                <option value="fast">Fast</option>
                <option value="medium">Medium (seimbang)</option>
                <option value="slow">Slow</option>
                <option value="slower">Slower</option>
                <option value="veryslow">Veryslow (terlambat, kualitas terbaik)</option>
              </select>
              <small class="inline-warning">⛔ Preset hanya berlaku untuk CPU encoder. GPU encoder tidak menggunakan preset.</small>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="looping" id="looping_sched">
                <label class="form-check-label" for="looping_sched">🔁 Aktifkan Loop</label>
              </div>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary btn-glow">🕒 Tambah Jadwal</button>
            </div>
          </div>
        </form>
      </div>

      <h5>📋 Jadwal Aktif</h5>
      <ul class="list-group mt-3">
        <?php if (count($schedules) == 0): ?>
          <li class="list-group-item text-muted">Belum ada jadwal.</li>
        <?php else: ?>
          <?php foreach ($schedules as $i => $sch): ?>
            <li class="list-group-item d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
              <div>
                <strong><?= $sch['time'] ?></strong>
                <div class="text-muted small"><?= basename($sch['video']) ?></div>
              </div>
              <div class="schedule-chip">
                <span><?= ucfirst($sch['platform'] ?? '-') ?></span>
                <span><?= $sch['loop'] ? 'Loop' : 'Sekali' ?></span>
                <span><?= strtoupper($sch['quality']) ?></span>
                <span><?= isset($sch['duration_hours']) ? (int)$sch['duration_hours'] : 1 ?> jam</span>
              </div>
              <a href="schedule.php?delete=<?= $i ?>" class="btn btn-sm btn-outline-danger btn-pill" title="Hapus jadwal">🗑</a>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
</div>
</div>

<script>
function updateStats() {
  fetch('stats.php')
    .then(res => res.json())
    .then(stats => {
      document.getElementById('cpuText').innerText = stats.cpu + '%';
      document.getElementById('storageText').innerText = stats.disk + '%';
      document.getElementById('ramText').innerText = stats.mem + '%';
      const netRx = stats.net && typeof stats.net.rx !== 'undefined' ? stats.net.rx : 0;
      const netTx = stats.net && typeof stats.net.tx !== 'undefined' ? stats.net.tx : 0;
      const netRxEl = document.getElementById('netRxText');
      const netTxEl = document.getElementById('netTxText');
      if (netRxEl) netRxEl.innerText = '↓ ' + netRx + ' MB/s';
      if (netTxEl) netTxEl.innerText = '↑ ' + netTx + ' MB/s';
    });
}
setInterval(updateStats, 10000);
updateStats();

// Toggle preset berdasarkan encoder type
function togglePreset(encoderSelectId, presetContainerId) {
  const encoderSelect = document.getElementById(encoderSelectId);
  const presetContainer = document.getElementById(presetContainerId);
  
  if (encoderSelect && presetContainer) {
    const updatePresetState = () => {
      if (encoderSelect.value === 'gpu') {
        presetContainer.style.opacity = '0.5';
        presetContainer.querySelector('select').disabled = true;
      } else {
        presetContainer.style.opacity = '1';
        presetContainer.querySelector('select').disabled = false;
      }
    };
    
    encoderSelect.addEventListener('change', updatePresetState);
    updatePresetState(); // Set initial state
  }
}

// Inisialisasi untuk form streaming
<?php if ($slotAvailable): ?>
togglePreset('encoder_type_<?= $slotAvailable ?>', 'preset_container_<?= $slotAvailable ?>');
<?php endif; ?>

// Inisialisasi untuk form schedule
togglePreset('encoder_type_sched', 'preset_container_sched');
</script>
<script>
document.getElementById('uploadForm').addEventListener('submit', function (e) {
  e.preventDefault();

  const fileInput = this.querySelector('input[name="video"]');
  const file = fileInput.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('video', file);

  const xhr = new XMLHttpRequest();
  const progressBar = document.getElementById('uploadProgressBar');
  const progressContainer = document.getElementById('uploadProgressContainer');
  const statusText = document.getElementById('uploadStatus');

  progressContainer.classList.remove('d-none');
  progressBar.style.width = '0%';
  progressBar.innerText = '0%';
  statusText.innerText = '⏳ Mengunggah...';

  xhr.upload.addEventListener('progress', function (e) {
    if (e.lengthComputable) {
      const percent = Math.round((e.loaded / e.total) * 100);
      progressBar.style.width = percent + '%';
      progressBar.innerText = percent + '%';
    }
  });

  xhr.onload = function () {
    if (xhr.status === 200) {
      progressBar.classList.remove('bg-danger');
      progressBar.classList.add('bg-success');
      statusText.innerText = '✅ Upload selesai! Memuat ulang halaman...';
      setTimeout(() => location.reload(), 1500); // refresh dalam 1.5 detik
    } else {
      progressBar.classList.remove('bg-success');
      progressBar.classList.add('bg-danger');
      statusText.innerText = '❌ Gagal upload!';
    }
  };

  xhr.onerror = function () {
    progressBar.classList.add('bg-danger');
    statusText.innerText = '⚠️ Terjadi kesalahan jaringan.';
  };

  xhr.open('POST', 'upload.php', true);
  xhr.send(formData);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>