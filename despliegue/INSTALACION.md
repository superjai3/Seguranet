# Publicar Seguranet en seguranet.es

Todos los comandos, en orden. Lo que hay entra en la capa **Always Free** de
Oracle Cloud: no vence a los doce meses y no pide tarjeta para seguir.

**El orden importa.** Cada paso necesita algo del anterior, y el certificado es
lo último de todo: pedirlo antes de que el dominio resuelva gasta intentos
contra un límite de Let's Encrypt y obliga a esperar.

```
1. Crear la instancia          → te da la IP
2. Preparar el servidor        → dentro, por SSH
3. Los secretos                → dentro, a mano
4. DNS en Hostalia             → apuntar a la IP y esperar
5. Publicar la aplicación      → desde tu PC
6. El dominio y el certificado → dentro
```

---

## 1. La instancia — en la consola de Oracle

Esto es de hacer clic, no hay comandos. **Compute → Instances → Create instance**.

| Qué | Cuál | Por qué |
| --- | --- | --- |
| Nombre | `seguranet` | |
| Imagen | **Canonical Ubuntu 24.04** | Trae el runtime de .NET 8 en sus propios repositorios. En 22.04 hay que agregar el de Microsoft. |
| Forma | **A1.Flex** con lo que sobre de la cuota, o **E2.1.Micro** | La cuota ARM es de 4 OCPU y 24 GB **para toda la cuenta**. Si Enricci ya los usa, no queda nada para una segunda A1: ahí va la Micro, de las que dan dos gratis aparte. |
| Disco | 50 GB | |
| Clave SSH | **Guardar la privada que ofrece descargar** | Es la única forma de entrar. Oracle no la vuelve a mostrar. |

Después, dos cosas más en la consola:

**Fijar la IP.** *Instance → Attached VNICs → la VNIC → IP addresses* → editar la
IP pública y pasarla de **Ephemeral** a **Reserved**. Si no, un día cambia sola y
el dominio deja de resolver sin que nadie haya tocado nada.

**Abrir los puertos.** *Networking → Virtual Cloud Networks → la VCN → Security
Lists → Default Security List → Add Ingress Rules*. Dos reglas, origen
`0.0.0.0/0`, protocolo TCP, puertos de destino **80** y **443**.

> Este es el punto donde más gente se traba: **hay dos cortafuegos**. Éste es el
> de Oracle; el de dentro de Ubuntu lo abre el script del paso 2. Si se abre uno
> solo, el sitio parece inalcanzable y no hay ningún error que lo explique.

Anotá la **IP pública**, que aparece en la ficha de la instancia.

---

## 2. Entrar y preparar el servidor

### Desde tu PC, en PowerShell

Windows exige que la clave privada sea legible sólo por vos, o SSH la rechaza.
Es el primer tropiezo y el mensaje de error no lo dice claro:

```powershell
# Poner la clave donde va y quitarle los permisos heredados
mkdir -Force $HOME\.ssh
Move-Item -Force "$HOME\Downloads\ssh-key-*.key" "$HOME\.ssh\seguranet.key"

icacls "$HOME\.ssh\seguranet.key" /inheritance:r
icacls "$HOME\.ssh\seguranet.key" /grant:r "$($env:USERNAME):(R)"
```

Ahora sí, entrar (cambiar `LA-IP` por la de tu instancia):

```powershell
ssh -i $HOME\.ssh\seguranet.key ubuntu@LA-IP
```

La primera vez pregunta si confiás en la huella del servidor: `yes`.

### Ya dentro del servidor

```bash
# Bajar los scripts. El repositorio es público, así que no hace falta clave.
sudo apt-get update -qq && sudo apt-get install -y -qq git
git clone --branch net8/produccion-seguranet-es \
    https://github.com/superjai3/Seguranet.git ~/seguranet-repo

cd ~/seguranet-repo/despliegue
sudo bash preparar-servidor.sh
```

Tarda unos minutos. Instala .NET 8, nginx y certbot, crea el usuario del
servicio, prepara las carpetas, **abre el cortafuegos de la máquina** y agrega
memoria de intercambio si hace falta.

Después, dejar instalado el servicio:

```bash
sudo cp seguranet.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable seguranet
```

---

## 3. Los secretos

Siguen dentro del servidor:

```bash
sudo cp ~/seguranet-repo/despliegue/seguranet.env.ejemplo /etc/seguranet.env
sudo nano /etc/seguranet.env
```

Hay que completar dos huecos:

- `MercadoLibre__ClientSecret=` — de developers.mercadolibre.com.ar → Mis
  aplicaciones → la app → clave secreta.
- `Correo__Clave=` — una **contraseña de aplicación** de Google, de 16
  caracteres, de myaccount.google.com/apppasswords. **No es la contraseña de la
  cuenta**: Google dejó de aceptarla por SMTP en 2022.

Guardar con `Ctrl+O`, `Enter`, `Ctrl+X`. Y cerrarlo:

```bash
sudo chown root:root /etc/seguranet.env
sudo chmod 600 /etc/seguranet.env
```

Con esos permisos ni el propio servicio puede abrirlo: lo lee systemd y se lo
pasa al proceso como variables de entorno.

---

## 4. El DNS, en Hostalia

En el panel de Hostalia, buscar el dominio `seguranet.es`.

Los servidores de nombres que trae por defecto (`ns1.dns-parking.com`,
`ns2.dns-parking.com`) son de **aparcamiento**: sirven para mostrar una página de
«en venta» y normalmente no dejan crear registros. Hay dos caminos:

- **Si hay editor de zona DNS** («Gestión DNS» o «Zona DNS»): usarlo directamente.
- **Si no lo hay**: cambiar los servidores de nombres a los de Hostalia, que es
  lo que habilita el editor.

Después, **dos registros A**:

| Tipo | Nombre | Valor | TTL |
| --- | --- | --- | --- |
| A | `@` | la IP de la instancia | 3600 |
| A | `www` | la IP de la instancia | 3600 |

Nada de CNAME ni de redirecciones: eso lo maneja nginx.

### Comprobar que propagó

Desde tu PC:

```powershell
Resolve-DnsName seguranet.es -Type A -Server 8.8.8.8
```

Tiene que devolver la IP de la instancia. Puede tardar entre unos minutos y
unas horas. **Hasta que no dé la IP correcta, no seguir al paso 6.**

---

## 5. Publicar la aplicación

Desde tu PC, en PowerShell, en la carpeta del proyecto:

```powershell
cd C:\Users\jaime\source\repos\Seguranet
.\despliegue\publicar.ps1
```

Compila el proyecto, lo empaqueta, lo sube por SSH y reinicia el servicio.

Comprobar que arrancó, desde dentro del servidor:

```bash
sudo systemctl status seguranet
curl -I http://127.0.0.1:5080/
```

Si algo falló, el registro dice qué:

```bash
sudo journalctl -u seguranet -n 50 --no-pager
```

---

## 6. El dominio y el certificado

**Sólo cuando el paso 4 ya devuelva la IP correcta.** Dentro del servidor:

```bash
cd ~/seguranet-repo/despliegue
git pull
sudo bash dominio.sh seguranet.es
```

El script comprueba primero que el dominio apunte de verdad a esta máquina y
sólo entonces pide el certificado. Let's Encrypt limita a **cinco fallos por
hora y por dominio**: pedirlo con el DNS a medio propagar gasta intentos y
obliga a esperar sin poder hacer nada.

Comprobar:

```bash
curl -I https://seguranet.es
curl -I http://seguranet.es    # tiene que redirigir con 301 a https
```

---

## Uso diario

**Publicar un cambio**, desde tu PC:

```powershell
cd C:\Users\jaime\source\repos\Seguranet
.\despliegue\publicar.ps1
```

**Ver qué está pasando**, dentro del servidor:

```bash
sudo systemctl status seguranet        # ¿está corriendo?
sudo journalctl -u seguranet -f        # el registro, en vivo
sudo systemctl restart seguranet       # reiniciar
sudo nginx -t && sudo systemctl reload nginx
```

**El certificado se renueva solo.** certbot instala un temporizador al sacarlo.
Para comprobar que sigue vivo:

```bash
sudo systemctl list-timers 'certbot*'
sudo certbot renew --dry-run
```

---

## Cosas que conviene saber

**Los datos viven fuera de la aplicación.** La base está en
`/var/lib/seguranet/`, no en `/var/www/seguranet/`. Por eso un despliegue puede
reemplazar el programa entero sin tocar lo único que no se puede volver a
generar.

**La aplicación escucha sólo en localhost**, en el puerto 5080. Quien atiende
desde fuera es nginx, que termina el TLS. No hay forma de llegar al 5080 desde
internet, y así tiene que ser.

**Si el sitio no responde y todo parece bien**, casi siempre es el cortafuegos:
comprobar que estén abiertos los dos, el de la consola de Oracle y el de la
máquina (`sudo iptables -L INPUT -n --line-numbers | head`).

---

## Apéndice: compartir la máquina con otro sitio

Si en lugar de una instancia propia se aloja junto a otro sitio —hoy, junto a
Enricci en `168.138.128.137`— no hay **nada** que configurar en la consola de
Oracle: los puertos ya están abiertos y la IP ya es fija. Todo pasa dentro.

Los dos sitios no se pisan porque cada uno tiene lo suyo:

| | Enricci | Seguranet |
| --- | --- | --- |
| Puerto | 5000 | 5080 |
| Usuario | `enricci` | `seguranet` |
| Aplicación | `/var/www/enricci` | `/var/www/seguranet` |
| Datos | `/var/lib/enricci` | `/var/lib/seguranet` |
| nginx | `sites-available/enricci` | `sites-available/seguranet` |

Un mismo nginx atiende los dos dominios y reparte según el nombre que pide el
navegador.

### La memoria es lo único ajustado

Medido en esa máquina: 954 MB en total, Enricci ocupa unos 234 MB, nginx 6 MB,
y quedan unos 340 MB disponibles más 2 GB de swap casi sin usar.

Entra, pero sólo porque `seguranet.service` viene con límites puestos:

- **GC de estación de trabajo** en vez de servidor. El de servidor reserva por
  núcleo y está pensado para máquinas grandes; acá es lo contrario de lo que
  conviene.
- **`MemoryHigh=180M`**: por encima de eso el núcleo aprieta y le recupera
  memoria a este servicio.
- **`MemoryMax=250M`**: techo duro. Si lo pasa, se mata **este** proceso y no el
  vecino.

Ese último punto es el que importa: garantiza que un problema en Seguranet no se
lleve puesto un sitio de un cliente real. Sin él, el que muere cuando falta
memoria es el que está recibiendo tráfico, que puede ser cualquiera de los dos.

### Comprobar cómo va

```bash
systemctl show seguranet -p MemoryCurrent   # cuánto usa ahora
free -m                                     # cuánto queda en la máquina
journalctl -u seguranet | grep -i "memory\|killed"
```

Si aparece que lo mataron por memoria, hay dos caminos: subir el techo si
todavía hay margen, o mover Seguranet a una instancia propia. La cuota ARM de
la capa gratuita —4 CPU y 24 GB— está sin usar si el otro sitio corre en una
`E2.1.Micro` x86, así que esa mudanza no cuesta dinero.
