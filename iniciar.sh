#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"
BACKEND_UNIT="intelbrastech-backend.service"
FRONTEND_UNIT="intelbrastech-frontend.service"
SCHEDULER_UNIT="intelbrastech-scheduler.service"
BACKEND_URL="http://127.0.0.1:8033"
FRONTEND_URL="http://127.0.0.1:5173/login"
RUN_USER="${SUDO_USER:-$(stat -c '%U' "$ROOT_DIR")}"

if [[ ! -f "$BACKEND_DIR/artisan" || ! -f "$FRONTEND_DIR/package.json" ]]; then
    echo "Erro: execute este script dentro da raiz do IntelbrasTech-ERP." >&2
    exit 1
fi

for command in php npm curl systemctl systemd-run; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Erro: comando obrigatorio nao encontrado: $command" >&2
        exit 1
    fi
done

if [[ $EUID -ne 0 ]]; then
    exec sudo --preserve-env=USER bash "$0" "$@"
fi

stop_unit() {
    local unit="$1"
    if systemctl list-units --all --full --no-legend "$unit" | grep -q "$unit"; then
        systemctl stop "$unit"
    fi
    systemctl reset-failed "$unit" 2>/dev/null || true
}

wait_for_url() {
    local name="$1"
    local url="$2"

    for _ in {1..30}; do
        if curl --silent --fail --output /dev/null "$url"; then
            echo "$name pronto: $url"
            return 0
        fi
        sleep 1
    done

    echo "Erro: $name nao respondeu em $url." >&2
    return 1
}

echo "Iniciando IntelbrasTech ERP..."
stop_unit "$BACKEND_UNIT"
stop_unit "$FRONTEND_UNIT"
stop_unit "$SCHEDULER_UNIT"

systemd-run \
    --quiet \
    --unit="${BACKEND_UNIT%.service}" \
    --property="User=$RUN_USER" \
    --property="WorkingDirectory=$BACKEND_DIR" \
    /usr/bin/php artisan serve --host=0.0.0.0 --port=8033

systemd-run \
    --quiet \
    --unit="${FRONTEND_UNIT%.service}" \
    --property="User=$RUN_USER" \
    --property="WorkingDirectory=$FRONTEND_DIR" \
    /usr/bin/npm run dev -- --host=0.0.0.0 --port=5173

systemd-run \
    --quiet \
    --unit="${SCHEDULER_UNIT%.service}" \
    --property="User=$RUN_USER" \
    --property="WorkingDirectory=$BACKEND_DIR" \
    /usr/bin/php artisan schedule:work

wait_for_url "Backend" "$BACKEND_URL"
wait_for_url "Frontend" "$FRONTEND_URL"

echo
echo "IntelbrasTech ERP esta ativo."
echo "Acesso: $FRONTEND_URL"
echo "Status: sudo systemctl status $BACKEND_UNIT $FRONTEND_UNIT $SCHEDULER_UNIT"
