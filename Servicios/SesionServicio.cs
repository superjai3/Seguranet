using System.Web;
using Seguranet.Models;

namespace Seguranet.Servicios
{
    /// <summary>
    /// La sesión del usuario que entró por el login.
    ///
    /// Antes esto no existía: el login validaba el correo y la clave contra la
    /// base y, si daban bien, hacía un RedirectToAction y nada más. No quedaba
    /// registro de que alguien hubiera entrado, así que el botón "Cerrar sesión"
    /// del menú no tenía ninguna sesión que cerrar.
    ///
    /// Se guarda en Session y no en una cookie propia porque no hace falta que
    /// sobreviva al cierre del navegador: es un panel de consulta, no un sitio
    /// donde uno quiera quedar logueado una semana.
    ///
    /// OJO — esto identifica quién entró, no protege nada. Ninguna página del
    /// sitio exige haber entrado: las de Views/Home son públicas a propósito
    /// (son las que explican el negocio) y no hay ningún [Authorize] en el
    /// proyecto. Si en algún momento hay pantallas que sólo deba ver un usuario
    /// registrado, hay que decidir cuáles y protegerlas; está anotado en
    /// AUDITORIA.md.
    /// </summary>
    public static class SesionServicio
    {
        private const string ClaveUsuario = "seguranet.usuario";

        /// <summary>Deja registrado que este usuario entró.</summary>
        public static void Iniciar(HttpSessionStateBase sesion, UsuarioDTO usuario)
        {
            if (sesion == null || usuario == null)
            {
                return;
            }

            // La clave y el token no se guardan: dentro de la sesión no hacen
            // falta para nada y es un lugar menos donde puedan terminar.
            sesion[ClaveUsuario] = new UsuarioDTO
            {
                IdUsuario = usuario.IdUsuario,
                Nombre = usuario.Nombre,
                Apellido = usuario.Apellido,
                Correo = usuario.Correo,
                Confirmado = usuario.Confirmado
            };
        }

        /// <summary>Quién está adentro, o null si no entró nadie.</summary>
        public static UsuarioDTO Actual(HttpSessionStateBase sesion)
        {
            return sesion == null ? null : sesion[ClaveUsuario] as UsuarioDTO;
        }

        public static bool HayUsuario(HttpSessionStateBase sesion)
        {
            return Actual(sesion) != null;
        }

        /// <summary>
        /// Cierra la sesión. Se sacan los datos del usuario y además se abandona
        /// la sesión entera: si más adelante alguien guarda otra cosa en Session,
        /// no queda colgada después de salir.
        /// </summary>
        public static void Cerrar(HttpSessionStateBase sesion)
        {
            if (sesion == null)
            {
                return;
            }

            sesion.Remove(ClaveUsuario);
            sesion.Clear();
            sesion.Abandon();
        }
    }
}
