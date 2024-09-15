using System;
using System.Collections.Generic;
using System.Linq;
using System.Web;

using Seguranet.Models;
using MailKit.Security;
using MailKit;
using MailKit.Net.Smtp;
using System.Linq.Expressions;
using MimeKit;
using MimeKit.Text;



namespace Seguranet.Servicios
{
    public static class CorreoServicio
    {
        private static string _Host = "smtp.gmail.com";
        private static int _Puerto = 587;
        
        private static string _NombreEnvia = "Seguranet";
        private static string _Correo = "jaimevaldes71@gmail.com";
        private static string _Clave = "pyxayrbkspzylvqb";

        public static bool Enviar(CorreoDTO correodto)
        {
            try
            {
                var email = new MimeMessage();

                email.From.Add(new MailboxAddress(_NombreEnvia, _Correo));
                email.To.Add(MailboxAddress.Parse(correodto.Para));
                email.Subject = correodto.Asunto;
                email.Body = new TextPart(MimeKit.Text.TextFormat.Html)
                {
                    Text = correodto.Contenido
                };

                var smtp = new SmtpClient();
                smtp.Connect(_Host, _Puerto, SecureSocketOptions.StartTls);

                smtp.Authenticate(_Correo, _Clave);
                smtp.Send(email);
                smtp.Disconnect(true);
                return true;
            }
                catch
            {
                return false;
            }
            
        }
    }
}