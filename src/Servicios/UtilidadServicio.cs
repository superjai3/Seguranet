using System.Security.Cryptography;
using System.Text;

namespace Seguranet.Servicios;

public static class UtilidadServicio
{
    /// <summary>
    /// Hash de la clave.
    ///
    /// Se conserva SHA-256 en hexadecimal, exactamente como lo hacía el proyecto
    /// original, porque las claves de los usuarios que ya existen están guardadas
    /// así: cambiar el algoritmo acá los dejaría a todos sin poder entrar.
    ///
    /// PENDIENTE, y está en AUDITORIA.md: SHA-256 a secas no es lo indicado para
    /// contraseñas —es rápido a propósito, que es justo lo que no se quiere—. Lo
    /// correcto es PBKDF2, bcrypt o Argon2, con sal por usuario. La migración se
    /// puede hacer sin echar a nadie: al validar bien con el hash viejo, se
    /// vuelve a guardar con el nuevo.
    /// </summary>
    public static string ConvertirSHA256(string texto)
    {
        var bytes = SHA256.HashData(Encoding.UTF8.GetBytes(texto));

        var hash = new StringBuilder(bytes.Length * 2);
        foreach (var b in bytes)
        {
            hash.Append(b.ToString("X2"));
        }
        return hash.ToString();
    }

    /// <summary>
    /// Token para los enlaces de confirmación y de restablecimiento.
    ///
    /// El original usaba <c>Guid.NewGuid()</c>. Un GUID identifica, pero no está
    /// pensado para ser difícil de adivinar, y acá el token es lo único que
    /// protege el cambio de clave de una cuenta. Estos son 32 bytes de un
    /// generador criptográfico.
    /// </summary>
    public static string GenerarToken()
    {
        var bytes = RandomNumberGenerator.GetBytes(32);
        return Convert.ToHexString(bytes).ToLowerInvariant();
    }
}
