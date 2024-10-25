using System.ComponentModel.DataAnnotations;

namespace Seguranet.Models
{
    public class ConsultaDTO
    {
        [Required(ErrorMessage = "El nombre es obligatorio.")]
        public string Nombre { get; set; }

        [Required(ErrorMessage = "El apellido es obligatorio.")]
        public string Apellido { get; set; }

        [Required(ErrorMessage = "El correo es obligatorio.")]
        [EmailAddress(ErrorMessage = "Formato de correo no válido.")]
        public string Correo { get; set; }

        [Required(ErrorMessage = "El teléfono es obligatorio.")]
        public string Telefono { get; set; }

        [Required(ErrorMessage = "El motivo de la consulta es obligatorio.")]
        public string Motivo { get; set; }

        [Required(ErrorMessage = "El mensaje es obligatorio.")]
        public string Mensaje { get; set; }
    }
}
