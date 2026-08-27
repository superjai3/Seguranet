#!/usr/bin/env bash
#
# Prepara un servidor Ubuntu recién creado para alojar Seguranet.
# Se corre UNA sola vez, dentro del servidor, como root o con sudo:
#
#   sudo bash preparar-servidor.sh
#
# Después de esto queda todo listo salvo dos cosas: los secretos, que se cargan
# a mano, y el certificado, que necesita que el dominio ya apunte acá.
#
# Es el mismo procedimiento que usa Enricci en su propia instancia, con los
# nombres cambiados. Si algo falla, comparar con aquel primero.

set -euo pipefail

USUARIO="seguranet"
CARPETA_APP="/var/www/seguranet"
CARPETA_DATOS="/var/lib/seguranet"

echo "==> Actualizando el sistema"
apt-get update -qq
apt-get upgrade -y -qq

echo "==> Instalando lo necesario"
# aspnetcore-runtime-8.0: alcanza para ejecutar. No hace falta el SDK, porque el
# proyecto se compila en la máquina de desarrollo y acá sólo se corre lo ya
# compilado. Ubuntu 24.04 lo trae en sus propios repositorios; en 22.04 habría
# que agregar el de Microsoft.
apt-get install -y -qq \
    aspnetcore-runtime-8.0 \
    nginx \
    certbot python3-certbot-nginx \
    unzip rsync \
    iptables-persistent

echo "==> Creando el usuario del servicio"
# Sin shell y sin home: este usuario existe únicamente para correr el sitio, así
# que si alguien lograra ejecutar algo a través de la aplicación, no encuentra
# una sesión desde la que seguir.
if ! id -u "$USUARIO" >/dev/null 2>&1; then
    adduser --system --group --no-create-home --shell /usr/sbin/nologin "$USUARIO"
fi

echo "==> Creando las carpetas"
# La aplicación va en una carpeta y los datos en otra, a propósito: el
# despliegue reemplaza la aplicación entera, y la base es lo único que no se
# puede volver a generar.
mkdir -p "$CARPETA_APP"
mkdir -p "$CARPETA_DATOS"/respaldos
mkdir -p /var/www/certbot

chown -R "$USUARIO:$USUARIO" "$CARPETA_DATOS"
chown -R "$USUARIO:$USUARIO" "$CARPETA_APP"

echo "==> Abriendo los puertos 80 y 443 en el cortafuegos de la máquina"
# Este es EL tropiezo de Oracle Cloud: hay dos cortafuegos. Las imágenes de
# Ubuntu traen reglas de iptables que sólo dejan pasar SSH, así que abrir los
# puertos en la consola de Oracle no alcanza. Si se abre uno solo, el sitio
# parece inalcanzable y no hay ningún error que lo explique.
#
# Se comprueba antes de agregar: este script se puede correr en una máquina que
# ya aloja otro sitio —los puertos entonces ya están abiertos— y volver a
# insertar la regla dejaría duplicados que se acumulan en cada pasada.
abrir_puerto() {
    local puerto="$1"
    if iptables -C INPUT -p tcp --dport "$puerto" -m state --state NEW,ESTABLISHED -j ACCEPT 2>/dev/null; then
        echo "    el puerto $puerto ya estaba abierto"
    else
        iptables -I INPUT 5 -p tcp --dport "$puerto" -m state --state NEW,ESTABLISHED -j ACCEPT
        echo "    abierto el puerto $puerto"
        REGLAS_NUEVAS=1
    fi
}

REGLAS_NUEVAS=0
abrir_puerto 80
abrir_puerto 443
[ "$REGLAS_NUEVAS" -eq 1 ] && netfilter-persistent save

# Memoria de intercambio, sólo si la máquina tiene poca RAM y todavía no la
# tiene. En la E2.1.Micro de la capa gratuita (1 GB) hace falta: sin ella, una
# compilación o un pico de tráfico terminan con el proceso muerto sin aviso.
RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
if [ "$RAM_MB" -lt 2048 ] && [ ! -f /swapfile ]; then
    echo "==> La máquina tiene ${RAM_MB} MB de RAM: agregando 2 GB de swap"
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo
echo "Listo. Lo que falta, en este orden:"
echo
echo "  1. Los secretos:"
echo "       sudo nano /etc/seguranet.env      (partir de seguranet.env.ejemplo)"
echo "       sudo chmod 600 /etc/seguranet.env"
echo
echo "  2. El servicio:"
echo "       sudo cp seguranet.service /etc/systemd/system/"
echo "       sudo systemctl daemon-reload && sudo systemctl enable seguranet"
echo
echo "  3. Desplegar, desde la máquina de desarrollo:"
echo "       .\\despliegue\\publicar.ps1"
echo
echo "  4. Cuando seguranet.es resuelva a esta máquina:"
echo "       sudo bash dominio.sh seguranet.es"
echo
echo "     Comprobar ANTES que el dominio resuelve acá, o certbot falla y hay"
echo "     que esperar para reintentar:"
echo "       dig +short seguranet.es"
echo "       curl -s ifconfig.me        # tiene que dar la misma IP"
