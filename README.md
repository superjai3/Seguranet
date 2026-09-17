# Seguranet
Desarrollo de CRM para la gestión de pólizas para Productores y Brokers de seguros.

## Verlo en línea

Hay una **demo estática** de las páginas públicas en

**https://superjai3.github.io/Seguranet/**

Es lo que el sitio se ve, no lo que el sitio hace: GitHub Pages sólo sirve
archivos, y esto es ASP.NET MVC 5, que necesita IIS. Queda afuera todo lo que
depende del servidor —el área de cuenta, que además consulta SQL Server, y el
POST del formulario de contacto—. El formulario de la página de Contacto es un
iframe de un servicio externo, así que ahí sí funciona.

Las páginas viven en `docs/` y **no se editan a mano**: salen de las vistas
Razor con

```
python3 tools/exportar-demo.py
```

Después de tocar algo en `Views/Home/` o en `Views/Shared/_Layout.cshtml`, hay
que volver a correrlo y comitear `docs/`, o la demo queda vieja.

Para publicarla: *Settings → Pages → Source: Deploy from a branch*, rama `main`
y carpeta `/docs`.

Si lo que se quiere es el sitio completo andando —cotizador, cuentas, base—, eso
no es Pages: va a un App Service de Windows en Azure, o a cualquier IIS.

## Cómo compilarlo

ASP.NET MVC 5 sobre .NET Framework 4.6.2. Hace falta Visual Studio 2022 con la
carga de trabajo **ASP.NET y desarrollo web** y el **targeting pack de .NET
Framework 4.6.2**.

Los paquetes de NuGet **no están versionados** —antes sí lo estaban, y eran 590 MB—,
así que después de clonar hay que restaurarlos una vez:

```
nuget restore Seguranet.sln
```

Desde Visual Studio alcanza con abrir la solución: restaura solo al compilar.

Si aparece *«Este proyecto hace referencia a los paquetes NuGet que faltan»*, es
eso: falta el restore.

El estado del sitio y lo que queda por hacer está en [AUDITORIA.md](AUDITORIA.md).

![image](https://github.com/user-attachments/assets/e1e84ed3-b511-40ed-9aae-38d8caa66404)

![image](https://github.com/user-attachments/assets/377584e1-93b3-4e1c-96ed-2bb0847dad6e)

![image](https://github.com/user-attachments/assets/6daee27a-4e52-4040-a103-4887645c2a50)

![image](https://github.com/user-attachments/assets/747e75aa-25d9-484d-b62a-9f3abdde2fe3)

![image](https://github.com/user-attachments/assets/05c96420-d39e-4a46-bea3-6e69e2f6150d)

![image](https://github.com/user-attachments/assets/9f3aa02c-c849-4ff1-80cc-c6a6f9b3de73)

![image](https://github.com/user-attachments/assets/3db808bf-9f3b-4c37-8242-bb15d7fc5b23)




