using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;

namespace Seguranet.Models
{
    public class UsuarioDTO
    {
        public int IdUsuario { get; set; }
        public string Nombre { get; set; }
        public string Apellido { get; set; }
        public string Dni { get; set; }
        public string Correo { get; set; }
        public string Clave { get; set; }
        public string ConfirmacionClave { get; set; }
        public bool Restablecer { get; set; }
        public bool Confirmado { get; set; }
        public string Token { get; set; }
    }
}



