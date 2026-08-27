#!/usr/bin/env bash
#
# Compila Seguranet y lo publica en el servidor.
# Se corre desde la máquina de desarrollo, no desde el servidor:
#
#   bash despliegue/publicar.sh
#
# En Windows conviene llamarlo a través de publicar.ps1, que encuentra el bash
# de Git y le pasa el trabajo.
#
# Variables que se pueden cambiar sin tocar el archivo:
#   SERVIDOR=ubuntu@1.2.3.4  LLAVE=~/.ssh/otra.key  bash despliegue/publicar.sh

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PROYECTO="$RAIZ/Seguranet.Web.csproj"

SERVIDOR="${SERVIDOR:-}"
LLAVE="${LLAVE:-$HOME/.ssh/seguranet.key}"

if [[ -z "$SERVIDOR" ]]; then
    echo "Falta decir a qué servidor publicar." >&2
    echo >&2
    echo "  SERVIDOR=ubuntu@LA-IP bash despliegue/publicar.sh" >&2
    echo >&2
    echo "O dejarlo fijo editando este archivo cuando la IP sea definitiva." >&2
    exit 1
fi

if [[ ! -f "$LLAVE" ]]; then
    echo "No encuentro la clave SSH en $LLAVE" >&2
    echo "Es la que descargaste al crear la instancia en Oracle." >&2
    exit 1
fi

SALIDA="$(mktemp -d)"
PAQUETE="$(mktemp -u).tar.gz"
trap 'rm -rf "$SALIDA" "$PAQUETE"' EXIT

ssh_() { ssh -i "$LLAVE" -o StrictHostKeyChecking=accept-new "$SERVIDOR" "$@"; }

echo "==> Compilando en Release"
# --no-self-contained: el runtime ya está instalado en el servidor, así que el
# paquete lleva sólo la aplicación. Son unos megabytes en vez de setenta.
dotnet publish "$PROYECTO" -c Release -o "$SALIDA" --nologo -v quiet --no-self-contained

# El appsettings.json que viaja es el del repositorio, con los secretos vacíos.
# Los de verdad los pone /etc/seguranet.env, que pisa lo que diga este archivo.
# Si alguna vez apareciera un appsettings con secretos, se queda acá.
rm -f "$SALIDA"/appsettings.Development.json

echo "==> Armando el paquete ($(du -sh "$SALIDA" | cut -f1))"
tar -czf "$PAQUETE" -C "$SALIDA" .

echo "==> Subiendo a $SERVIDOR"
scp -i "$LLAVE" -o StrictHostKeyChecking=accept-new -q "$PAQUETE" "$SERVIDOR:/tmp/seguranet-despliegue.tar.gz"

echo "==> Desplegando"
ssh_ 'bash -s' <<'REMOTO'
set -euo pipefail
CARPETA_APP="/var/www/seguranet"

echo "  - parando el servicio"
# || true: la primera vez el servicio todavía no existe y no es un error.
sudo systemctl stop seguranet || true

echo "  - reemplazando la aplicación"
# Se vacía la carpeta antes de descomprimir: si no, los archivos que se
# borraron del proyecto se quedarían para siempre en el servidor, y un día uno
# de ésos se ejecuta y nadie entiende por qué.
sudo rm -rf "${CARPETA_APP:?}"/*
sudo tar -xzf /tmp/seguranet-despliegue.tar.gz -C "$CARPETA_APP"
rm -f /tmp/seguranet-despliegue.tar.gz

sudo chown -R seguranet:seguranet "$CARPETA_APP"

echo "  - levantando el servicio"
sudo systemctl start seguranet

# Un momento para que arranque antes de preguntarle cómo le fue.
sleep 3
if sudo systemctl is-active --quiet seguranet; then
    echo "  - el servicio está corriendo"
else
    echo "  - EL SERVICIO NO ARRANCÓ. Últimas líneas del registro:" >&2
    sudo journalctl -u seguranet -n 25 --no-pager >&2
    exit 1
fi
REMOTO

echo
echo "==> Comprobando que responda"
if ssh_ 'curl -fsS -o /dev/null -w "%{http_code}" http://127.0.0.1:5080/' | grep -q '^200$'; then
    echo "    la aplicación responde 200"
else
    echo "    la aplicación no responde como se esperaba" >&2
    echo "    mirar: ssh -i $LLAVE $SERVIDOR 'sudo journalctl -u seguranet -n 50'" >&2
    exit 1
fi

echo
echo "Listo."
