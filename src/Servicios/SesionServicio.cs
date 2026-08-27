using System.Text.Json;
using Seguranet.Models;

namespace Seguranet.Servicios;

/// <summary>
/// La sesión del usuario que entró por el login.
///
/// En el proyecto viejo esto no existía: el login validaba el correo y la clave
/// y, si daban bien, hacía un redirect y nada más. No quedaba registro de que
/// alguien hubiera entrado, así que el botón «Cerrar sesión» del menú no tenía
/// ninguna sesión que cerrar.
///
/// Cambio respecto de la versión de MVC 5: la sesión de ASP.NET Core guarda
/// bytes, no objetos, así que el usuario va serializado a JSON. Es más
/// explícito y, de paso, obliga a decidir qué se guarda — que es poco: ni la
/// clave ni el token entran acá.
///
/// OJO — esto identifica quién entró, no protege nada. Ninguna página del sitio
/// exige haber entrado, y no hay un solo [Authorize] en el proyecto. Está en
/// AUDITORIA.md como pendiente.
/// </summary>
public static class SesionServicio
{
    private const string Clave = "seguranet.usuario";

    public static void Iniciar(ISession sesion, UsuarioDTO usuario)
    {
        if (usuario is null)
        {
            return;
        }

        // Sin la clave ni el token: dentro de la sesión no hacen falta para
        // nada y es un lugar menos donde puedan terminar.
        var minimo = new UsuarioDTO
        {
            IdUsuario = usuario.IdUsuario,
            Nombre = usuario.Nombre,
            Apellido = usuario.Apellido,
            Correo = usuario.Correo,
            Confirmado = usuario.Confirmado,
        };

        sesion.SetString(Clave, JsonSerializer.Serialize(minimo));
    }

    /// <summary>Quién está adentro, o null si no entró nadie.</summary>
    public static UsuarioDTO? Actual(ISession sesion)
    {
        var json = sesion.GetString(Clave);
        if (string.IsNullOrEmpty(json))
        {
            return null;
        }

        try
        {
            return JsonSerializer.Deserialize<UsuarioDTO>(json);
        }
        catch (JsonException)
        {
            // Sesión vieja con otro formato: se trata como si no hubiera nadie
            // en vez de tirar la página abajo.
            return null;
        }
    }

    public static bool HayUsuario(ISession sesion) => Actual(sesion) is not null;

    /// <summary>
    /// Cierra la sesión. Se saca al usuario y además se vacía todo: si más
    /// adelante alguien guarda otra cosa en la sesión, no queda colgada después
    /// de salir.
    /// </summary>
    public static void Cerrar(ISession sesion)
    {
        sesion.Remove(Clave);
        sesion.Clear();
    }
}
