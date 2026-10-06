# Ampliación del asistente — primer bloque, 5 de octubre de 2026

Actualización del 6 de octubre: el bloque siguiente conserva el estado histórico del 5 de octubre. Ya se han realizado pruebas sintéticas reales y comprobaciones aisladas con PostgreSQL/Docker; el resultado actual, incluido el cierre parcial de la reevaluación y las restricciones pendientes, está en [evaluaciones](ai-evaluations.md) y [primera entrega beta](beta-0.1.0.md). Las altas de IA no se han abierto globalmente.

Implementación técnica, no validación semántica de Luna ni autorización para abrir la beta. **Cero llamadas reales y cero USD adicionales**. Se mantiene el presupuesto anterior; no se liberan reservas ni se repite la comparación de latencia.

## Alcance para revisión

| Operación | Datos mínimos | Resultado después del botón |
| --- | --- | --- |
| `propose_property` | Nombre, tipo y dirección | Inmueble nuevo; ciudad, compra y valoración opcionales. Una valoración indicada utiliza el registro existente de valoraciones. |
| `propose_contact` | Nombre | Persona por defecto, o empresa si se indica. Correo/teléfono opcionales. No se vincula automáticamente a un contrato. |
| `propose_lease` | Inmueble, contactos existentes, inicio, renta y día de cobro | Contrato **en borrador**, sin activar alquiler ni generar mensualidades. Fin opcional; fianza por defecto 0, visible en la tarjeta. |

Si faltan datos, Laravel devuelve una pregunta concreta sin inventarlos. «Crea una propiedad llamada Casa Muro» requiere preguntar tipo y dirección. No se supone una fecha, importe o día de cobro. Los nombres se resuelven dentro de la cartera: varias coincidencias requieren aclaración. No se pueden usar entidades de otra cartera ni un contacto aún no confirmado.

Las tres altas se anuncian y aparecen en el catálogo **solo para administrador local**, con sus controles existentes de correo verificado/MFA/activación. `ai.actions.creation_enabled=false` permanece en código; los tests lo habilitan exclusivamente en su proceso. No se conceden roles ni se modifican `.env`, MFA o flags globales. No habilitar para usuarios antes de evaluar los nuevos recorridos reales y decidir su apertura.

Siguen disponibles, según plan/consentimiento y gates actuales, las propuestas anteriores de gasto, cobro completo/parcial, teléfono y nota. **Todavía no hay paridad completa con los formularios**: ingresos no vinculados a alquiler, activar/renovar/modificar contratos, modificar inmuebles, borrar registros, subir documentos y otras operaciones no forman parte de esta ampliación. Document AI continúa simulada.

## Límite de confianza y reutilización

El modelo propone datos con schemas estrictos y cerrados. No aporta código, roles, cartera, estado activo de contrato ni una orden de confirmación. `CreationProposalTools`/`CreationProposalActions` utilizan el ciclo común `ActionProposalService`/`ProposalActionRegistry`:

1. Resolución y validación con acciones compartidas por formulario/asistente.
2. Payload/snapshot cifrados, propuesta pendiente y revisión de la tarjeta.
3. Correcciones permitidas → nueva revisión; destinos de contrato bloqueados.
4. Confirmación en endpoint independiente, con bloqueo de cartera/propuesta y comprobación de plan, permisos, entidades, snapshot y payload.
5. Una escritura y recibo recuperable; confirmar otra vez devuelve el mismo resultado.

`CreateProperty`, `CreateContact` y `CreateLease` comparten las reglas manuales; validar una vista previa no crea registros. El asistente rechaza nombres de inmueble/contacto ya existentes, incluso duplicados legítimos: utilizar el formulario manual en esos casos. Un duplicado creado entre preview y confirmación invalida la propuesta. Un contacto eliminado/modificado también invalida el contrato. La tarjeta mantiene el orden revisado de participantes, incluido el primero como principal.

Las tarjetas permiten editar, cancelar, confirmar y recuperar una confirmación cuya respuesta se perdió, sin repetir automáticamente la escritura. Enlaces y eventos de refresco se construyen a partir del tipo e IDs del recibo validado, no de una URL arbitraria del proveedor.

## Memoria breve dentro del chat

`AssistantConversationContext` conserva una **referencia de inmueble**, no sus saldos/contactos/documentos. Usa el último turno completado de la misma conversación/usuario/cartera, durante **30 minutos**. Antes de reutilizarla comprueba que el inmueble existe y está autorizado. Un inmueble nuevo solo proporciona un ID después de una confirmación separada y correcta.

La referencia va en metadata cifrada del historial existente; 30 minutos de uso no equivalen al borrado del historial, que mantiene la retención/revocación existente. Los pasos técnicos conservan únicamente un ID validado. No se añade almacenamiento conversacional en OpenAI: continúa `store=false`, sin Conversations API, `previous_response_id`, embeddings o memoria entre usuarios.

El contexto es auxiliar, no autoridad ni hechos actuales. Una petición nueva explícita prevalece; una consulta general, ambigua o fallida no mantiene indiscriminadamente un inmueble más antiguo. La memoria no rompe empates del buscador ni acredita resolución de escrituras. El inmueble explícito de la ficha abierta conserva prioridad.

Ejemplo cubierto técnicamente: propuesta de cobro parcial de Piso Centro → «¿Quiénes son los inquilinos?». El segundo turno recibe una referencia y vuelve a consultar participantes actuales; el cobro permanece pendiente si no se pulsó Confirmar. **La comprensión de esa frase por Luna necesita evaluación real; un proveedor simulado no la demuestra.**

## Privacidad y comprobaciones

Aviso de chat y revisión legal `2026-10-05`, archivados sin sobrescribir revisiones anteriores. Se explican referencia breve, datos nuevos escritos (direcciones/correos/teléfonos) y alternativa manual sin enviarlos a OpenAI. Requiere nueva aceptación **solo para utilizar IA**, no para acceder a la app; no se atribuye consentimiento a cuentas existentes. El contenido legal sigue siendo borrador, pendiente de revisión jurídica/proveedores efectivos.

Pruebas: 23 regresiones nuevas/200 aserciones; suite ordinaria **505 pruebas, 503 correctas/3.443 aserciones y dos skips live**. Frontend **183 correctas**, build cliente/SSR y SEO de diez páginas correctos. Fixtures sintéticas y HTTP del proveedor simulado/bloqueado. Cubren cifrado, preview sin escritura, cuotas al confirmar, duplicados, targets ajenos/archivados, revisión, orden de participantes, no activación/cobros, cancelación, idempotencia, recibo perdido, memoria aislada/caducada y chat «sí» sin confirmación. Un turno fallido/interrumpido posterior también invalida una referencia antigua: no se salta ese mensaje para recuperar el inmueble de una respuesta anterior.

**Pendientes:** PostgreSQL 17 y aplicación a Docker local (Docker Desktop no respondía), revisión interactiva escritorio/móvil (sin navegador conectado), evaluación real sintética y revisión humana. No contarlos como PASS.

La [documentación oficial de function calling](https://developers.openai.com/api/docs/guides/function-calling) y [estado conversacional](https://developers.openai.com/api/docs/guides/conversation-state) guió schemas cerrados y contexto manual acotado; no se adopta persistencia automática del proveedor.

## Siguiente validación antes de habilitar altas públicas

Necesita presupuesto autorizado nuevo. Reutilizar prompt/orquestador/tools/schemas reales y harness existente, con datos sintéticos:

- Inmueble con céntimos, solo nombre, dirección ausente y duplicado.
- Contacto con/sin teléfono, correo inválido, empresa, ambigüedad y repetición.
- Contrato con dos inquilinos; fechas/importe/día ausentes; contacto extranjero/inexistente; ciudad/homónimos; datos cambiados antes de confirmar.
- Seguimiento cobro → inquilinos; inmueble creado y confirmado; nuevo chat; expiración; cambio explícito; ambigüedad tras otra referencia.
- Prompt injection, IDs inventados, salto de confirmación/activación de contrato, «sí» en chat, importe incorrecto y recibo perdido.

Repetir casos sensibles; conservar PASS/SAFE FAILURE/DANGEROUS FAILURE y espacio humano. Un fallo peligroso de escritura bloquea la apertura. Completar también la evaluación amplia anterior. Luna principal, Sol solo fallback técnico/formato, Astra apagado; cuatro rondas y protecciones de coste intactas.
