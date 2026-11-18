#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PREFIX="${PREFIX:-/usr/local}"
CONFIG_DIR="${CONFIG_DIR:-/etc/ward-agent}"
DATA_DIR="${DATA_DIR:-/var/lib/ward-agent}"

cd "$ROOT/ward-agent"
go build -o "$PREFIX/bin/ward-agent" ./cmd/ward-agent

install -d -m 0750 "$CONFIG_DIR" "$DATA_DIR"
if [[ ! -f "$CONFIG_DIR/config.yaml" ]]; then
  sed "s|./data|$DATA_DIR|g" configs/agent.example.yaml > "$CONFIG_DIR/config.yaml"
fi

if [[ -d /etc/systemd/system ]]; then
  install -m 0644 deployments/ward-agent.service /etc/systemd/system/ward-agent.service
  echo "Installed systemd unit. Paste Panel-generated agent.yaml into $CONFIG_DIR/config.yaml"
  echo "systemctl daemon-reload && systemctl enable --now ward-agent"
fi
