#!/bin/bash

echo "🧹 Menjalankan proses uninstall Stream Dashboard..."

APP_DIR="stream-dashboard"
INSTALL_DIR_TERMUX="$HOME/$APP_DIR"
INSTALL_DIR_ALPINE="$HOME/$APP_DIR"
INSTALL_DIR_VPS="/opt/$APP_DIR"
SERVICE_NAME="stream-dashboard"
SERVICE_FILE="/etc/systemd/system/$SERVICE_NAME.service"

# Deteksi OS
OS_NAME=""
OS_ID=""
if [ -f /etc/os-release ]; then
  OS_NAME=$(grep '^NAME=' /etc/os-release | cut -d= -f2 | tr -d '"')
  OS_ID=$(grep '^ID=' /etc/os-release | cut -d= -f2 | tr -d '"')
fi

PLATFORM=""

if echo "$OS_NAME" | grep -qi "Android" || echo "$OS_ID" | grep -qi "android"; then
  PLATFORM="termux"
elif echo "$OS_ID" | grep -qi "alpine"; then
  PLATFORM="alpine"
elif [[ "$OS_ID" == "debian" || "$OS_ID" == "ubuntu" ]]; then
  PLATFORM="vps"
elif grep -qi "armbian" /etc/os-release 2>/dev/null || uname -a | grep -qi "armbian"; then
  PLATFORM="vps"
else
  echo "❓ Tidak bisa deteksi otomatis. Pilih:"
  echo "1) Termux"
  echo "2) VPS/Armbian"
  echo "3) Alpine/postmarketOS"
  read -p "Pilih platform (1/2/3): " input
  case "$input" in
    1) PLATFORM="termux" ;;
    2) PLATFORM="vps" ;;
    3) PLATFORM="alpine" ;;
    *) PLATFORM="vps" ;;
  esac
fi

# ==== TERMUX ====
if [[ "$PLATFORM" == "termux" ]]; then
  if [ -d "$INSTALL_DIR_TERMUX" ]; then
    echo "🧹 Menghapus direktori $INSTALL_DIR_TERMUX..."
    rm -rf "$INSTALL_DIR_TERMUX"
    echo "✅ Uninstall dari Termux selesai."
  else
    echo "⚠️ Direktori $INSTALL_DIR_TERMUX tidak ditemukan."
  fi

# ==== ALPINE / POSTMARKETOS ====
elif [[ "$PLATFORM" == "alpine" ]]; then
  if [ -d "$INSTALL_DIR_ALPINE" ]; then
    echo "🧹 Menghapus direktori $INSTALL_DIR_ALPINE..."
    rm -rf "$INSTALL_DIR_ALPINE"
    echo "✅ Uninstall dari Alpine/postmarketOS selesai."
  else
    echo "⚠️ Direktori $INSTALL_DIR_ALPINE tidak ditemukan."
  fi
  echo "ℹ️  Catatan: Jika menggunakan service manual, hapus service tersebut secara manual."

# ==== VPS / ARMBIAN ====
elif [[ "$PLATFORM" == "vps" ]]; then
  echo "🛑 Menghentikan dan menonaktifkan service..."
  sudo systemctl stop "$SERVICE_NAME" 2>/dev/null
  sudo systemctl disable "$SERVICE_NAME" 2>/dev/null

  if [ -d "$INSTALL_DIR_VPS" ]; then
    echo "🧹 Menghapus direktori $INSTALL_DIR_VPS..."
    sudo rm -rf "$INSTALL_DIR_VPS"
  else
    echo "⚠️ Direktori $INSTALL_DIR_VPS tidak ditemukan."
  fi

  if [ -f "$SERVICE_FILE" ]; then
    echo "🧽 Menghapus file service systemd..."
    sudo rm -f "$SERVICE_FILE"
    sudo systemctl daemon-reload
    echo "✅ Service systemd telah dihapus."
  else
    echo "⚠️ File service systemd tidak ditemukan."
  fi

  echo "✅ Uninstall dari VPS / Armbian selesai."

else
  echo "❌ Platform tidak dikenali. Batal."
  exit 1
fi