using Microsoft.Data.Sqlite;
using Seguranet.Models;

namespace Seguranet.Datos;

/// <summary>
/// Altas, validación y confirmación de usuarios.
///
/// Portado del SQL Server original. Las consultas son las mismas —parametrizadas
/// ya lo estaban, así que no había inyección que arreglar— con tres cambios:
///
///  · `SqlConnection` pasa a `SqliteConnection`, y la conexión llega inyectada
///    en vez de leerse de un campo estático con `ConfigurationManager`.
///  · Los booleanos se leen de `INTEGER`: SQLite no tiene `bit`.
///  · Los métodos dejan de ser estáticos, para que se puedan sustituir en una
///    prueba y para que la conexión entre por el constructor.
/// </summary>
public class DBUsuario
{
    private readonly FabricaConexion _fabrica;

    public DBUsuario(FabricaConexion fabrica) => _fabrica = fabrica;

    public bool Registrar(UsuarioDTO usuario)
    {
        try
        {
            using var conexion = _fabrica.Abrir();
            using var cmd = conexion.CreateCommand();
            cmd.CommandText = """
                INSERT INTO Usuario (Nombre, Apellido, Dni, Correo, Clave, Restablecer, Confirmado, Token)
                VALUES (@nombre, @apellido, @dni, @correo, @clave, @restablecer, @confirmado, @token)
                """;
            cmd.Parameters.AddWithValue("@nombre", usuario.Nombre ?? "");
            cmd.Parameters.AddWithValue("@apellido", usuario.Apellido ?? "");
            cmd.Parameters.AddWithValue("@dni", usuario.Dni ?? "");
            cmd.Parameters.AddWithValue("@correo", usuario.Correo ?? "");
            // Llega ya haseada desde el controlador.
            cmd.Parameters.AddWithValue("@clave", usuario.Clave ?? "");
            cmd.Parameters.AddWithValue("@restablecer", usuario.Restablecer ? 1 : 0);
            cmd.Parameters.AddWithValue("@confirmado", usuario.Confirmado ? 1 : 0);
            cmd.Parameters.AddWithValue("@token", usuario.Token ?? "");

            return cmd.ExecuteNonQuery() > 0;
        }
        catch (SqliteException e) when (e.SqliteErrorCode == 19)
        {
            // Restricción única sobre Correo: ya hay una cuenta con esa casilla.
            // Antes esto no se podía distinguir de un fallo cualquiera porque no
            // existía el índice y el alta duplicada entraba sin protestar.
            return false;
        }
    }

    /// <summary>Devuelve el usuario si el correo y la clave coinciden; si no, null.</summary>
    public UsuarioDTO? Validar(string correo, string clave)
    {
        using var conexion = _fabrica.Abrir();
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = """
            SELECT IdUsuario, Nombre, Apellido, Correo, Restablecer, Confirmado
            FROM Usuario
            WHERE Correo = @correo AND Clave = @clave
            """;
        cmd.Parameters.AddWithValue("@correo", correo ?? "");
        cmd.Parameters.AddWithValue("@clave", clave ?? "");

        using var lector = cmd.ExecuteReader();
        return lector.Read() ? Leer(lector) : null;
    }

    public UsuarioDTO? Obtener(string correo)
    {
        using var conexion = _fabrica.Abrir();
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = """
            SELECT IdUsuario, Nombre, Apellido, Correo, Restablecer, Confirmado
            FROM Usuario
            WHERE Correo = @correo
            """;
        cmd.Parameters.AddWithValue("@correo", correo ?? "");

        using var lector = cmd.ExecuteReader();
        return lector.Read() ? Leer(lector) : null;
    }

    /// <summary>Guarda el token con el que se pide restablecer la clave.</summary>
    public bool MarcarRestablecer(string correo, string token)
    {
        using var conexion = _fabrica.Abrir();
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = "UPDATE Usuario SET Restablecer = 1, Token = @token WHERE Correo = @correo";
        cmd.Parameters.AddWithValue("@token", token);
        cmd.Parameters.AddWithValue("@correo", correo ?? "");
        return cmd.ExecuteNonQuery() > 0;
    }

    /// <summary>
    /// Cambia la clave a partir del token del correo y lo invalida en la misma
    /// operación: un enlace de restablecimiento sirve una sola vez.
    /// </summary>
    public bool RestablecerClave(string token, string claveHaseada)
    {
        using var conexion = _fabrica.Abrir();
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = """
            UPDATE Usuario
            SET Clave = @clave, Restablecer = 0, Token = ''
            WHERE Token = @token AND Token <> '' AND Restablecer = 1
            """;
        cmd.Parameters.AddWithValue("@clave", claveHaseada);
        cmd.Parameters.AddWithValue("@token", token ?? "");
        return cmd.ExecuteNonQuery() > 0;
    }

    /// <summary>Confirma la cuenta con el token del correo de alta.</summary>
    public bool Confirmar(string token)
    {
        using var conexion = _fabrica.Abrir();
        using var cmd = conexion.CreateCommand();
        cmd.CommandText = """
            UPDATE Usuario
            SET Confirmado = 1, Token = ''
            WHERE Token = @token AND Token <> ''
            """;
        cmd.Parameters.AddWithValue("@token", token ?? "");
        return cmd.ExecuteNonQuery() > 0;
    }

    private static UsuarioDTO Leer(SqliteDataReader lector) => new()
    {
        IdUsuario = lector.GetInt32(0),
        Nombre = lector.IsDBNull(1) ? "" : lector.GetString(1),
        Apellido = lector.IsDBNull(2) ? "" : lector.GetString(2),
        Correo = lector.IsDBNull(3) ? "" : lector.GetString(3),
        Restablecer = !lector.IsDBNull(4) && lector.GetInt32(4) == 1,
        Confirmado = !lector.IsDBNull(5) && lector.GetInt32(5) == 1,
    };
}
