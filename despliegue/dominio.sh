#!/usr/bin/env bash
#
# Pone Seguranet a responder en un dominio, con HTTPS.
# Se corre DENTRO del servidor, con sudo:
#
#   sudo bash dominio.sh seguranet.es
#   sudo bash dominio.sh seguranet.es micorreo@ejemplo.com
#
# Qué hace, en orden: comprueba que el dominio apunte de verdad a esta máquina,
# deja nginx atendiendo por HTTP, saca el certificado de Let's Encrypt, pasa a
# HTTPS y le avisa a la aplicación cuál es su dominio.
#
# Se puede volver a correr las veces que haga falta: si el certificado ya está,
# certbot lo reutiliza en vez de pedir otro.

set -euo pipefail

DOMINIO="${1:-}"
# Let's Encrypt usa este correo sólo para avisar si un certificado está por
# vencer sin haberse renovado. No manda publicidad.
MAIL="${2:-seguranetarg@gmail.com}"

if [[ -z "$DOMINIO" ]]; then
    echo "Falta el dominio. Ejemplo:" >&2
    echo "  sudo bash dominio.sh seguranet.es" >&2
    exit 1
fi

if [[ $EUID -ne 0 ]]; then
    echo "Hay que correrlo con sudo." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Comprobar el DNS ANTES de llamar a certbot.
#
# Let's Encrypt limita a 5 fallos por hora y por dominio. Si se pide el
# certificado con el DNS a medio propagar, se gastan intentos y hay que esperar
# sin poder hacer nada. Un chequeo de dos segundos evita esa espera.
# ---------------------------------------------------------------------------
echo "==> Comprobando que $DOMINIO apunte a esta máquina"
IP_SERVIDOR=$(curl -s --max-time 10 ifconfig.me || true)
IP_DOMINIO=$(dig +short "$DOMINIO" A | tail -1 || true)

echo "    esta máquina: ${IP_SERVIDOR:-desconocida}"
echo "    $DOMINIO:     ${IP_DOMINIO:-no resuelve}"

if [[ -z "$IP_DOMINIO" ]]; then
    echo >&2
    echo "$DOMINIO todavía no resuelve a ninguna dirección." >&2
    echo "Falta crear el registro A en el panel del dominio, o todavía se está" >&2
    echo "propagando. Suele tardar entre unos minutos y unas horas." >&2
    exit 1
fi

if [[ "$IP_DOMINIO" != "$IP_SERVIDOR" ]]; then
    echo >&2
    echo "$DOMINIO resuelve a $IP_DOMINIO, que no es esta máquina." >&2
    echo "Si acabás de cambiar el registro A, esperá a que se propague." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# nginx por HTTP
#
# Primero sin certificado, porque Let's Encrypt necesita responder un desafío
# por el puerto 80 para comprobar que el dominio es tuyo. Con el certificado ya
# puesto, certbot reescribe esta misma configuración.
# ---------------------------------------------------------------------------
echo "==> Configurando nginx"
cat > /etc/nginx/sites-available/seguranet <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMINIO www.$DOMINIO;

    # El desafío de Let's Encrypt se sirve como archivo, sin pasar por la
    # aplicación: si la aplicación estuviera caída, la renovación seguiría
    # funcionando igual.
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        proxy_pass         http://127.0.0.1:5080;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        # Sin esto la aplicación cree que todo llega en claro y arma las URL
        # absolutas con http://, aunque el visitante haya entrado por https.
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   Upgrade           \$http_upgrade;
        proxy_set_header   Connection        keep-alive;
        proxy_cache_bypass \$http_upgrade;
    }

    # Los estáticos no cambian entre despliegues salvo que se toquen, y el
    # nombre lleva versión, así que se pueden cachear con tranquilidad.
    location ~* \.(webp|jpg|jpeg|png|gif|ico|css|js|woff2?)$ {
        proxy_pass http://127.0.0.1:5080;
        expires 7d;
        add_header Cache-Control "public";
    }

    # Comprimir: el grueso de lo que baja el visitante es texto.
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
    gzip_min_length 1024;

    client_max_body_size 20M;
}
NGINX

ln -sf /etc/nginx/sites-available/seguranet /etc/nginx/sites-enabled/seguranet
# El sitio de ejemplo de Ubuntu responde a cualquier nombre y se queda con los
# pedidos que deberían llegar acá.
rm -f /etc/nginx/sites-enabled/default

mkdir -p /var/www/certbot
nginx -t
systemctl reload nginx

# ---------------------------------------------------------------------------
# El certificado
# ---------------------------------------------------------------------------
echo "==> Pidiendo el certificado a Let's Encrypt"
certbot --nginx \
    -d "$DOMINIO" -d "www.$DOMINIO" \
    --non-interactive --agree-tos --email "$MAIL" \
    --redirect

# certbot instala su propio temporizador de renovación. Se comprueba que exista,
# porque un certificado que no se renueva tira el sitio a los tres meses y el
# aviso llega por correo a una casilla que quizá nadie mire.
systemctl list-timers 'certbot*' --no-pager | head -3

# ---------------------------------------------------------------------------
# Avisarle a la aplicación cuál es su dominio
#
# De ahí salen las URL canónicas, las de Open Graph y el sitemap. Si queda
# vacío, se arman con el host del pedido: correcto para probar por IP, pero en
# producción haría que la misma página se indexe en dos direcciones.
# ---------------------------------------------------------------------------
if [[ -f /etc/seguranet.env ]]; then
    if grep -q '^Sitio__Dominio=' /etc/seguranet.env; then
        sed -i "s|^Sitio__Dominio=.*|Sitio__Dominio=$DOMINIO|" /etc/seguranet.env
    else
        echo "Sitio__Dominio=$DOMINIO" >> /etc/seguranet.env
    fi
    systemctl restart seguranet || true
fi

echo
echo "Listo. Comprobar:"
echo "  curl -I https://$DOMINIO"
echo "  curl -I http://$DOMINIO      # tiene que redirigir con 301 a https"
