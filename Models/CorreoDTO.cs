﻿using System;
using System.Collections.Generic;
using System.Linq;
using System.Security.Cryptography.X509Certificates;
using System.Web;

namespace Seguranet.Models
{
    public class CorreoDTO
    {
        public string Para { get; set;}
        public string Asunto { get; set;}
        public string Contenido { get; set;}
    }
}