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
