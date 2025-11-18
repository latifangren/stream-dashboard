# 🎥 Stream Dashboard

> Dashboard web berbasis PHP untuk mengelola streaming video live ke berbagai platform (YouTube, Facebook Live, Twitch, dan Custom RTMP) menggunakan FFmpeg. Aplikasi ini dirancang untuk berjalan di Termux (Android) atau Linux dengan kontrol penuh melalui antarmuka web yang modern dan responsif.

---

## 🧪 Perangkat yang Telah Diuji

| Perangkat | ROM/OS | Status GPU Encoding | Catatan | Chipset |
|-----------|--------|---------------------|---------|---------|
| Google Pixel 5 | A15 cr droid | ✅ **WORK** | Bug: Network IO tidak terbaca<br>Permission denied | Snapdragon 765G |
| SONY XZ2C | A10 Stock Rom | ✅ **WORK** | - | Snapdragon 845 |
| Xiaomi Redmi Note 5 | PostMarket OS (Alpine Linux) | ❌ **ERROR** | Kernel issue | Snapdragon 636 |

> **Catatan**: Semua perangkat diuji tanpa akses root.

## 📋 Daftar Isi

- [Quick Start](#-quick-start)
- [Fitur Utama](#-fitur-utama)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi](#-konfigurasi)
- [Cara Penggunaan](#-cara-penggunaan)
- [Struktur Proyek](#-struktur-proyek)
- [Troubleshooting](#-troubleshooting)
- [Lisensi](#-lisensi)

## 🚀 Quick Start

### Instalasi Cepat (3 Langkah)

```bash
# 1. Clone atau download project
git clone https://github.com/latifangren/stream-dashboard.git stream-dashboard
cd stream-dashboard

# 2. Jalankan script instalasi
chmod +x install.sh
./install.sh

# 3. Buat user pertama dan akses dashboard
# Via browser: http://[IP]:3100/add_user.php
# Atau via CLI (lihat bagian Instalasi)
```

---

### 📝 Informasi Platform

| Platform | Lokasi Instalasi | Port Default | Web Server |
|----------|------------------|--------------|------------|
| ✅ **Termux (Android)** | `~/stream-dashboard` | `3100` | PHP Built-in Server |
| ✅ **Alpine Linux / postmarketOS** | `~/stream-dashboard` | `3100` | PHP Built-in Server |
| ✅ **Debian / Ubuntu VPS** | `/opt/stream-dashboard` | `3100` | systemd Service |

---

### ⚙️ Konfigurasi Tambahan

#### 📤 Menaikkan Limit Upload PHP

Jika upload video gagal karena ukuran file terlalu besar, ikuti langkah berikut:

**1. Cek limit upload PHP saat ini:**
```bash
php -r "echo 'upload_max_filesize: '.ini_get('upload_max_filesize').PHP_EOL; echo 'post_max_size: '.ini_get('post_max_size').PHP_EOL;"
```

**2. Buat atau edit `php.ini` (Khusus Termux):**
```bash
# Salin file konfigurasi default
cp /data/data/com.termux/files/usr/etc/php/php.ini-development /data/data/com.termux/files/usr/etc/php/php.ini

# Atau buat manual
mkdir -p /data/data/com.termux/files/usr/etc/php
nano /data/data/com.termux/files/usr/etc/php/php.ini
```

> **Catatan**: Lokasi `php.ini` mungkin berbeda tergantung instalasi. Gunakan `php --ini` untuk menemukan lokasi yang tepat.

**3. Ubah atau tambahkan konfigurasi berikut:**
```ini
upload_max_filesize = 2G
post_max_size = 2G
memory_limit = 1G
max_execution_time = 300
```

**4. Verifikasi perubahan:**
```bash
php -r "echo 'upload_max_filesize: '.ini_get('upload_max_filesize').PHP_EOL; echo 'post_max_size: '.ini_get('post_max_size').PHP_EOL;"
```

---

#### ⏰ Setup Cronjob untuk Termux

**1. Install Cronie:**
```bash
pkg install cronie termux-services
# Atau
pkg install cronie
```

**2. Enable cron service:**
```bash
sv-enable crond
```

**3. Edit crontab:**
```bash
crontab -e
```

**4. Tambahkan baris berikut (cek setiap menit):**

**Untuk Termux/Alpine:**
```bash
* * * * * PATH=$PATH:/usr/local/bin:/usr/bin:/bin && cd '/data/data/com.termux/files/home/stream-dashboard' && '/data/data/com.termux/files/usr/bin/php' run_schedule.php >> '/data/data/com.termux/files/home/stream-dashboard/cron_output.log' 2>&1
```

**Untuk VPS:**
```bash
* * * * * cd /opt/stream-dashboard && php run_schedule.php > /dev/null 2>&1
```

---

#### 🌐 Setup Fix Network Dashboard

Untuk beberapa versi Android/ROM tertentu, mungkin memerlukan akses root untuk membaca statistik network.

**1. Install iproute2 (Termux):**
```bash
pkg install iproute2
```

**2. Cek manual via Termux:**
```bash
ip -s link
```

## ✨ Fitur Utama

### 🎬 Streaming Multi-Platform

| Platform | Deskripsi |
|----------|-----------|
| **YouTube Live** | Streaming langsung ke YouTube |
| **Facebook Live** | Streaming ke Facebook Live |
| **Twitch** | Streaming ke platform Twitch |
| **Custom RTMP** | Streaming ke server RTMP custom |

### 🚀 Fitur Streaming

- ✅ **Dual Slot Streaming** - Jalankan hingga 2 streaming secara bersamaan
- 🔄 **Video Looping** - Putar video secara berulang tanpa batas
- 🎯 **Kualitas Disesuaikan** - Pilih kualitas Low (480p), Medium (720p), atau High (1080p)
- ⚡ **Hardware/Software Encoding** - Dukungan encoder CPU (libx264) dan GPU (hardware acceleration)
- 🎛️ **Preset Encoding** - Kontrol preset encoding untuk kualitas dan performa
- ⏱️ **Durasi Streaming** - Set durasi streaming otomatis (1-24 jam)

### 📅 Penjadwalan

- 🤖 **Auto Start** - Jadwalkan streaming untuk dimulai secara otomatis
- 📋 **Multiple Schedules** - Buat beberapa jadwal sekaligus
- ⏰ **Cron Integration** - Jalankan `run_schedule.php` via cron untuk auto-start
- 🔄 **Schedule Daemon** - Alternatif daemon process untuk Termux (jika cron tidak tersedia)
- ⏳ **Toleransi Waktu** - Jadwal akan dieksekusi jika terlewat maksimal 5 menit atau akan datang dalam 1 menit
- 🛠️ **Setup Otomatis** - Setup cron via web interface tanpa perlu akses terminal

### 📊 Monitoring Real-time

- 💻 **CPU Usage** - Monitor penggunaan CPU
- 🧠 **RAM Usage** - Monitor penggunaan memori
- 💾 **Disk Usage** - Monitor penggunaan storage
- 🌐 **Network I/O** - Monitor kecepatan upload/download

### 🎞️ Manajemen Video

- 📤 **Upload Video** - Upload file video MP4 melalui web interface
- 🖼️ **Galeri Video** - Lihat semua video yang tersedia
- ✏️ **Rename Video** - Ubah nama file video
- 🗑️ **Delete Video** - Hapus video yang tidak diperlukan

### 👥 Multi-User System

- 👤 **User Management** - Sistem multi-user dengan isolasi data per user
- 🔐 **Secure Login** - Autentikasi berbasis password hash
- 🔒 **User Isolation** - Setiap user memiliki folder dan data terpisah

## 🔧 Persyaratan Sistem

### 💻 Software yang Diperlukan

| Komponen | Versi | Keterangan |
|----------|-------|------------|
| **PHP** | 7.4+ | Dengan ekstensi: `posix`, `json`, `session` |
| **FFmpeg** | Latest | Dengan dukungan encoding H.264 |
| **Web Server** | - | Apache/Nginx atau PHP built-in server |
| **Cron** | - | Opsional - Untuk auto-start scheduled streams |
| **Schedule Daemon** | - | Opsional - Alternatif untuk cron (Termux) |

#### Ekstensi PHP yang Diperlukan

- `posix` - Untuk manajemen proses
- `json` - Untuk data storage
- `session` - Untuk autentikasi

### 🖥️ Hardware (Rekomendasi)

| Komponen | Minimum | Direkomendasikan |
|----------|---------|------------------|
| **Platform** | Android 7.0+ (Termux) | Linux Server (Ubuntu/Debian/CentOS) |
| **RAM** | 2GB | 4GB+ (untuk dual streaming) |
| **Storage** | Tergantung ukuran video | SSD direkomendasikan |

### 🎬 Encoder Support

Aplikasi ini mendukung berbagai hardware encoder untuk performa optimal:

#### CPU Encoder
- **libx264** - Software encoding (universal support)

#### GPU/Hardware Encoders

| Encoder | Platform | Deskripsi |
|---------|----------|-----------|
| `h264_mediacodec` | Android/Termux | Android MediaCodec |
| `h264_vulkan` | Multi-platform | Vulkan H.264 |
| `h264_nvenc` | NVIDIA GPU | NVIDIA NVENC |
| `h264_qsv` | Intel | Intel Quick Sync |
| `h264_v4l2m2m` | Raspberry Pi/Android | V4L2 M2M |
| `h264_omx` | Raspberry Pi | OpenMAX |
| `h264_videotoolbox` | macOS | VideoToolbox |

## 📦 Instalasi

### 🚀 Metode Instalasi Otomatis (Disarankan)

Aplikasi ini menyediakan script instalasi otomatis yang mendukung berbagai platform. Script akan secara otomatis:

- ✅ Mendeteksi platform (Termux, Alpine, atau VPS)
- 📦 Menginstall dependencies yang diperlukan
- 📁 Menyalin file ke lokasi instalasi
- 🌐 Setup web server (untuk VPS: systemd service)
- 🔐 Membuat folder dan permission yang diperlukan

---

### 1️⃣ Download atau Clone Project

```bash
# Clone repository (jika menggunakan git)
git clone https://github.com/latifangren/stream-dashboard.git stream-dashboard
cd stream-dashboard

# Atau download dan extract project ke folder stream-dashboard
```

---

### 2️⃣ Jalankan Script Instalasi

```bash
# Berikan permission execute
chmod +x install.sh

# Jalankan instalasi
./install.sh
```

**Script akan secara otomatis melakukan:**

| Tugas | Deskripsi |
|-------|-----------|
| 🔍 **Deteksi Platform** | Termux/Android, Alpine/postmarketOS, atau VPS Debian/Ubuntu |
| 📦 **Install Dependencies** | PHP, FFmpeg, dan komponen lainnya |
| 📁 **Copy Files** | Menyalin file ke lokasi instalasi |
| 🌐 **Setup Web Server** | Konfigurasi sesuai platform |

**Lokasi Instalasi per Platform:**

| Platform | Lokasi | Web Server |
|----------|--------|------------|
| **Termux** | `~/stream-dashboard` | PHP Built-in Server |
| **Alpine/postmarketOS** | `~/stream-dashboard` | PHP Built-in Server |
| **VPS Debian/Ubuntu** | `/opt/stream-dashboard` | systemd Service |

**Port Default:** `3100`

### 3️⃣ Akses Dashboard

Setelah instalasi selesai, akses dashboard sesuai platform:

#### 🖥️ Untuk VPS (Debian/Ubuntu)

```bash
# Service sudah otomatis berjalan
# Akses via: http://[IP_SERVER]:3100
```

#### 📱 Untuk Termux

```bash
cd ~/stream-dashboard
php -S 0.0.0.0:3100

# Akses via: http://localhost:3100
# Atau dari device lain: http://[IP_TERMUX]:3100
```

#### 🐧 Untuk Alpine/postmarketOS

```bash
cd ~/stream-dashboard
php -S 0.0.0.0:3100

# Akses via: http://localhost:3100
```

---

### 4️⃣ Buat User Pertama

Setelah dashboard dapat diakses, buat user pertama:

#### 🌐 Via Browser (Disarankan)

1. Akses: `http://[IP]:3100/add_user.php`
2. Isi username dan password
3. Klik **"Tambah Pengguna"**

#### 💻 Via Command Line

```bash
# Masuk ke directory instalasi
cd ~/stream-dashboard  # atau /opt/stream-dashboard untuk VPS

# Buat user via PHP
php -r "
\$users = ['admin' => ['password' => password_hash('password123', PASSWORD_DEFAULT), 'created' => date('Y-m-d H:i:s')]];
file_put_contents('users.json', json_encode(\$users, JSON_PRETTY_PRINT));
mkdir('users/admin/videos', 0755, true);
file_put_contents('users/admin/schedule.json', '[]');
echo 'User admin created with password: password123\n';
"
```

> **⚠️ PENTING**: Ganti password default setelah login pertama kali!

### 5️⃣ Setup Auto-Schedule (Cron atau Daemon)

Aplikasi mendukung tiga metode untuk menjalankan scheduled streams secara otomatis:

#### 🌐 Metode 1: Setup Cron via Web Interface (Disarankan)

1. Login ke dashboard
2. Buka tab **Schedule**
3. Scroll ke bagian **System & Cron Status**
4. Jika cron belum terpasang, klik tombol **⚙️ Setup Cron Otomatis**
5. Jika cron sudah terpasang tapi tidak aktif, klik **🔧 Update/Repair Cron** untuk memperbaiki

**✅ Keuntungan:**
- 🎯 Setup otomatis dengan absolute path yang benar
- 🖥️ Tidak perlu akses terminal/SSH
- 🔧 Otomatis mendeteksi dan memperbaiki path yang salah

---

#### 🔄 Metode 2: Schedule Daemon (Alternatif untuk Termux)

Jika cron tidak berjalan di Termux, gunakan daemon sebagai alternatif:

1. Login ke dashboard
2. Buka tab **Schedule**
3. Scroll ke bagian **🔄 Schedule Daemon (Alternatif untuk Cron)**
4. Klik tombol **▶️ Start Daemon**
5. Daemon akan berjalan di background dan mengeksekusi jadwal setiap menit

**✅ Keuntungan Daemon:**
- 🔌 Tidak bergantung pada cron service
- 📱 Cocok untuk Termux yang mungkin tidak memiliki cron aktif
- 🎛️ Mudah dikontrol via web interface
- 📝 Log tersedia di `schedule_daemon.log`

---

#### 💻 Metode 3: Setup Cron Manual (Alternatif)

Jika ingin setup manual via terminal:

**1. Edit crontab:**
```bash
crontab -e
```

**2. Tambahkan baris berikut (cek setiap menit):**

**Untuk Termux/Alpine:**
```bash
* * * * * PATH=$PATH:/usr/local/bin:/usr/bin:/bin && cd '/data/data/com.termux/files/home/stream-dashboard' && '/data/data/com.termux/files/usr/bin/php' run_schedule.php >> '/data/data/com.termux/files/home/stream-dashboard/cron_output.log' 2>&1
```

**Untuk VPS:**
```bash
* * * * * cd /opt/stream-dashboard && php run_schedule.php > /dev/null 2>&1
```

> **📌 Catatan**: 
> - Pastikan path absolut sesuai dengan lokasi instalasi
> - Pastikan PHP tersedia di PATH
> - Untuk VPS, pastikan user yang menjalankan cron memiliki akses ke directory instalasi
> - Gunakan **Setup Cron via Web Interface** untuk memastikan path yang benar

---

### 6️⃣ Uninstall (Jika Diperlukan)

Untuk menghapus instalasi:

```bash
# Masuk ke directory project (jika masih ada)
cd stream-dashboard

# Jalankan script uninstall
chmod +x uninstall.sh
./uninstall.sh
```

**Script akan melakukan:**
- 🛑 Menghentikan service (untuk VPS)
- 🗑️ Menghapus file instalasi
- 🔧 Menghapus systemd service (untuk VPS)

---

## ⚙️ Konfigurasi

### 🌐 Konfigurasi Platform Streaming

#### 📺 YouTube Live

1. Buka [YouTube Studio](https://studio.youtube.com)
2. Pilih **Go Live** → **Stream**
3. Salin **Stream Key** yang diberikan
4. Masukkan stream key di dashboard

#### 📘 Facebook Live

1. Buka [Facebook Live](https://www.facebook.com/live/create)
2. Salin **Stream Key** dari pengaturan
3. Masukkan stream key di dashboard

#### 🟣 Twitch

1. Buka [Twitch Dashboard](https://dashboard.twitch.tv/settings/stream)
2. Salin **Primary Stream Key**
3. Masukkan stream key di dashboard

#### 🔗 Custom RTMP

Masukkan URL RTMP lengkap, contoh:

```
rtmp://your-server.com:1935/live/stream_key
```

---

### 🎛️ Konfigurasi Encoder

#### 💻 CPU Encoder (libx264)

| Preset | Kecepatan | Kualitas | Rekomendasi |
|--------|-----------|----------|-------------|
| `ultrafast` | ⚡ Tercepat | ⭐ Rendah | Perangkat lemah |
| `veryfast` | ⚡⚡ Cepat | ⭐⭐ Baik | ✅ **Disarankan** |
| `medium` | ⚡⚡⚡ Sedang | ⭐⭐⭐ Seimbang | Keseimbangan |
| `veryslow` | 🐌 Terlambat | ⭐⭐⭐⭐⭐ Terbaik | Kualitas maksimal |

#### 🎮 GPU Encoder (Hardware)

- ✅ Otomatis mendeteksi hardware encoder yang tersedia
- ⚙️ Tidak menggunakan preset (hardware encoder memiliki preset sendiri)
- 🚀 Lebih efisien untuk perangkat yang mendukung

---

### 📊 Konfigurasi Kualitas

| Kualitas | Resolusi | Bitrate | Bandwidth | Rekomendasi |
|----------|----------|---------|-----------|-------------|
| **Low** | 480p | 1000k | ~1 Mbps | Koneksi lambat |
| **Medium** | 720p | 2000k | ~2 Mbps | ✅ **Disarankan** |
| **High** | 1080p | 6000k | ~6 Mbps | Koneksi cepat |

## 📖 Cara Penggunaan

### 1️⃣ Login ke Dashboard

1. Buka browser dan akses URL dashboard (contoh: `http://localhost:3100`)
2. Masukkan username dan password
3. Klik **Login**

---

### 2️⃣ Upload Video

1. Klik tab **Upload**
2. Pilih file video MP4
3. Klik **Mulai Upload**
4. Tunggu hingga upload selesai
5. Video akan muncul di tab **Galeri**

---

### 3️⃣ Mulai Streaming

1. Klik tab **Streaming**
2. Pastikan ada slot tersedia (maksimal 2 streaming bersamaan)
3. Isi form streaming:
   - **Platform**: Pilih platform tujuan
   - **Stream Key/URL**: Masukkan stream key atau URL RTMP
   - **Pilih Video**: Pilih video dari dropdown
   - **Kualitas**: Pilih kualitas streaming
   - **Durasi**: Set durasi streaming (1-24 jam)
   - **Encoder**: Pilih CPU atau GPU encoder
   - **Preset**: Pilih preset (hanya untuk CPU encoder)
   - **Looping**: Centang jika ingin video di-loop
4. Klik **Mulai Streaming**
5. Status streaming akan muncul di dashboard

---

### 4️⃣ Menghentikan Streaming

1. Di tab **Streaming**, cari slot yang aktif
2. Klik tombol **Stop Slot X**
3. Streaming akan dihentikan

---

### 5️⃣ Menjadwalkan Streaming

1. Klik tab **Jadwal**
2. Isi form jadwal:
   - **Platform**: Pilih platform
   - **Stream Key/URL**: Masukkan stream key
   - **Waktu**: Pilih tanggal dan waktu mulai (format: `YYYY-MM-DD HH:MM`)
   - **Video**: Pilih video
   - **Kualitas**: Pilih kualitas
   - **Durasi**: Set durasi (1-24 jam)
   - **Encoder & Preset**: Konfigurasi encoder
   - **Loop**: Aktifkan jika perlu
3. Klik **Tambah Jadwal**
4. Setup auto-start:
   - **Via Web**: Klik **⚙️ Setup Cron Otomatis** di bagian System & Cron Status
   - **Atau**: Klik **▶️ Start Daemon** untuk menggunakan daemon (alternatif cron)
   - **Atau**: Setup cron manual via terminal (lihat bagian Instalasi)

> **📌 Catatan Penting**:
> - Jadwal akan otomatis dieksekusi jika waktunya sesuai (toleransi: -5 menit sampai +1 menit)
> - Setelah dieksekusi, jadwal akan otomatis dihapus dari queue
> - Gunakan tombol **🧪 Test Run** untuk test eksekusi jadwal secara manual

---

### 6️⃣ Mengelola Video

#### ✏️ Rename Video

1. Buka tab **Galeri**
2. Masukkan nama baru di form rename
3. Klik **Ganti Nama**

#### 🗑️ Delete Video

1. Buka tab **Galeri**
2. Klik **Hapus** pada video yang ingin dihapus
3. Konfirmasi penghapusan

---

### 7️⃣ Monitoring

Dashboard menampilkan statistik real-time:

| Metrik | Update | Deskripsi |
|--------|--------|-----------|
| **CPU Load** | Setiap 10 detik | Penggunaan CPU |
| **RAM Usage** | Real-time | Penggunaan memori |
| **Storage Usage** | Real-time | Penggunaan disk |
| **Network I/O** | Real-time | Kecepatan upload/download |

## 📁 Struktur Proyek

```
stream-dashboard/
├── index.php              # Dashboard utama
├── login.php              # Halaman login
├── logout.php             # Logout handler
├── add_user.php           # Tambah user baru
├── stream.php             # Handler untuk memulai streaming
├── stop.php               # Handler untuk menghentikan streaming
├── upload.php             # Handler untuk upload video
├── delete_video.php       # Handler untuk hapus video
├── rename_video.php       # Handler untuk rename video
├── schedule.php           # Handler untuk manajemen jadwal
├── run_schedule.php       # Script untuk menjalankan scheduled streams (via cron/daemon)
├── schedule_daemon.php    # Daemon process alternatif untuk cron (Termux)
├── daemon_control.php     # API untuk kontrol daemon (start/stop/status)
├── setup_cron.php         # API untuk setup cron otomatis via web
├── cron_status.php        # API untuk status cron dan daemon
├── check_cron.php         # Script CLI untuk cek dan setup cron
├── test_run_schedule.php  # API untuk test run schedule via web
├── stats.php              # API untuk statistik sistem
├── install.sh             # Script instalasi otomatis
├── uninstall.sh           # Script uninstall
├── users.json             # Database user (JSON)
├── schedule_run.log       # Log eksekusi schedule (dibuat otomatis)
├── schedule_daemon.log     # Log daemon process (dibuat saat daemon berjalan)
├── schedule_daemon.pid     # PID file daemon (dibuat saat daemon berjalan)
├── cron_output.log        # Output cron job (jika menggunakan cron)
├── assets/
│   └── theme.css          # Stylesheet tema
├── users/                 # Folder data user (dibuat otomatis saat instalasi)
│   └── [username]/
│       ├── videos/        # Folder video user
│       ├── schedule.json  # File jadwal user
│       ├── status-1.json # Status streaming slot 1
│       ├── status-2.json # Status streaming slot 2
│       ├── log-1.txt     # Log streaming slot 1
│       ├── log-2.txt     # Log streaming slot 2
│       └── debug.txt      # Debug log
└── README.md              # Dokumentasi ini
```

## 🔍 Troubleshooting

### Streaming Tidak Berjalan

1. **Cek FFmpeg terinstall**:
   ```bash
   ffmpeg -version
   ```

2. **Cek log file**:
   ```bash
   cat users/[username]/log-1.txt
   cat users/[username]/debug.txt
   ```

3. **Cek stream key valid**:
   - Pastikan stream key tidak expired
   - Untuk YouTube, stream key berubah setiap kali membuat event baru

4. **Cek koneksi internet**:
   - Pastikan koneksi stabil untuk streaming
   - Test dengan `ping` atau `curl`

### Encoder GPU Tidak Terdeteksi

1. **Cek encoder yang tersedia**:
   ```bash
   ffmpeg -hide_banner -encoders | grep h264
   ```

2. **Untuk Android/Termux**:
   - Pastikan menggunakan `h264_mediacodec`
   - Beberapa perangkat Android tidak mendukung hardware encoding

3. **Fallback ke CPU**:
   - Jika GPU tidak tersedia, aplikasi akan otomatis menggunakan CPU encoder

### Scheduled Stream Tidak Berjalan

1. **Cek status cron/daemon via web**:
   - Login ke dashboard
   - Buka tab **Schedule**
   - Scroll ke bagian **System & Cron Status**
   - Cek status cron atau daemon
   - Jika cron tidak aktif, klik **🔧 Update/Repair Cron**
   - Jika daemon tidak berjalan, klik **▶️ Start Daemon**

2. **Cek cron job (jika menggunakan cron)**:
   ```bash
   crontab -l
   ```

3. **Cek daemon (jika menggunakan daemon)**:
   ```bash
   # Cek apakah daemon berjalan
   cat schedule_daemon.pid
   ps -p $(cat schedule_daemon.pid)
   
   # Cek log daemon
   tail -f schedule_daemon.log
   ```

4. **Test manual**:
   ```bash
   php run_schedule.php
   ```
   Atau via web: Klik tombol **🧪 Test Run** di dashboard

5. **Cek log schedule**:
   ```bash
   tail -f schedule_run.log
   ```

6. **Cek timezone**:
   - Pastikan timezone di `run_schedule.php` sesuai dengan lokasi
   - Default: `Asia/Jakarta`

7. **Cek format waktu jadwal**:
   - Format: `YYYY-MM-DD HH:MM` (contoh: `2024-12-25 14:30`)
   - Jadwal akan dieksekusi jika waktunya sesuai (toleransi: -5 menit sampai +1 menit)

8. **Jika cron tidak berjalan di Termux**:
   - Gunakan **Schedule Daemon** sebagai alternatif
   - Klik **▶️ Start Daemon** di dashboard
   - Daemon akan berjalan di background dan mengeksekusi jadwal setiap menit

### Permission Denied

1. **Cek permission folder users**:
   ```bash
   chmod 755 users
   chmod -R 755 users/*
   ```

2. **Cek ownership** (jika menggunakan web server):
   ```bash
   sudo chown -R www-data:www-data users/
   ```

### Video Upload Gagal

1. **Cek ukuran file**:
   - Pastikan tidak melebihi `upload_max_filesize` dan `post_max_size` di PHP
   - Edit `php.ini`:
     ```ini
     upload_max_filesize = 500M
     post_max_size = 500M
     ```

2. **Cek permission folder videos**:
   ```bash
   chmod 755 users/[username]/videos
   ```

### Dashboard Tidak Bisa Diakses

1. **Untuk VPS (systemd service)**:
   ```bash
   # Cek status service
   sudo systemctl status stream-dashboard
   
   # Jika service tidak berjalan, start service
   sudo systemctl start stream-dashboard
   
   # Cek log service
   sudo journalctl -u stream-dashboard -f
   ```

2. **Untuk Termux/Alpine (PHP built-in server)**:
   ```bash
   # Pastikan server berjalan
   cd ~/stream-dashboard
   php -S 0.0.0.0:3100
   
   # Pastikan terminal masih terbuka
   # Gunakan screen/tmux untuk background process
   ```

3. **Cek firewall**:
   ```bash
   # Ubuntu/Debian (VPS)
   sudo ufw allow 3100/tcp
   sudo ufw status
   
   # Termux: Pastikan tidak ada firewall yang memblokir
   ```

4. **Cek port sudah digunakan**:
   ```bash
   # Cek apakah port 3100 sudah digunakan
   netstat -tuln | grep 3100
   # atau
   lsof -i :3100
   ```

5. **Cek error log**:
   ```bash
   # Untuk VPS (systemd)
   sudo journalctl -u stream-dashboard -n 50
   
   # Untuk PHP built-in server
   # Error akan muncul di terminal
   ```

### Masalah Instalasi

1. **Script install.sh gagal**:
   ```bash
   # Pastikan script memiliki permission execute
   chmod +x install.sh
   
   # Jalankan dengan bash explicit
   bash install.sh
   
   # Cek apakah index.php ada di directory saat ini
   ls -la index.php
   ```

2. **File tidak ter-copy dengan benar**:
   ```bash
   # Cek apakah rsync tersedia
   which rsync
   
   # Jika tidak ada, install rsync atau script akan menggunakan fallback
   # Termux: pkg install rsync
   # Alpine: apk add rsync
   # VPS: sudo apt install rsync
   ```

3. **Permission denied saat instalasi**:
   ```bash
   # Pastikan user memiliki permission untuk menulis ke lokasi instalasi
   # Untuk VPS, pastikan menggunakan sudo
   # Untuk Termux/Alpine, pastikan di HOME directory
   ```

4. **Service systemd tidak berjalan (VPS)**:
   ```bash
   # Cek status
   sudo systemctl status stream-dashboard
   
   # Reload daemon
   sudo systemctl daemon-reload
   
   # Enable dan start service
   sudo systemctl enable stream-dashboard
   sudo systemctl start stream-dashboard
   
   # Cek log untuk error
   sudo journalctl -u stream-dashboard -n 100
   ```

## 📝 Catatan Penting

### 🔒 Keamanan

- ⚠️ Jangan expose dashboard ke internet tanpa autentikasi yang kuat
- 🔐 Gunakan HTTPS untuk production
- 🔑 Ganti password default setelah instalasi
- 🛡️ Pertimbangkan menggunakan firewall

### ⚡ Performance

- 💻 Dual streaming membutuhkan resource yang cukup
- 📊 Monitor CPU dan RAM usage secara berkala
- 🎮 Gunakan hardware encoder jika tersedia untuk performa lebih baik

### 💾 Storage

- 📁 Video yang di-upload akan memakan storage
- 🗑️ Pertimbangkan untuk menghapus video yang tidak digunakan
- 📊 Monitor disk usage secara berkala

### 🌐 Network

- 📡 Streaming membutuhkan bandwidth yang stabil
- ⚡ Pastikan upload speed mencukupi untuk kualitas yang dipilih

| Kualitas | Bandwidth Minimum |
|----------|-------------------|
| **Low** | ~1 Mbps |
| **Medium** | ~2 Mbps |
| **High** | ~6 Mbps |

## 📄 Lisensi

Lihat file [LICENSE](LICENSE) untuk informasi lisensi lengkap.

---

## 🤝 Kontribusi

Kontribusi sangat diterima! Silakan:

- 🐛 Buat [issue](https://github.com/latifangren/stream-dashboard/issues) untuk melaporkan bug atau meminta fitur
- 🔀 Buat [pull request](https://github.com/latifangren/stream-dashboard/pulls) untuk berkontribusi kode
- 📖 Perbaiki dokumentasi jika menemukan kesalahan

---

## 📧 Support

Jika mengalami masalah atau memiliki pertanyaan:

- 📝 Buat [issue](https://github.com/latifangren/stream-dashboard/issues) di repository
- 💬 Diskusikan di [Discussions](https://github.com/latifangren/stream-dashboard/discussions)
- 📧 Hubungi maintainer

---

<div align="center">

**Dibuat dengan ❤️ untuk streaming yang lebih mudah**

⭐ Jika project ini membantu Anda, pertimbangkan untuk memberikan star!

</div>
