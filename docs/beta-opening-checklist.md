# Apertura de la beta MVP — cierre del 23/10/2026

## Estado efectivo — 27/10/2026

Textos `2026-10-27-r1` publicados en app/landing; logging temporal instalado. **HTTPS IPv4 público abierto por petición del titular**, confirmado desde datos móviles, sin cambios de app/IA/secretos. Firewall interno e IPv6 protegidos, reversión transitoria cancelada y monitor sin incidencias. La landing permanece en lista de espera mientras se revisan los acuerdos privados: apertura de red no acredita cierre de privacidad ni campaña de captación. [Evidencia](public-web-opening-20261027.md), [guía breve](provider-closeout-quick-guide.md).

La prueba funcional final ya fue dada por completada por el titular el 26/10; no se solicita repetirla por defecto. Quedan por confirmar las cuentas/acuerdos y procedimientos privados donde no haya evidencia, segunda custodia y monitor externo HTTPS continuo. Los checks inferiores son checkpoints históricos y no sustituyen este resumen.


Objetivo: admitir los primeros propietarios, no completar todo el roadmap. Este documento resume el estado vigente; los checkpoints anteriores de la guía de lanzamiento son históricos cuando lo contradigan. No es una certificación jurídica ni de invulnerabilidad, ni autoriza abrir el firewall.

**Avance comunicado el 26/10/2026:** el titular da por completada la prueba final de producción del resumen de lanzamiento; no se exige repetir por defecto todo ese recorrido. Se termina localmente la edición legal **2026-10-26**, con enlaces discretos y elecciones separadas; falta su entrega conjunta en app y publicación de Pages. El cierre editorial no acredita contratos privados ni conservación operativa. [Edición y alcance](legal-beta.md).

## Ya disponible: no volver a contratar ni reconstruir

- Landing pública, dominio y aplicación HTTPS en OVHcloud Gravelines. Release activa `ae5309f4cf17142ec0ad675e756f855dbe2428d4`; la aplicación sigue restringida a la conexión de pruebas.
- Gestión manual de inmuebles, contactos, contratos, cobros/gastos, documentos, calendario y tickets. Plan beta gratuito: 50 inmuebles y 5 GB, con cuotas de IA.
- Buzón OVH/Zimbra y Resend para envío automático: correo técnico por la cola y aviso de copia recibidos por el titular. Esto no acredita cada flujo de correo de cuenta.
- Copia cifrada externa, programación, alerta y dos restauraciones aisladas comprobadas. Material de recuperación descargado y descifrado fuera del VPS; falta confirmar segunda custodia independiente.
- IA activa para las pruebas autorizadas: Luna principal, Sol solo para error técnico/formato, cuatro rondas y techo global de 5 USD/mes. No cambiar el modelo para resolver un fallo de cortesía.
- Revisión de solo lectura de este bloque: controles de preparación sin fallos, cola y trabajos fallidos a cero, rango de cobro y compatibilidad anteriores correctos. No se leen conversaciones ni documentos de usuarios.

## Cierre mínimo antes de recibir datos reales

### 1. Privacidad efectiva, no solo páginas legales — titular + ayuda técnica

- [ ] Conservar los acuerdos aplicables y su vinculación a las cuentas de OVH (VPS/buzón), Hetzner, Cloudflare, Resend y OpenAI. Confirmar la entidad y las opciones de datos de OpenAI; no prometer retención cero ni residencia UE no contratadas.
  - Avance 26/10: el titular comunica OpenAI OpCo, LLC y aporta Sharing con las tres opciones en `Disabled` y Data retention con `API call logging: Enabled per call`. El código local y el de la última entrega registrada fuerzan `store=false`; no acredita ZDR. Pendientes persistencia/organización de producción, confirmación del ajuste de logging recomendado y acuerdo aplicable; no se da el punto por cerrado. [Registro de alcance](provider-privacy-checklist.md).
- [ ] Completar conservación operativa y aplicar los procedimientos privados de derechos, incidentes y borrado, con inventario y evaluación de riesgos proporcionados.
  - [x] 27/10: el titular confirma Gmail solo de pruebas; no hay historial de clientes que migrar desde ese buzón.
  - [x] 27/10: carpeta «Soporte cerrado» creada en Zimbra, según el titular. Revisión mensual manual prevista, sin purga automática ni borrados ejecutados por esta tarea.
  - [ ] Aplicar y verificar la caducidad temporal de logs en la entrega: configuración nativa preparada, siete contenedores todavía con rotación por tamaño. [Procedimiento](log-retention.md).
- [ ] Publicar coherentemente app y landing. La edición local `2026-10-26` ya tiene revisión editorial y archivo inmutable; la app continúa en la revisión anterior hasta entregarla. El indicador editorial no acredita aceptación de acuerdos ni conservación operativa y no sustituye ese cierre.

La revisión profesional de protección de datos es recomendable antes de recibir contratos de clientes. [Detalles y evidencias pendientes](provider-privacy-checklist.md), [procedimientos](privacy-operations.md), [AEPD: relación responsable/encargado](https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/8-responsable-y-encargado-del-tratamiento).

### 2. Cerrar únicamente los recorridos HTTPS no confirmados — titular + ayuda técnica

El titular ha probado la aplicación y considera aceptable la IA para el MVP. No equivale a acreditar automáticamente todos los checks siguientes ni exige repetir los ya confirmados. Registrar los pendientes con cuentas y documentos exclusivamente ficticios:

- [ ] Registro/aceptación, verificación y recuperación recibidas y enlaces utilizables en el dominio de producción; MFA con/sin recordar navegador y revocación.
- [ ] Un ciclo manual de inmueble/contacto/contrato, cobro parcial y gasto; rango de cobro correcto.
- [ ] Subida/descarga de documento, denegación desde otra cuenta, exportación y eliminación de la cuenta de prueba.
- [ ] Ticket, aviso recibido por soporte y respuesta visible en la misma conversación.
- [ ] En móvil: registro, navegación, formularios y propuesta de IA utilizables sin bloqueos. Los builds/tests no sustituyen esta revisión visual.

No repetir evaluaciones extensas ni consumir nuevos presupuestos de IA por este checklist. Las muestras sintéticas previas no demuestran imposibilidad absoluta de filtración. Un fallo de autorización, importe/entidad incorrectos o escritura no confirmada requiere investigación antes de abrir; una respuesta torpe a un agradecimiento no bloquea por sí sola el MVP.

### 3. Avisos y recuperación — ayuda técnica + titular

- [x] Alertas internas por espacio, cola/scheduler, antivirus, servicios y otras comprobaciones. Instaladas en el VPS, timer activo y correo técnico recibido en Zimbra. [Procedimiento](operational-alerts.md).
- [x] Monitor externo conectado: Healthchecks.io/Gmail, periodo 2 minutos y gracia 5. Destino privado configurado, primera señal real y señales de fallo/recuperación aceptadas; vigilancia reanudada tras ensayo breve y protegido, sin parar la app.
- [x] Recepción de ambos avisos externos DOWN/UP en Gmail confirmada por el titular el 23/10/2026. El ensayo fue de señales sintéticas, sin detener la aplicación ni simular siete minutos de ausencia de heartbeat.
- [ ] Añadir comprobación externa del dominio al abrir HTTPS al público; el heartbeat no valida DNS/conectividad entrante. No retirar ahora la restricción de pruebas para facilitarlo.
- [ ] Confirmar segunda custodia protegida del material de recuperación fuera del ordenador habitual y acceso a su contraseña. No compartirlo por chat ni Git.

### 4. Publicar una entrega controlada — ayuda técnica, con autorización de apertura

- [ ] Seleccionar los cambios finales, probar y guardar en GitHub una release/tag reproducible. No publicar todo el árbol sucio ni confundirlo con el commit desplegado.
- [ ] Entregar la corrección local de cortesía si se incluye en esa release, sin incorporar módulos de IA pendientes.
- [ ] Publicar la landing legal actualizada y comprobar CTA hacia el registro cuando se decida admitir altas, conservando la lista de espera.
- [ ] Abrir HTTPS al público **solo con autorización explícita**, conservando protección de base de datos/servicios internos y controles IPv6. Comprobar desde otra red.
- [ ] Acompañar inicialmente a 5–10 propietarios y revisar incidencias antes de aumentar la captación. Explicar beta gratuita y limitaciones, sin promesas de asesoramiento fiscal ni respuestas infalibles.

## No hace falta para esta apertura

Pagos/Stripe live, módulos nuevos, rediseño de arquitectura, inglés, fiscalidad pública, Document AI real, nuevas altas por IA, Enterprise, app móvil nativa o IA perfecta. La revisión opcional de conversaciones no está desplegada: no presentar ese módulo como operativo ni recopilar conversaciones para mejora por defecto.

## Corrección local de cortesía de este bloque

El catálogo cerrado de ayuda no incluía agradecimientos. Se añade `thanks` y la instrucción para distinguir un agradecimiento aislado de una consulta u operación acompañada de «gracias». Respuesta verificada: «¡De nada! Aquí estoy cuando necesites revisar tus alquileres». No se permite texto libre en la ruta de ayuda ni se cambia confirmación, permisos, modelos, cuotas, cifrado o herramientas.

Validación **offline**: 28 pruebas dirigidas/150 aserciones; suite backend 608 correctas/4.245 aserciones y dos skips de IA real; Pint correcto. Son pruebas del contrato/parser y del flujo HTTP con proveedor simulado, no una medición nueva de comprensión de Luna. Cero llamadas reales y cero coste. Corrección sin despliegue/push; firewall y flags intactos. [Estado del VPS](ovh-deployment-status.md).
