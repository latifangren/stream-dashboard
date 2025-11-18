#!/bin/bash

clear
cat << "EOF"

📦 Stream Dashboard Installer
📍 Versi: Instalasi Langsung dari Directory (Tanpa ZIP)
🚀 Port Default: 3100

EOF

echo "🔍 Mendeteksi sistem..."

# Simpan directory source (dimana script ini dijalankan)
SOURCE_DIR=$(cd "$(dirname "$0")" && pwd)

# Deteksi OS
OS_ID=""
if [ -f /etc/os-release ]; then
  OS_ID=$(grep '^ID=' /etc/os-release | cut -d= -f2 | tr -d '"')
fi

PLATFORM=""

if echo "$OS_ID" | grep -qi "android"; then
  PLATFORM="termux"
elif echo "$OS_ID" | grep -qi "alpine"; then
  PLATFORM="alpine"
elif [ "$OS_ID" = "debian" ] || [ "$OS_ID" = "ubuntu" ]; then
  PLATFORM="vps"
else
  echo "❌ Tidak dapat mendeteksi platform secara otomatis."
  echo "Silakan pilih:"
  echo "1) Termux"
  echo "2) VPS / Linux"
  echo "3) Alpine / postmarketOS"
  echo -n "Pilih platform (1/2/3): "
  read input
  case "$input" in
    1) PLATFORM="termux" ;;
    2) PLATFORM="vps" ;;
    3) PLATFORM="alpine" ;;
    *) PLATFORM="vps" ;;
  esac
fi

APP_DIR="stream-dashboard"
PORT="3100"

# Fungsi untuk konfigurasi PHP
configure_php() {
  local platform=$1
  echo "⚙️ Mengonfigurasi PHP settings..."
  
  PHP_INI=""
  PHP_INI_DIR=""
  
  case "$platform" in
    termux)
      PHP_INI_DIR="/data/data/com.termux/files/usr/etc/php"
      PHP_INI="$PHP_INI_DIR/php.ini"
      PHP_INI_DEV="$PHP_INI_DIR/php.ini-development"
      ;;
    alpine)
      # Alpine bisa punya beberapa lokasi
      PHP_INI_DIR="/etc/php83"  # untuk php83
      if [ ! -d "$PHP_INI_DIR" ]; then
        PHP_INI_DIR="/etc/php"
      fi
      PHP_INI="$PHP_INI_DIR/php.ini"
      PHP_INI_DEV="$PHP_INI_DIR/php.ini-development"
      if [ ! -f "$PHP_INI_DEV" ]; then
        PHP_INI_DEV="$PHP_INI_DIR/php.ini-production"
      fi
      ;;
    vps)
      # VPS biasanya menggunakan php.ini di /etc/php/[version]/cli atau /etc/php/[version]/apache2
      # Cari versi PHP yang terinstall
      PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "")
      if [ -n "$PHP_VERSION" ]; then
        # Coba lokasi CLI dulu (untuk PHP CLI)
        PHP_INI_DIR="/etc/php/$PHP_VERSION/cli"
        if [ -d "$PHP_INI_DIR" ]; then
          PHP_INI="$PHP_INI_DIR/php.ini"
          PHP_INI_DEV="$PHP_INI_DIR/php.ini-development"
          if [ ! -f "$PHP_INI_DEV" ]; then
            PHP_INI_DEV="$PHP_INI_DIR/php.ini-production"
          fi
        else
          # Fallback ke lokasi umum
          PHP_INI_DIR="/etc/php/$PHP_VERSION"
          PHP_INI="$PHP_INI_DIR/php.ini"
          PHP_INI_DEV="$PHP_INI_DIR/php.ini-development"
        fi
      else
        # Fallback jika tidak bisa deteksi versi
        PHP_INI_DIR="/etc/php"
        PHP_INI="$PHP_INI_DIR/php.ini"
        PHP_INI_DEV="$PHP_INI_DIR/php.ini-development"
      fi
      ;;
  esac
  
  # Jika php.ini tidak ada, copy dari development
  if [ ! -f "$PHP_INI" ] && [ -f "$PHP_INI_DEV" ]; then
    echo "📋 Menyalin php.ini-development ke php.ini..."
    if [ "$platform" = "vps" ]; then
      sudo cp "$PHP_INI_DEV" "$PHP_INI"
    elif [ "$platform" = "alpine" ]; then
      doas cp "$PHP_INI_DEV" "$PHP_INI"
    else
      cp "$PHP_INI_DEV" "$PHP_INI"
    fi
  fi
  
  # Jika php.ini masih tidak ada, coba buat dari scratch
  if [ ! -f "$PHP_INI" ]; then
    echo "⚠️ php.ini tidak ditemukan di $PHP_INI"
    echo "   Mencoba lokasi alternatif..."
    
    # Coba cari php.ini yang aktif
    PHP_INI_ACTIVE=$(php --ini 2>/dev/null | grep "Loaded Configuration File" | awk '{print $4}' || echo "")
    if [ -n "$PHP_INI_ACTIVE" ] && [ -f "$PHP_INI_ACTIVE" ]; then
      PHP_INI="$PHP_INI_ACTIVE"
      echo "✅ Menggunakan php.ini aktif: $PHP_INI"
    else
      echo "❌ Tidak dapat menemukan php.ini. Silakan konfigurasi manual."
      return 1
    fi
  fi
  
  # Backup php.ini
  if [ -f "$PHP_INI" ]; then
    echo "💾 Membuat backup php.ini..."
    if [ "$platform" = "vps" ]; then
      sudo cp "$PHP_INI" "${PHP_INI}.backup.$(date +%Y%m%d_%H%M%S)"
    elif [ "$platform" = "alpine" ]; then
      doas cp "$PHP_INI" "${PHP_INI}.backup.$(date +%Y%m%d_%H%M%S)"
    else
      cp "$PHP_INI" "${PHP_INI}.backup.$(date +%Y%m%d_%H%M%S)"
    fi
  fi
  
  # Update konfigurasi PHP
  if [ -f "$PHP_INI" ]; then
    echo "🔧 Mengatur PHP limits..."
    
    # Tentukan command untuk privilege escalation
    PRIV_CMD=""
    if [ "$platform" = "vps" ]; then
      PRIV_CMD="sudo"
    elif [ "$platform" = "alpine" ]; then
      PRIV_CMD="doas"
    fi
    
    # Fungsi untuk update atau add setting
    update_php_setting() {
      local setting=$1
      local value=$2
      local file=$3
      local priv_cmd=$4
      
      # Cek apakah setting sudah ada
      if grep -q "^[[:space:]]*$setting[[:space:]]*=" "$file" 2>/dev/null; then
        # Update existing setting
        if [ -n "$priv_cmd" ]; then
          $priv_cmd sed -i "s|^[[:space:]]*$setting[[:space:]]*=.*|$setting = $value|" "$file"
        else
          sed -i "s|^[[:space:]]*$setting[[:space:]]*=.*|$setting = $value|" "$file"
        fi
      else
        # Add new setting (tambahkan di akhir file)
        if [ -n "$priv_cmd" ]; then
          echo "$setting = $value" | $priv_cmd tee -a "$file" > /dev/null
        else
          echo "$setting = $value" >> "$file"
        fi
      fi
    }
    
    # Update settings
    update_php_setting "upload_max_filesize" "2G" "$PHP_INI" "$PRIV_CMD"
    update_php_setting "post_max_size" "2G" "$PHP_INI" "$PRIV_CMD"
    update_php_setting "memory_limit" "2G" "$PHP_INI" "$PRIV_CMD"
    update_php_setting "max_execution_time" "300" "$PHP_INI" "$PRIV_CMD"
    
    echo "✅ PHP configuration updated!"
    echo "   - upload_max_filesize = 2G"
    echo "   - post_max_size = 2G"
    echo "   - memory_limit = 2G"
    echo "   - max_execution_time = 300"
    return 0
  else
    echo "❌ Gagal mengonfigurasi PHP: php.ini tidak ditemukan"
    return 1
  fi
}

# Validasi source directory
if [ ! -f "$SOURCE_DIR/index.php" ]; then
  echo "❌ Error: File index.php tidak ditemukan di $SOURCE_DIR"
  echo "   Pastikan script dijalankan dari directory stream-dashboard."
  exit 1
fi

echo "📂 Source directory: $SOURCE_DIR"

# ================================
# TERMUX
# ================================
if [ "$PLATFORM" = "termux" ]; then
  echo "📲 Instalasi untuk Termux..."
  pkg update -y && pkg upgrade -y
  pkg install -y php curl ffmpeg

  # Konfigurasi PHP
  configure_php "termux"

  INSTALL_DIR="$HOME/$APP_DIR"
  echo "📋 Menyalin file ke $INSTALL_DIR..."
  
  mkdir -p "$INSTALL_DIR"
  
  # Copy semua file kecuali .git dan file instalasi
  if command -v rsync >/dev/null 2>&1; then
    rsync -av --exclude='.git' --exclude='install.sh' --exclude='uninstall.sh' "$SOURCE_DIR/" "$INSTALL_DIR/"
  else
    # Fallback: copy file satu per satu
    find "$SOURCE_DIR" -mindepth 1 -maxdepth 1 ! -name '.git' ! -name 'install.sh' ! -name 'uninstall.sh' -exec cp -r {} "$INSTALL_DIR/" \;
  fi
  
  # Buat folder users jika belum ada
  mkdir -p "$INSTALL_DIR/users"
  chmod 755 "$INSTALL_DIR/users"
  
  echo "✅ Instalasi selesai!"
  echo ""
  echo "📌 Jalankan dengan:"
  echo "    cd ~/stream-dashboard && php -S 0.0.0.0:$PORT"
  echo ""
  echo "🌐 Akses di: http://localhost:$PORT"
  exit 0
fi

# ================================
# ALPINE / POSTMARKETOS
# ================================
if [ "$PLATFORM" = "alpine" ]; then
  echo "🐧 Instalasi untuk Alpine / PostmarketOS..."

  doas apk update
  doas apk add --no-cache php php83 php83-cli php83-openssl php83-session php83-curl curl ffmpeg

  PHP_BIN=$(command -v php || command -v php83)

  # Konfigurasi PHP
  configure_php "alpine"

  INSTALL_DIR="$HOME/$APP_DIR"
  echo "📋 Menyalin file ke $INSTALL_DIR..."
  
  mkdir -p "$INSTALL_DIR"
  
  # Copy semua file kecuali .git dan file instalasi
  if command -v rsync >/dev/null 2>&1; then
    rsync -av --exclude='.git' --exclude='install.sh' --exclude='uninstall.sh' "$SOURCE_DIR/" "$INSTALL_DIR/"
  else
    # Fallback: copy file satu per satu
    find "$SOURCE_DIR" -mindepth 1 -maxdepth 1 ! -name '.git' ! -name 'install.sh' ! -name 'uninstall.sh' -exec cp -r {} "$INSTALL_DIR/" \;
  fi
  
  # Buat folder users jika belum ada
  mkdir -p "$INSTALL_DIR/users"
  chmod 755 "$INSTALL_DIR/users"
  
  echo "✅ Instalasi selesai!"
  echo ""
  echo "📌 Jalankan manual dengan:"
  echo "    cd $INSTALL_DIR && $PHP_BIN -S 0.0.0.0:$PORT"
  echo ""
  echo "⚠️ Catatan: Alpine/postmarketOS tidak menggunakan systemd,"
  echo "   jadi service otomatis tidak dibuat."
  echo "   Gunakan screen/tmux atau buat service manual jika diperlukan."
  exit 0
fi

# ================================
# VPS DEBIAN / UBUNTU
# ================================
if [ "$PLATFORM" = "vps" ]; then
  echo "🖥️ Instalasi untuk Debian/Ubuntu VPS..."

  sudo apt update
  sudo apt install -y php curl ffmpeg ufw

  # Konfigurasi PHP
  configure_php "vps"

  INSTALL_DIR="/opt/$APP_DIR"
  echo "📋 Menyalin file ke $INSTALL_DIR..."
  
  sudo mkdir -p "$INSTALL_DIR"
  sudo chown -R "$USER:$USER" "$INSTALL_DIR"
  
  # Copy semua file kecuali .git dan file instalasi
  if command -v rsync >/dev/null 2>&1; then
    rsync -av --exclude='.git' --exclude='install.sh' --exclude='uninstall.sh' "$SOURCE_DIR/" "$INSTALL_DIR/"
  else
    # Fallback: copy file satu per satu
    find "$SOURCE_DIR" -mindepth 1 -maxdepth 1 ! -name '.git' ! -name 'install.sh' ! -name 'uninstall.sh' -exec cp -r {} "$INSTALL_DIR/" \;
  fi
  
  # Buat folder users jika belum ada
  mkdir -p "$INSTALL_DIR/users"
  chmod 755 "$INSTALL_DIR/users"
  
  # Set ownership
  sudo chown -R "$USER:$USER" "$INSTALL_DIR"

  PHP_BIN=$(command -v php)

  SERVICE_FILE="/etc/systemd/system/stream-dashboard.service"

  echo "⚙️ Membuat systemd service..."
  cat <<EOF2 | sudo tee "$SERVICE_FILE" > /dev/null
[Unit]
Description=Stream Dashboard
After=network.target

[Service]
Type=simple
ExecStart=$PHP_BIN -S 0.0.0.0:$PORT -t $INSTALL_DIR
WorkingDirectory=$INSTALL_DIR
Restart=always
RestartSec=5
User=$USER

[Install]
WantedBy=multi-user.target
EOF2

  sudo systemctl daemon-reload
  sudo systemctl enable --now stream-dashboard

  sudo ufw allow $PORT

  IPADDR=$(hostname -I | awk '{print $1}')
  echo ""
  echo "✅ Instalasi selesai!"
  echo "🌐 Akses di: http://$IPADDR:$PORT"
  echo "📊 Status service: sudo systemctl status stream-dashboard"
  exit 0
fi
