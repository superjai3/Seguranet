using Seguranet.Models;

namespace Seguranet.Datos;

/// <summary>
/// Las consultas que llegan por el formulario de contacto.
///
/// Se guardan siempre, aunque el envío del correo falle. Es a propósito: si el
/// aviso por mail es lo único que existe, una casilla mal configurada o un corte
/// de SMTP se traduce en consultas perdidas sin que nadie se entere.
/// </summary>
public class DBConsulta
{
    private readonly FabricaConexion _fabrica;

    public DBConsulta(FabricaConexion fabrica) => _fabrica = fabrica;

    public bool Guardar(ConsultaDTO consulta)
    {
        using var conexion = _fabrica.Abrir();
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = """
            INSERT INTO ConsultasContacto (Nombre, Apellido, Correo, Telefono, Motivo, Mensaje, Estado)
            VALUES (@nombre, @apellido, @correo, @telefono, @motivo, @mensaje, 'Nueva')
            """;
        cmd.Parameters.AddWithValue("@nombre", consulta.Nombre);
        cmd.Parameters.AddWithValue("@apellido", consulta.Apellido);
        cmd.Parameters.AddWithValue("@correo", consulta.Correo);
        cmd.Parameters.AddWithValue("@telefono", consulta.Telefono);
        cmd.Parameters.AddWithValue("@motivo", consulta.Motivo);
        cmd.Parameters.AddWithValue("@mensaje", consulta.Mensaje);

        return cmd.ExecuteNonQuery() > 0;
    }
}
