using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;
using System.Configuration;
using Seguranet.Models;
using System.Data;
using System.Data.SqlClient;
using System.Linq.Expressions;

namespace Seguranet.Models.Datos
{
    public class DBUsuario
    {
        private static string CadenaSQL = ConfigurationManager.ConnectionStrings["DBSeguranetContext"].ConnectionString;
        //private static string CadenaSQL = "Server=Mito\\jaime; DataBase=DBSeguranet; Trusted_Connection=True; TrustServerCertificate=True;";

        public static bool Registrar(UsuarioDTO usuario)
        {
            bool respuesta = false;
            try
            {
                using (SqlConnection oconexion = new SqlConnection(CadenaSQL))
                {
                    string query = "insert into Usuario(Nombre,Apellido,Dni,Correo,Clave,Restablecer,Confirmado,Token) ";
                    query += " values(@nombre,@apellido,@dni,@correo,@clave,@restablecer,@confirmado,@token)";

                    SqlCommand cmd = new SqlCommand(query, oconexion);
                    //cmd.Parameters.AddWithValue("@nombre", usuario.Nombre);
                    //cmd.Parameters.AddWithValue("@apellido", usuario.Apellido);
                    //cmd.Parameters.AddWithValue("@dni", usuario.Dni);
                    //cmd.Parameters.AddWithValue("@correo", usuario.Correo);
                    cmd.Parameters.Add("@nombre", SqlDbType.VarChar, 50).Value = usuario.Nombre;///////////////////////////////////////////////////
                    cmd.Parameters.Add("@apellido", SqlDbType.VarChar, 50).Value = usuario.Apellido;
                    cmd.Parameters.Add("@dni", SqlDbType.VarChar, 10).Value = usuario.Dni;
                    cmd.Parameters.Add("@correo", SqlDbType.VarChar, 50).Value = usuario.Correo;

                    //cmd.Parameters.AddWithValue("@clave", usuario.Clave);
                    cmd.Parameters.Add("@clave", SqlDbType.VarChar, 200).Value = usuario.Clave;  // La clave ya está encriptada/haseada

                    //cmd.Parameters.AddWithValue("@restablecer", usuario.Restablecer);
                    //cmd.Parameters.AddWithValue("@confirmado", usuario.Confirmado);
                    //cmd.Parameters.AddWithValue("@token", usuario.Token);
                    cmd.Parameters.Add("@restablecer", SqlDbType.Bit).Value = usuario.Restablecer;  // Uso de SqlDbType.Bit
                    cmd.Parameters.Add("@confirmado", SqlDbType.Bit).Value = usuario.Confirmado;
                    cmd.Parameters.Add("@token", SqlDbType.VarChar, 200).Value = usuario.Token;

                    cmd.CommandType = CommandType.Text;

                    oconexion.Open();

                    int filasAfectadas = cmd.ExecuteNonQuery();
                    if (filasAfectadas > 0) respuesta = true;
                }

                return respuesta;
            }
            catch (Exception ex)
            {
                throw ex;
            }
        }

        public static UsuarioDTO Validar(string correo, string clave)
        {
            UsuarioDTO usuario = null;
            try
            {
                using (SqlConnection oconexion = new SqlConnection(CadenaSQL))
                {
                    string query = "select Nombre,Restablecer,Confirmado from Usuario ";
                    query += " where Correo=@correo and Clave = @clave";

                    SqlCommand cmd = new SqlCommand(query, oconexion);
                    //cmd.Parameters.AddWithValue("@correo", correo);
                    cmd.Parameters.Add("@correo", SqlDbType.VarChar, 50).Value = correo;

                    //cmd.Parameters.AddWithValue("@clave", clave);
                    cmd.Parameters.Add("@clave", SqlDbType.VarChar, 200).Value = clave;  // Clave hasheada
                    cmd.CommandType = CommandType.Text;

                    oconexion.Open();

                    using (SqlDataReader dr = cmd.ExecuteReader())
                    {
                        if (dr.Read())
                        {
                            usuario = new UsuarioDTO()
                            {
                                Nombre = dr["Nombre"].ToString(),
                                Restablecer = (bool)dr["Restablecer"],
                                Confirmado = (bool)dr["Confirmado"]
                            };
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                throw ex;
            }
            return usuario;
        }

        public static UsuarioDTO Obtener(string correo)
        {
            UsuarioDTO usuario = null;
            try
            {
                using (SqlConnection oconexion = new SqlConnection(CadenaSQL))
                {
                    string query = "select Nombre,Clave,Restablecer,Confirmado,Token from Usuario where Correo=@correo";

                    SqlCommand cmd = new SqlCommand(query, oconexion);
                    // cmd.Parameters.AddWithValue("@correo", correo);
                    cmd.Parameters.Add("@correo", SqlDbType.VarChar, 50).Value = correo;

                    cmd.CommandType = CommandType.Text;

                    oconexion.Open();

                    using (SqlDataReader dr = cmd.ExecuteReader())
                    {
                        if (dr.Read())
                        {
                            usuario = new UsuarioDTO()
                            {
                                Nombre = dr["Nombre"].ToString(),
                                Clave = dr["Clave"].ToString(),
                                Restablecer = (bool)dr["Restablecer"],
                                Confirmado = (bool)dr["Confirmado"],
                                Token = dr["Token"].ToString()
                            };
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                throw ex;
            }
            return usuario;
        }

        public static bool RestablecerActualizar(int restablecer, string clave, string token)
        {
            bool respuesta = false;
            try
            {
                using (SqlConnection oconexion = new SqlConnection(CadenaSQL))
                {
                    string query = @"update Usuario set " +
                        "Restablecer=@restablecer, " +
                        "Clave=@clave " +
                        "where Token=@token";

                    SqlCommand cmd = new SqlCommand(query, oconexion);
                    //cmd.Parameters.AddWithValue("@restablecer", restablecer);
                    cmd.Parameters.Add("@restablecer", SqlDbType.Bit).Value = restablecer;

                    //cmd.Parameters.AddWithValue("@clave", clave);
                    cmd.Parameters.Add("@clave", SqlDbType.VarChar, 200).Value = clave;

                    //cmd.Parameters.AddWithValue("@token", token);
                    cmd.Parameters.Add("@token", SqlDbType.VarChar, 200).Value = token;

                    cmd.CommandType = CommandType.Text;

                    oconexion.Open();

                    int filasAfectadas = cmd.ExecuteNonQuery();
                    if (filasAfectadas > 0) respuesta = true;
                }

                return respuesta;
            }
            catch (Exception ex)
            {
                throw ex;
            }
        }

        public static bool Confirmar(string token)
        {
            bool respuesta = false;
            try
            {
                using (SqlConnection oconexion = new SqlConnection(CadenaSQL))
                {
                    string query = @"update Usuario set " +
                        "Confirmado= 1 " +
                        "where Token=@token";

                    SqlCommand cmd = new SqlCommand(query, oconexion);
                    //cmd.Parameters.AddWithValue("@token", token);
                    cmd.Parameters.Add("@token", SqlDbType.VarChar, 200).Value = token;

                    cmd.CommandType = CommandType.Text;

                    oconexion.Open();

                    int filasAfectadas = cmd.ExecuteNonQuery();
                    if (filasAfectadas > 0) respuesta = true;
                }

                return respuesta;
            }
            catch (Exception ex)
            {
                throw ex;
            }
        }

    }
}