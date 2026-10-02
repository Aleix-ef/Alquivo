# Operación de privacidad antes de beta

Preparación de 1 de octubre de 2026. Procedimiento propuesto, **pendiente de aprobación y puesta en práctica por el titular**. No declara cumplimiento ni sustituye contratos/revisión profesional. No guardar aquí solicitudes reales, documentos de identidad, claves o registros de clientes: el repositorio puede ser público.

## Responsabilidad y registros restringidos

El titular es responsable de organizar las tareas y decidir las comunicaciones; contacto `soporte@alquivo.com`. El acceso al equipo de soporte exige autorización explícita, correo verificado y MFA; no da acceso a carteras. Para diagnóstico excepcional, documentar finalidad, persona autorizada y duración; nunca enviar contratos a correo, chats o proveedores de IA por comodidad.

Mantener fuera de Git un registro privado de derechos (referencia, recepción, derecho solicitado, identidad comprobada de forma proporcional, decisión, fecha/respuesta), incidentes (referencia, cronología, sistemas/personas afectados, evaluación, medidas, comunicaciones) y supresiones (referencia mínima y fecha que permitan reaplicar el borrado tras una restauración). Definir y justificar acceso y retención con el asesor; no acumular expedientes indefinidamente. El registro de supresiones es operativo, no existe todavía una herramienta automatizada que lo reaplique.

## Solicitudes de derechos

1. Registrar la recepción y confirmar cómo contactar con quien solicita el derecho, evitando divulgar si existe una cartera ajena.
2. Comprobar identidad proporcionalmente. Preferir la sesión/correo verificado; no pedir DNI por defecto ni recuperar una cuenta protegida solo por un correo declarado. Tener un procedimiento específico si se pierden ambos factores y los códigos.
3. Distinguir datos de cuenta (Alquivo responsable) de datos incorporados por un propietario (Alquivo encargado cuando aplique): trasladar la solicitud al responsable sin facilitar datos de terceros.
4. Responder, con carácter general, en un mes; documentar ampliaciones motivadas y comunicarlas dentro del primer mes. Exportaciones/documentos existentes cubren parte de la devolución; recopilar manualmente el resto de forma segura cuando proceda.
5. Borrar/rectificar únicamente lo autorizado, comprobar reintentos de archivos, tickets/chat y excepciones concretas de conservación. La cuenta elimina su cartera, no las evidencias mínimas restringidas hasta su caducidad. No prometer borrado instantáneo de copias o registros del proveedor.
6. Registrar supresiones para restauraciones y comunicar el resultado. Mantener las excepciones debidamente justificadas, bloqueadas/restringidas cuando corresponda, y eliminarlas al finalizar el plazo aplicable.

## Incidentes

1. Contener y preservar evidencia mínima: limitar el acceso afectado, revocar sesiones/claves cuando proceda, no destruir registros necesarios ni divulgar documentos.
2. Determinar alcance, fechas, categorías de datos, propietarios afectados y riesgo; contactar con los proveedores por su canal de incidentes.
3. Si Alquivo actúa como encargado, avisar sin dilación indebida al propietario responsable; no esperar a agotar un plazo de comunicación a una autoridad.
4. Para tratamientos como responsable, valorar y documentar si debe notificarse a la autoridad (art. 33 RGPD: sin dilación indebida y, de ser posible, dentro de 72 horas desde conocimiento, salvo que sea improbable el riesgo) y a afectados si hay alto riesgo (art. 34). Preparar el canal AEPD y asesoramiento antes de lanzar. No todos los incidentes se comunican automáticamente de la misma forma.
5. Documentar cierre, causas, controles y cambios; verificar recuperaciones y revisar el riesgo.

## Restauración y supresiones

Restaurar en entorno aislado, sin correo/IA/pagos ni acceso de usuarios; verificar base, archivos y **ambas claves**. Aplicar supresiones posteriores a la copia y caducidades de evidencia, chat y tickets antes de reabrir. Revocar sesiones/dispositivos restaurados y reconciliar estado externo que corresponda. Medir tiempos y conservar resultado sin incluir contenido de clientes en informes públicos. Una restauración local no acredita destino externo ni recuperación tras pérdida completa del servidor.

## Proveedores y cambios

Inventario y contratos efectivos de alojamiento, backup, Resend, Cloudflare Email Routing, buzón y OpenAI; revisar entidades, ubicación, garantías, subencargados y finalidad. Confirmar la política de evidencia (1.095 días de referencia técnica), copias, buzón y registros. No asumir que `store=false` elimina los registros de seguridad de OpenAI.

Cerrar los campos de `legalOperator.js`, incrementar la revisión y archivar el artefacto final. Cumplir el aviso de cambios de subencargados/fin de beta prometido en condiciones. Si se trasladan cuentas reales anteriores, resolver su incorporación bajo condiciones vigentes; no sobrescribir o inventar aceptación. No importar las cuentas/documentos de desarrollo a producción.

Fuentes: [RGPD, arts. 12, 28, 32–34](https://www.boe.es/doue/2016/119/L00001-00088.pdf), [LOPDGDD, art. 32](https://www.boe.es/buscar/act.php?id=BOE-A-2018-16673#a32), [OpenAI Docs: tratamiento y conservación](https://developers.openai.com/api/docs/guides/your-data).
