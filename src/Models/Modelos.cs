using System.ComponentModel.DataAnnotations;

namespace Seguranet.Models;

/*
   Los cuatro modelos del proyecto, juntos porque entre los cuatro no llegan a
   cien líneas y separarlos en cuatro archivos sólo obligaba a abrir cuatro.

   Cambio respecto del original: las cadenas nacen en "" en vez de quedar en
   null. Con `Nullable` activado, un `string` sin inicializar es un aviso del
   compilador, y en tiempo de ejecución era una excepción esperando a que
   alguien mandara el formulario a medias.
*/

public class UsuarioDTO
{
    public int IdUsuario { get; set; }
    public string Nombre { get; set; } = "";
    public string Apellido { get; set; } = "";
    public string Dni { get; set; } = "";
    public string Correo { get; set; } = "";
    public string Clave { get; set; } = "";
    public string ConfirmacionClave { get; set; } = "";
    public bool Restablecer { get; set; }
    public bool Confirmado { get; set; }
    public string Token { get; set; } = "";
}

public class ConsultaDTO
{
    [Required(ErrorMessage = "El nombre es obligatorio.")]
    public string Nombre { get; set; } = "";

    [Required(ErrorMessage = "El apellido es obligatorio.")]
    public string Apellido { get; set; } = "";

    [Required(ErrorMessage = "El correo es obligatorio.")]
    [EmailAddress(ErrorMessage = "Formato de correo no válido.")]
    public string Correo { get; set; } = "";

    [Required(ErrorMessage = "El teléfono es obligatorio.")]
    public string Telefono { get; set; } = "";

    [Required(ErrorMessage = "El motivo de la consulta es obligatorio.")]
    public string Motivo { get; set; } = "";

    [Required(ErrorMessage = "El mensaje es obligatorio.")]
    public string Mensaje { get; set; } = "";
}

public class ContactoViewModel
{
    [Required(ErrorMessage = "El nombre es obligatorio.")]
    public string Nombre { get; set; } = "";

    [Required(ErrorMessage = "El teléfono es obligatorio.")]
    [Phone(ErrorMessage = "El teléfono no es válido.")]
    public string Telefono { get; set; } = "";

    [Required(ErrorMessage = "El correo electrónico es obligatorio.")]
    [EmailAddress(ErrorMessage = "El correo no es válido.")]
    public string Email { get; set; } = "";

    [Required(ErrorMessage = "El tipo de consulta es obligatorio.")]
    public string TipoConsulta { get; set; } = "";

    [Required(ErrorMessage = "El mensaje es obligatorio.")]
    public string Mensaje { get; set; } = "";
}

public class CorreoDTO
{
    public string Para { get; set; } = "";
    public string Asunto { get; set; } = "";
    public string Contenido { get; set; } = "";
}
