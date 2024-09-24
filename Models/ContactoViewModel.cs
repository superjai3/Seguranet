using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;

namespace Seguranet.Models
{
    public class ContactoViewModel
    {
        public int IdConsulta { get; set; }
        public string Nombre { get; set; }
        public string Email { get; set; }
        public string Telefono { get; set; }
        public string TipoConsulta { get; set; }
        public string Mensaje { get; set; }
    }
}
