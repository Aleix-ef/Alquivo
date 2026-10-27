# Despliegue legal y conservación de logs — 27/10/2026

## Resultado

Entrega de código `050731bc131b7e4a4f2d8f16b7fb86217b79b37a`, activa en el VPS desde aproximadamente **06:25 UTC**. Revisión legal **2026-10-27-r1** sincronizada en frontend/backend. Publicada también mediante avance rápido en `release/alquivo-beta-validation`; Cloudflare ya sirve esa revisión en la página de privacidad de la landing.

Se partió de la release efectiva `ae5309f4cf17142ec0ad675e756f855dbe2428d4`, en un worktree aislado. Solo se incorporaron textos, presentación/aceptación legal, compatibilidad del soporte y logging nativo. No se desplegó el árbol principal completo ni su trabajo pendiente de IA.

## Textos y elecciones

- Pie habitual con **Privacidad** e **Información legal**, sin identidad/domicilio como bloque visible. El aviso legal permite acceder a todas las condiciones; identidad completa conservada donde corresponde.
- Primera capa de privacidad en desplegable cerrado accesible. Registro con casilla de condiciones/encargo obligatoria y no premarcada; versión vigente validada por Laravel y evidencia cifrada. No se modificaron aceptaciones existentes.
- Soporte informa sin exigir una casilla de consentimiento universal. El antiguo campo sigue siendo compatible si un cliente anterior lo envía. Sesión, autorización, CSRF, antispam y antivirus no se relajan.
- Gmail no figura como custodio de consultas históricas de clientes, conforme a la aclaración del titular. La landing explica que no hay reenvío a Gmail; no se han leído ni borrado mensajes ni alterado el Gmail administrativo/de alertas.
- Zimbra: carpeta «Soporte cerrado» creada según el titular; conservación y revisión **manual**, no purga automática supuestamente instalada.
- Avisos de IA, sus versiones y elecciones separadas no cambian. Los textos no habilitan funciones ni acreditan contratos privados.

Archivo legal inmutable: `docs/legal-revisions/2026-10-27-r1.json`.
SHA-256: `b8eb87498730cf821f89b00d17ff128aaeab545e47bbdb44a4e955773dfcd9db`.
SHA-256 del artefacto de código desplegado: `954d78c8d4ae39e58ac85887790fadbae2d590b27eeeb816af6ec11c889b7bac`.

## Conservación aplicada

Los siete contenedores usan efectivamente `journald`, con etiqueta de servicio, sin copiar variables de entorno. Política del journal del VPS: **30 días**, rotación diaria, **256 MB** y reserva de 1 GB. Timer horario habilitado/activo; servicio ejecutado con `Result=success` y `ExecMainStatus=0`. Verificado con un mensaje sintético aislado, no leyendo logs de usuarios.

La política abarca el journal del host, no solo los contenedores. Rotación/limpieza por bloques: admite un margen hasta el ciclo siguiente y puede conservar menos historial por espacio. No es borrado individual exacto al segundo. Laravel y auditoría mantienen sus plazos independientes de 14/30 días; proveedores y copias, los suyos. El controlador por defecto de Docker sigue siendo `local`; no se reinició Docker ni se truncaron sus archivos.

No quedan contenedores detenidos del proyecto conservando los controladores anteriores. Se preservaron imágenes de reversión, volúmenes, archivos y claves. Uso del journal tras la entrega: 73,9 MB.

## Validación y operación

- **599 pruebas backend correctas**, 4.101 aserciones; dos pruebas de IA real omitidas intencionadamente. Base SQLite en memoria y transportes sintéticos, sin inferencias facturadas.
- **217 pruebas frontend** y **3 pruebas de configuración de logs** correctas.
- Compilación de app/SSR y SEO de diez páginas correctos; preview aislado de las cuatro páginas de landing correcto. Pint, verificación de unidades systemd, archivo legal y comprobación de patrones conocidos de secretos correctos.
- Copia externa nueva y completada a **06:08:40 UTC**, antes de las recreaciones. No se realizó otra restauración en esta entrega; las restauraciones aisladas anteriores permanecen documentadas.
- HTTPS local al dominio real: 200 para salud, acceso, cinco páginas legales y configuración/planes públicos; 401 sin sesión para cuenta, conversaciones y documentos.
- Las cinco páginas legales de la app muestran la revisión nueva y no el aviso de borrador. Se esperó un evento genuino del scheduler posterior a la recreación.
- Monitor final sin incidencias observadas. Temporizadores de monitor, copia, vigilancia de copia y logs activos; firewall activo. Ejecución real del monitor con código 0, incluida su señal externa configurada.
- Hash de entorno privado y reglas de acceso IPv4/IPv6 idénticos antes/después. Sin migraciones de datos, cambios de secretos, permisos o usuarios; acceso público **todavía restringido**.
- Luna/routing, límites, IA validada restringida, altas por IA deshabilitadas, ausencia de captura de calidad, Document AI real deshabilitada y pagos deshabilitados: sin cambios.

No había navegador conectado: render y pruebas automatizadas no se presentan como inspección visual/interactiva humana. Estas pruebas no garantizan ausencia de vulnerabilidades ni certifican cumplimiento jurídico.

## Incidencias del procedimiento y reversión

Hubo **dos reversiones controladas**, preservando la versión anterior, por defectos del verificador de despliegue, no por fallos funcionales detectados en la aplicación:

1. Una búsqueda demasiado amplia de «borrador» confundía la explicación válida de borradores de IA con el aviso legal provisional. Se comprueba ahora el aviso exacto, sin cambiar el contenido para satisfacer el verificador.
2. El control operativo se ejecutaba antes de reanudar el temporizador de copia que el propio despliegue había pausado. Se corrigió el orden: reanudar, comprobar actividad y después ejecutar el monitor.

El tercer intento completó todos los controles. El script disponía de bloqueo de copia/despliegue, comprobación de artefacto, copia reciente, retorno de imágenes/política del host y reanudación independiente de temporizadores. Su SHA-256 final es `4b86a0f8b69845796d15c3d476d3df298bc2843d12c46a4da3538f7aaac71040`. Se verificó después que el temporizador transitorio de reanudación había quedado inactivo. Las recreaciones implicaron interrupciones breves.

## Publicación y límites

Cloudflare sirve `2026-10-27-r1` en **privacidad y aviso legal** de la landing. Verificados por HTTPS el formulario con email/nombre/rango de inmuebles, consentimiento obligatorio no premarcado, desplegable de privacidad y clave pública de Turnstile configurada en modo de publicación, no preview. El endpoint conserva HTTP 405 para GET. No se enviaron solicitudes ni se consultaron inscripciones. La landing mantiene **lista de espera**, no registro abierto. La entrega no abre firewall ni autoriza acceso público a la aplicación.

Los apartados históricos de las guías reflejan sus fechas y no sustituyen este estado. Acuerdos privados, comprobaciones de las cuentas proveedoras y operación manual del buzón siguen separados: publicar los textos no los da por completados. No se ha certificado seguridad absoluta ni ausencia de obligaciones legales.

