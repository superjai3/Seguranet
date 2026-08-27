using MailKit.Net.Smtp;
using MailKit.Security;
using Microsoft.Extensions.Options;
using MimeKit;
using Seguranet.Models;

namespace Seguranet.Servicios;

/// <summary>
/// Envío de los correos de confirmación de cuenta y restablecimiento de clave.
///
/// El original traía la casilla y la contraseña de aplicación de Gmail escritas
/// en dos campos estáticos, en un repositorio público. Ahora salen de
/// <see cref="OpcionesCorreo"/>, que en el servidor se llena desde un archivo de
/// entorno fuera del repositorio.
///
/// El otro cambio es que ya no se traga los errores en silencio. El
/// <c>catch { return false; }</c> del original hacía imposible saber si un
/// correo no llegó porque la casilla estaba mal, porque el SMTP rechazó la
/// conexión o porque el destinatario no existía: todo era «false».
/// </summary>
public class CorreoServicio
{
    private readonly OpcionesCorreo _opciones;
    private readonly ILogger<CorreoServicio> _registro;

    public CorreoServicio(IOptions<OpcionesCorreo> opciones, ILogger<CorreoServicio> registro)
    {
        _opciones = opciones.Value;
        _registro = registro;
    }

    public async Task<bool> EnviarAsync(CorreoDTO correo, CancellationToken ct = default)
    {
        if (!_opciones.Habilitado)
        {
            // Sin credenciales el sitio sigue funcionando: las consultas se
            // guardan en la base igual. Se avisa una vez por intento, para que
            // al mirar el registro se entienda por qué no llegó el correo.
            _registro.LogWarning(
                "No hay credenciales de correo configuradas: no se envió «{Asunto}» a {Para}.",
                correo.Asunto, correo.Para);
            return false;
        }

        try
        {
            var mensaje = new MimeMessage();
            mensaje.From.Add(new MailboxAddress(_opciones.NombreEnvia, _opciones.Usuario));
            mensaje.To.Add(MailboxAddress.Parse(correo.Para));
            mensaje.Subject = correo.Asunto;
            mensaje.Body = new TextPart(MimeKit.Text.TextFormat.Html) { Text = correo.Contenido };

            using var smtp = new SmtpClient();
            await smtp.ConnectAsync(_opciones.Servidor, _opciones.Puerto, SecureSocketOptions.StartTls, ct);
            await smtp.AuthenticateAsync(_opciones.Usuario, _opciones.Clave, ct);
            await smtp.SendAsync(mensaje, ct);
            await smtp.DisconnectAsync(true, ct);
            return true;
        }
        catch (Exception e)
        {
            _registro.LogError(e, "Falló el envío de «{Asunto}» a {Para}.", correo.Asunto, correo.Para);
            return false;
        }
    }
}
