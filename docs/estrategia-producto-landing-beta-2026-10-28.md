# Alquivo — Estrategia de landing, conversión y activación de la beta

Fecha: 28 de octubre de 2026.

Estado: informe y propuesta. Este documento no implementa ni autoriza cambios
de código, producción, autenticación, condiciones comerciales o tratamiento de datos.

## 1. Conclusión ejecutiva

Alquivo ya tiene una base de producto que merece ponerse delante de propietarios.
La oportunidad inmediata es demostrar mejor su utilidad y conseguir que alguien
complete una primera tarea real. Una web más bonita, por sí sola, no prueba que
el producto se vaya a vender; tampoco hace falta añadir más módulos para empezar
a aprender de usuarios.

Mi tesis comercial es:

> El propietario no compra un dashboard ni un chatbot: quiere saber qué ha
> cobrado, qué falta por cobrar y dónde está la información de cada alquiler.

La IA es una diferenciación importante: reduce la necesidad de buscar entre
pantallas y permite consultar esos datos en lenguaje cotidiano. Debe demostrarse
con tareas concretas, sin prometer que toda la gestión sea autónoma.

El recorrido que queremos mejorar es:

**Entiendo para qué me sirve → pruebo → organizo un alquiler → consulto mis datos
→ vuelvo en mi siguiente tarea de gestión → valoro pagar cuando exista esa opción.**

## 2. Evidencia y límites de este informe

Se ha consultado el código de la versión de aplicación `602c9f9`, la documentación
del proyecto y la web pública. Las comprobaciones HTTP, HTML, recursos, robots,
sitemap y configuración pública de la revisión anterior de hoy se aprovechan
aquí; no se presentan como una nueva batería de pruebas funcionales.

### Hechos confirmados

- La landing tiene HTML público prerenderizado, metadatos, canonical, guías,
  robots, sitemap y marcado `SoftwareApplication`.
- Los CTA de alta inspeccionados llevan a `/register`.
- La configuración pública declara beta activa, asistente disponible y pagos y
  fiscalidad públicos desactivados. El plan público incluye 50 inmuebles, 5 GB
  y hasta 20 consultas mensuales al asistente.
- El HTML inicial de la FAQ todavía declara que la IA sigue en revisión: no es
  coherente con la API. El estado inicial del store del asistente es `false` y el
  render estático no consulta esa API. El cliente sí carga la configuración.
- La portada contiene una ilustración de panel y una conversación explicativa;
  no una demostración capturada de una tarea real de la aplicación.
- La explicación explícita de para quién es Alquivo aparece bastante abajo.
- No se ha encontrado instrumentación específica del embudo comercial en el
  código revisado. Eso no equivale a que no existan registros técnicos.
- El onboarding opcional y los estados vacíos ya existen. El siguiente paso del
  dashboard prioriza una valoración del patrimonio antes del primer alquiler.
- La creación de una propiedad ya lleva a su apartado de alquiler. Ese enlace
  entre tareas es bueno y conviene conservarlo.
- No hay imagen `og:image` dedicada en los metadatos inspeccionados.
- `www.alquivo.com` no resolvía en la comprobación de hoy.

### Lo que todavía no sabemos

- Conversión de visitantes humanos a registros y registros a propietarios activos.
- Dónde abandonan realmente los usuarios y cuánto tardan en completar su tarea.
- Disposición a pagar, segmento más receptivo y demanda de cada funcionalidad.
- Rendimiento real en móviles y redes de nuestros futuros clientes.
- Qué cambios visuales elevan la conversión. Son hipótesis, no resultados demostrados.

PageSpeed Insights devolvió un error 429 de cuota en móvil y escritorio. No se
han obtenido puntuaciones válidas de Lighthouse. No había navegador disponible
para una nueva inspección visual/interactiva. La accesibilidad completa, el
recorrido de alta y los resultados de negocio no quedan certificados por este informe.
No se han leído conversaciones, documentos ni datos reales de las carteras.

## 3. Posicionamiento: a quién intentaría convencer primero

### Segmento inicial recomendado

Propietarios particulares que gestionan sus propios alquileres, especialmente
quienes tienen varios inmuebles y combinan Excel, carpetas, mensajes y memoria.
Para las primeras conversaciones comerciales priorizaría propietarios con unos
2–10 inmuebles: es una hipótesis de captación, no un nuevo límite del producto.

Un propietario con un solo inmueble sigue siendo válido. Inmobiliarias, gestores
de muchas carteras y usuarios que necesitan contabilidad o fiscalidad avanzadas
no serían el foco inicial de ventas. La futura idea Enterprise queda separada.

### Problemas por los que alguien puede interesarse

1. Distinguir lo cobrado de lo pendiente, también con pagos parciales.
2. Saber cuánto queda después de los gastos de un inmueble.
3. Localizar contrato, contacto o factura sin buscar en varios sitios.
4. Recordar qué alquiler o vencimiento necesita revisión.
5. Consultar esos datos sin aprender todas las pantallas.

La promesa central sería **control cotidiano del alquiler con menos búsqueda y
menos dispersión**. La visión patrimonial acompaña esa promesa, no la sustituye.

## 4. Diagnóstico de la landing

### Lo que mantendría

- La identidad oscura, sobria y financiera; el nombre y el wordmark.
- El titular «Tus alquileres, en orden. Tus números, claros.».
- La gratuidad de la beta y la ausencia de tarjeta, explicadas con claridad.
- Un acceso visible para quien ya tiene cuenta.
- Enlaces legales discretos y accesibles, sin mostrar la identidad legal en el hero.
- La lectura sin JavaScript y las guías existentes.

### Lo que está frenando su capacidad de explicar el producto

**Coherencia de IA — error confirmado.** Un visitante o agente que lea solo HTML
recibe una disponibilidad diferente. Hay que corregir el origen del estado y
validar HTML/API, no cambiar una frase suelta ni habilitar flags por marketing.

**Utilidad demasiado abstracta — hipótesis comercial.** Expresiones sobre
patrimonio, perspectiva y claridad construyen marca, pero muestran menos tareas
concretas de las que el producto ya resuelve.

**Demostración débil — observación de contenido.** La conversación de ejemplo
explica qué consultará el asistente; no muestra la respuesta útil que recibirá
un propietario. La ilustración del panel no permite reconocer el flujo real.

**Repetición y longitud — riesgo de experiencia.** Hay varios bloques que
transmiten beneficios parecidos. Reducir repetición puede mejorar lectura móvil;
eso no justifica eliminar información útil ni fijar una longitud arbitraria.

**Confianza todavía poco demostrada — hipótesis comercial.** Un propietario
debe decidir si introduce información sensible en una app nueva. Necesita ver
el producto, entender quién le atiende, saber qué puede exportar y conocer las
limitaciones sin enfrentarse a un gran bloque jurídico.

## 5. Propuesta concreta de portada

### Bloque 1. Qué es, para quién y cómo probarlo

Texto de contexto:

> Para propietarios que gestionan sus propios alquileres.

Titular, conservando la marca actual:

> Tus alquileres, en orden.
> Tus números, claros.

Subtítulo propuesto:

> Ten a mano lo que has cobrado, lo que queda pendiente y los gastos de cada
> inmueble. Organiza tus alquileres en un solo lugar y consulta tus datos con
> Alquivo AI.

CTA principal: **Probar gratis**. Secundario: **Ver cómo funciona**.

Apoyo breve: beta gratuita, sin tarjeta. Los límites detallados aparecen en su
sección, sin saturar el primer vistazo. El acceso para clientes sigue en cabecera.

El subtítulo debe entenderse sin saber qué significa SaaS, ERP, rentabilidad
neta o inteligencia artificial. No afirmaría tiempos de ahorro no medidos.

### Bloque 2. Demostración real y breve

Capturas de una cartera sintética de la versión pública, con dos o tres inmuebles:

- Cobros pendientes de un mes claramente identificado.
- Resumen de cobros y gastos de uno de esos inmuebles.
- Una pregunta a Alquivo AI y una respuesta contrastada con esos registros.

La misma historia y las mismas cantidades deben cuadrar entre capturas. No se
usarán datos de usuarios. La etiqueta de ejemplo será discreta pero comprensible.

Empezar por imágenes optimizadas. Si un vídeo aporta más claridad, cargarlo al
pedirlo, con controles y subtítulos; sin autoplay ni reproducción de audio inesperada.

### Bloque 3. Tres beneficios reconocibles

1. **Sabe qué queda por cobrar.** Distingue pagos completos, parciales y pendientes.
2. **Entiende lo que deja cada inmueble.** Cobros y gastos registrados, sin confundir
   previsiones con dinero recibido ni equiparar flujo de caja y rentabilidad.
3. **Encuentra cada cosa en su sitio.** Contratos, personas y documentos asociados.

Evitar una cuadrícula larga de módulos. Los detalles secundarios pueden quedar
en desplegables accesibles o en una página de funcionalidades útil más adelante.

### Bloque 4. IA con valor concreto

Propuesta de título: **Pregúntale a tus alquileres.**

Ejemplos candidatos, que se probarán antes de publicarse:

- «¿Qué alquileres tengo pendientes este mes?»
- «¿Cuánto he cobrado de este inmueble en total?»
- «¿Qué contratos terminan próximamente?»

Mostrar información consultada y enlaces a sus registros. Explicar que determinadas
propuestas necesitan revisión y confirmación. No decir que puede ejecutar todo
lo que permite la interfaz: las nuevas acciones de creación siguen restringidas.

Sustituir garantías absolutas sobre errores por mensajes verificables: respuestas
basadas en registros, fuentes consultables y revisión de datos importantes.

### Bloque 5. Beta y confianza

Una única oferta visible: beta gratuita. Explicar sin ambigüedad:

- Hasta 50 inmuebles y 5 GB durante la beta.
- Hasta 20 consultas mensuales al asistente según el plan actual, sujetas a sus
  límites operativos; no publicitar IA ilimitada.
- Sin tarjeta ni cobro automático al terminar.
- Aviso previo al cambio de condiciones y posibilidad de exportar datos.
- Soporte mediante tickets, sin prometer atención inmediata.

La confianza debe apoyarse en hechos y enlaces breves a privacidad/proveedores,
no en frases como «seguridad total», «imposible de hackear» o certificaciones que
no se hayan obtenido. Las afirmaciones técnicas se contrastarán antes de añadirlas.

### Bloque 6. Preguntas que frenan la decisión

Priorizar preguntas comerciales reales:

- ¿Me sirve si solo tengo uno o pocos inmuebles?
- ¿Puedo probar antes de subir contratos o documentos?
- ¿Necesito tarjeta? ¿Qué pasa cuando termine la beta?
- ¿Qué hace la IA y qué no hace?
- ¿Cómo exporto mis datos o pido ayuda?

Las respuestas se limitarán al funcionamiento confirmado. Los enlaces legales
amplían información; no sustituyen los consentimientos o avisos que procedan.

### Bloque 7. Cierre y recursos

Cerrar con el mismo CTA principal y una expectativa concreta: crear la cuenta,
añadir un inmueble y empezar a organizar un alquiler. Mantener recursos útiles
enlazados, sin otro bloque extenso que compita con el registro.

## 6. Diseño móvil y accesibilidad

- Diseñar primero a 360–430 px y comprobar escritorio después. Probar también
  zoom, pantallas estrechas y tamaños de texto ampliados.
- Texto principal alrededor de 17–18 px como dirección de diseño, sin exigir
  leer miniaturas de un dashboard para entender el producto.
- Botones cómodos de tocar, contraste medido y foco visible; las acciones no
  deben depender del color, hover, animaciones o un icono sin etiqueta.
- Demo con recortes y composición propios para móvil: no reducir una captura
  de escritorio hasta volverla ilegible.
- Revisar un CTA de cabecera o contextual al salir del hero. Solo mantenerlo
  fijo si no tapa soporte, formularios, teclado o contenido.
- FAQ mediante controles nativos y navegación por teclado. Movimiento reducido,
  imágenes dimensionadas para evitar saltos y formularios con mensajes claros.

No declarar WCAG 2.2 AA por revisar CSS: requerirá verificación visual, teclado,
lector de pantalla y herramientas automáticas como comprobación complementaria.

## 7. Dentro de la app: conseguir una primera tarea útil

### Cambiar la prioridad del siguiente paso

Actualmente el dashboard propone primero un inmueble, después una valoración y
después un alquiler. Para un propietario que quiere controlar cobros, movería
la valoración a un paso opcional posterior. No modificaría datos existentes.

Recorrido propuesto para alguien que ya alquila:

1. Añadir inmueble con lo esencial.
2. Configurar su alquiler y la renta.
3. Revisar qué se espera cobrar y en qué periodo.
4. Registrar un cobro solo si realmente se ha producido.
5. Consultar el resultado en resumen o mediante IA.

Para un inmueble sin alquilar, ofrecer un recorrido distinto y breve: organizar
su ficha y, si interesa, registrar un gasto o valoración. No exigir un contrato
ni inventar ingresos para completar un checklist.

### Aprovechar lo que ya existe

Conservar el recorrido opcional, los formularios contextuales y la transición
de propiedad creada a su alquiler. Mejorarlos con el siguiente paso pertinente,
en vez de añadir otro onboarding independiente o un tutorial obligatorio.

El resumen debe distinguir «no has registrado información» de «no hay pendientes»
y «no hay valoración» de «vale cero». Las cifras deben mantener periodo, origen
y criterio: cobrado, esperado y pendiente no son equivalentes.

### Hacer útil la primera conversación

Las sugerencias contextuales ya existen. Revisaría que las iniciales tengan
sentido según los datos disponibles. Sin registros, el asistente puede explicar
cómo empezar; con un alquiler, debe ofrecer preguntas que tengan respuesta.

Si no puede hacer algo, la recuperación ideal es explicar la limitación y llevar
a la pantalla o formulario correcto, no terminar con un rechazo genérico. Esto
se evaluará aparte de la landing, sin ampliar acciones ni alterar confirmaciones.

## 8. Medición y validación comercial

### Métrica principal propuesta

**Propietarios que configuran un alquiler utilizable y regresan para una tarea
real del siguiente ciclo de gestión.**

Crear cuenta o completar el tutorial no basta. Tampoco una respuesta bonita de
IA con datos vacíos. Un inmueble no alquilado debe tener un criterio alternativo
de activación, definido antes de instrumentarlo.

Un producto de alquileres no tiene por qué abrirse cada día: no usaría usuarios
activos diarios como único indicador de éxito. El siguiente ciclo de cobro y las
tareas reales son referencias más útiles que perseguir visitas frecuentes.

### Embudo mínimo

1. Llegada humana estimada y canal, distinguiéndolos de tráfico técnico y bots.
2. CTA y comienzo del registro.
3. Alta completada.
4. Primer inmueble y primer alquiler o tarea alternativa.
5. Consulta útil / revisión de cobros / documento localizado.
6. Regreso con una nueva necesidad real.

Definir eventos mínimos, base jurídica aplicable, consentimiento cuando proceda,
retención y acceso antes de instrumentar. No enviar nombres, mensajes, importes,
direcciones, contratos, URLs con tokens ni textos de formularios a analítica.
Sin duplicar proveedores ni activar grabaciones de sesión por defecto.

### Primera muestra de aprendizaje — objetivos propuestos, no métricas actuales

- Acompañar a 5–10 propietarios del segmento elegido.
- Observar si pueden configurar un alquiler y localizar un pendiente sin explicación
  constante; registrar dónde se atascan, con su conocimiento y sin copiar datos privados.
- Preguntar qué usan ahora, qué problema les llevó a probar y qué echarían de menos
  si Alquivo desapareciera. No limitarse a preguntar si les gusta el diseño.
- Revisar su siguiente tarea/ciclo y preguntar si pagarían una cantidad concreta
  por continuar. Distinguir una respuesta amable de uso continuado o compromiso.

Con esta muestra no declararemos una tasa de conversión estable ni un mercado
validado. Servirá para priorizar obstáculos y encontrar usuarios que repiten.

## 9. Captación: mensajes alineados con la landing

Probar un caso de uso por publicación, vídeo o conversación:

- «¿Sabes qué alquileres te quedan pendientes sin abrir el Excel?»
- «Lo que has cobrado no es lo mismo que lo que te deben.»
- «Contrato, gastos y cobros de un piso, sin buscar en cinco sitios.»

El contenido debe mostrar el mismo problema y solución que encuentra después
el visitante. Un reel puede ser una grabación de pantalla con datos sintéticos,
subtítulos y una tarea breve; no exige que el fundador aparezca en cámara.

Empezar por conversaciones relevantes y comunidades que permitan presentaciones,
sin mensajes masivos ni testimonios inventados. Ofrecer ayuda para la primera
configuración y recoger objeciones concretas. Separar invitaciones a la beta de
comunicaciones comerciales posteriores y respetar las decisiones de contacto.

No escalar publicidad pagada hasta observar activación y continuidad. La cantidad
de registros por sí sola no justifica gastar más en adquirirlos.

## 10. SEO y descubrimiento por asistentes

Mantener el HTML útil, las guías, enlaces internos, canonical y controles de
indexación existentes. Corregir primero la disponibilidad contradictoria de IA,
la imagen social y el alias www. El acceso privado seguirá dependiendo de la
autenticación, no de robots.txt.

El contenido nuevo debe responder a tareas reales, con ejemplos propios y enlaces
al producto: seguimiento de cobros parciales, gastos por inmueble o preparación
del siguiente mes. Publicar más páginas no garantiza visibilidad. Evitar consejo
fiscal o jurídico personalizado presentado como una guía genérica.

Una página de funcionalidades solo merece crearse si amplía información útil
que no cabe bien en la portada. Una página de precios con una sola beta puede
esperar; no publicar importes provisionales de planes ocultos.

Google indica que sus funciones de IA mantienen los fundamentos de SEO y no
requieren archivos o marcado especiales:
https://developers.google.com/search/docs/appearance/ai-features

Prioridad baja para archivos complementarios como llms.txt. No crear una API
pública ni permisos especiales de agentes para mejorar el descubrimiento.

## 11. Riesgos comerciales que conviene resolver con antelación

### La transición de beta a pago

La configuración revisada contiene una beta de 50 inmuebles/5 GB y un futuro
plan fundador de 20 inmuebles/2 GB. Son parámetros de código y los planes de pago
están ocultos: no se presentan aquí como condiciones comerciales definitivas.

Antes del cierre hay que decidir cómo se atenderá a quien supere los límites
del plan posterior. No prometer hoy esos 50 inmuebles para siempre, borrar datos
por bajar de plan ni ocultar la posibilidad de exportar. No se propone cambiar
ahora las cuotas, el precio o los permisos.

### Economía y soporte

Medir coste de IA, almacenamiento y tiempo de soporte por cartera activa antes
de asegurar que un precio es rentable. El soporte del fundador puede ayudar a
aprender al principio, pero también es un coste. No hace falta resolver toda la
monetización para admitir la primera muestra de beta.

### Seguridad y confianza

No hay crecimiento sostenible si una demostración promete más de lo que hace
el producto o si una mejora de conversión elimina controles necesarios.
Las evidencias legales, MFA, aislamiento de carteras, cifrado, confirmación de
acciones y límites de presupuesto quedan fuera de los recortes comerciales.

## 12. Plan priorizado de implementación y aceptación

Los esfuerzos son relativos, no compromisos de duración ni predicciones de conversión.

| Fase | Trabajo | Esfuerzo / riesgo principal | Cómo comprobar que está terminado |
| --- | --- | --- | --- |
| A. Mensaje coherente | Corregir HTML/API de IA, subtítulo claro, CTA consistente, límites de beta y quitar garantías absolutas | Bajo / publicar capacidades no disponibles | HTML sin JS y navegador coinciden con capacidades públicas; CTA y cifras correctos |
| B. Demostración y móvil | Capturas sintéticas reales, historia de cobros, composición legible, imagen social, revisar soporte/CTA y www | Medio / cargar peso o perjudicar accesibilidad | Datos de ejemplo cuadran; revisión móvil/escritorio, teclado y metadatos de compartición |
| C. Activación | Reordenar siguiente paso, valoración opcional, llevar al primer alquiler/tarea útil y a una consulta contextual | Medio / interferir en flujos existentes | Alta y tareas completas con cuentas sintéticas, sin bloqueos nuevos ni cambios automáticos de datos |
| D. Aprendizaje medido | Definir e instrumentar el embudo mínimo conforme a privacidad y acompañar primeras pruebas | Medio / seguimiento excesivo o inferencias prematuras | Eventos sin datos sensibles, controles probados y observaciones reales de uso |
| E. Optimización posterior | Rendimiento medido, contenidos que respondan a dudas repetidas y cambios según uso observado | Variable / optimizar métricas sin mejorar el producto | Comparación antes/después con muestras y limitaciones declaradas |

Definir medición y objetivos antes de A; capturar una línea base cuando sea
posible. La instrumentación que necesite decisiones de privacidad no se añade
silenciosamente ni debe bloquear las correcciones simples de contenido.

No abrir nuevos módulos, automatizaciones, demo pública persistente o integraciones
para agentes dentro de estas fases. El producto actual puede seguir abierto
mientras se mejoran estas partes mediante entregas pequeñas y verificadas.

## 13. Referencias de implementación consultadas

Código consultado en el commit `602c9f9` (no asumir que el árbol local pendiente
sea idéntico a lo publicado):

- `frontend/src/views/MarketingView.vue`, `frontend/src/i18n.js`: portada y textos.
- `frontend/src/entry-public.js`, `frontend/src/stores/product.js`: origen de la
  discrepancia de disponibilidad de IA en HTML estático.
- `frontend/src/seo.js`, `frontend/tools/build-public.mjs`: metadatos y generación pública.
- `frontend/src/views/AuthView.vue`: formulario, placeholder y aceptación legal.
- `frontend/src/views/DashboardView.vue`: siguiente paso, bienvenida y métricas.
- `frontend/src/components/OnboardingTour.vue`: recorrido opcional actual.
- `frontend/src/views/PropertiesView.vue`: creación mínima y paso a alquiler.
- `frontend/src/components/AssistantWidget.vue`, `backend/config/ai.php`: asistente,
  sugerencias, propuestas y restricción de nuevas acciones de creación.
- `backend/config/plans.php`: límites actuales y futuros todavía no comerciales.

El análisis comercial es criterio de producto apoyado en esas evidencias, no una
promesa de éxito. El siguiente paso recomendado es la fase A, seguida de la demo
y móvil, mientras se capta una pequeña muestra de propietarios reales.
