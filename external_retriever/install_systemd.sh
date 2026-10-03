#!/usr/bin/env bash
set -euo pipefail

SERVICE_NAME="mbs-retriever"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
TEMPLATE_FILE="${SCRIPT_DIR}/mbs-retriever.service.template"

if [[ "${EUID}" -ne 0 ]]; then
  echo "ERROR: Run this script as root (or with sudo)."
  exit 1
fi

for required in \
  "${SCRIPT_DIR}/retriever_service.py" \
  "${SCRIPT_DIR}/.env" \
  "${SCRIPT_DIR}/.venv/bin/uvicorn" \
  "${TEMPLATE_FILE}"
do
  if [[ ! -e "${required}" ]]; then
    echo "ERROR: Missing required file: ${required}"
    exit 1
  fi
done

APP_DIR="${SCRIPT_DIR}"

sed "s|__APP_DIR__|${APP_DIR}|g" "${TEMPLATE_FILE}" > "${SERVICE_FILE}"
chmod 644 "${SERVICE_FILE}"

systemctl daemon-reload
systemctl enable --now "${SERVICE_NAME}"

echo
echo "Installed: ${SERVICE_FILE}"
echo "App directory: ${APP_DIR}"
echo
systemctl status "${SERVICE_NAME}" --no-pager || true

echo
echo "Health check:"
sleep 2
curl -fsS http://127.0.0.1:8000/health || true
echo
