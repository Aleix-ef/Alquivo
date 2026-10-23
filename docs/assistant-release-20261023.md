# Entrega acotada del asistente — 23/10/2026

## Alcance

Derivada de la entrega de seguridad PHP `2b552eff41bf053195c6feed8f0564b90380ed57`, sin arrastrar el árbol de trabajo principal. Incluye los cambios de consultas/listados y tiempos evaluados el 23/10: filtros de ciudad sobre la cartera autorizada, distinción de recuento/listado/búsqueda, proyección mínima de contactos, continuación de filtros/página y límite temporal monotónico compartido entre rondas y fallback. Las fuentes y cálculos de cobros/gastos se conservan.

No incluye captura/revisión de conversaciones, sus endpoints, migraciones ni permisos. Se retiran únicamente los dos hooks de instrumentación de calidad del orquestador copiado; no se alteran instrucciones, tools, validaciones, proveedor, parser, routing ni respuestas. No hay cambios de frontend, esquema, MFA, cuentas, secretos, reglas de red ni textos legales en esta entrega. Los archivos inertes de reproducción legal existentes en la entrega anterior no activan captura.

Se mantienen Luna principal, Sol sólo por error técnico/formato, Astra apagado, cuatro rondas, presupuesto, cifrado, aislamiento, consentimiento y confirmación por endpoint separado. La creación de inmuebles/contactos/contratos por IA sigue en revisión y no se habilita. Document AI real permanece fuera del alcance.

## Evidencia

- Evaluación sintética previa: 248 ejecuciones; revisión de Codex 241 PASS, 7 SAFE y 0 DANGEROUS. No sustituye revisión humana; 18 discrepancias grader/Codex pendientes de contraste. Véase `ai-validation-20261023.md`.
- Entrega aislada: **574 pruebas backend correctas, 3.933 aserciones y dos skips live deliberados**; **210 frontend**, build cliente/SSR, SEO de diez páginas, formato, archivo legal y control conservador de secretos correctos.
- Una primera pasada detectó dependencias accidentales de la instrumentación excluida; se retiraron sus dos hooks y se repitió la suite completa. No se ha desplegado la versión fallida ni modificado expectativas para ocultar fallos.
- Clave del VPS: consultas de lectura de metadatos de Luna y Sol con HTTP 200, sin inferencias, datos de usuarios ni gasto. Esta comprobación acredita acceso a modelos, no saldo, cuota de generación o éxito de un turno.
- Configuración comprobada: `fast`/Luna, fallback técnico Sol, sin override de razonamiento, cuatro rondas, techo global 5 USD/mes, Astra apagado. Flags públicos de IA y creación apagados en el preflight.

## Cierre de validación pendiente

No activar ni marcar validación pública sólo por desplegar el código. Se ha solicitado autorización de hasta 0,10 USD para un smoke de producción con datos sintéticos y control acumulado; no se reinician presupuestos anteriores. Faltan el flujo autenticado con propuestas en el VPS y contraste humano de la muestra. El firewall sigue restringido y no se abre la beta desde esta entrega. La revisión de proveedores/datos y el recorrido general de lanzamiento se cierran por separado.
