# Fiscalidad premium — propuesta de producto e implementación

Estado: propuesta aprobada. Primera entrega implementada el 10/09/2026, en beta y pendiente de revisión fiscal profesional. La simulación personal de IRPF (fase B) y las ampliaciones (fase C) siguen pendientes.

## Entrega disponible

Ruta `/fiscality`, accesible desde navegación y ficha del inmueble. Incluye perfil por contribuyente/ejercicio, fichas anuales, periodos de uso, participación constante, importes confirmados, gastos con justificantes opcionales, amortización de construcción, reducción ordinaria, imputación con catastro notificado y aplicación/expiración de saldos de financiación y reparación. Descarga PDF y CSV individual/global e historial de instantáneas cifradas. Plan Fundador y prueba; las versiones anteriores siguen descargables tras bajar de plan.

Cobertura exacta de esta entrega: **2025**, personas físicas en régimen común, viviendas en España, adquisición por compra y plena propiedad con porcentaje constante durante el periodo indicado. Los periodos permiten alquiler habitual, disponibilidad y vivienda habitual propia. La clasificación fiscal la revisa el usuario. Los cargos y movimientos se muestran como referencia, sin convertirlos automáticamente en ingresos/gastos deducibles. La ficha fiscal no modifica las finanzas.

Se calculan la reducción ordinaria del 50 % o el régimen transitorio del 60 % por fecha de contrato, y la imputación del 1,1 % / 2 % según la regla catastral 2025. La construcción usa bases sin suelo, porcentaje elegido entre 1 % y 3 %, días alquilados y tope acumulado. Financiación/reparación aplica antes los saldos anteriores; muestra aplicado, pendiente y caducado por origen, sin trasladarlos automáticamente a otro ejercicio.

**Todavía requieren desarrollo específico:** 2026 y otros ejercicios, varios contratos con distinto tratamiento durante el año, cambios de porcentaje, reducciones especiales del 60/70/90 %, muebles/mejoras, dudoso cobro y recuperaciones, gastos previos al alquiler, catastro no notificado, herencias/usufructos, no residencial/IVA/retenciones, forales/IRNR/IS e IRPF personal. Los casos no cubiertos se guardan como incompletos y quedan identificados en el dossier parcial. La captura admite descripción y documento por gasto, pero no verifica la autenticidad o suficiencia fiscal de un justificante.

El cuestionario de caso ordinario también excluye alquiler temporal, habitaciones, actividad económica, familiares, rentas irregulares y devoluciones de intereses. Falta la revisión externa de los casos de referencia antes de publicitar fiabilidad fiscal. No se ha cambiado el precio ni añadido una promesa fiscal a la landing.

Implementación: dominio `backend/app/Domain/Fiscality`, reglas/versionado en `config/fiscality.php`, tres tablas (`tax_years`, `property_tax_records`, `tax_report_snapshots`) con contenido cifrado y borrado en cascada. Autorización por usuario/cartera, control de revisión para evitar sobrescrituras, límites separados de generación/descarga, 100 versiones por ejercicio y reutilización de una instantánea si no cambian los datos. Cada descarga regenera la presentación v1 desde los datos congelados; se preserva el contenido, no se garantiza identidad binaria del PDF. Las últimas 20 versiones aparecen en pantalla; los identificadores anteriores conservan la descarga autorizada.

Verificación: pruebas en `PropertyTaxCalculatorTest`, `FiscalityApiTest` y `frontend/tests/fiscality.test.js`. La muestra visual `php tools/preview-fiscal-pdf.php` genera `/tmp/alquivo-fiscal-qa.pdf` con datos inventados y sin acceder a la base de datos. El motor redondea a céntimos por partida mediante aritmética decimal exacta. Los saldos y bases de entrada son del 100 % del inmueble; los resultados y arrastres de salida corresponden al porcentaje declarado.

Investigación: 7 de septiembre de 2026. Referencias: Ley y Reglamento del IRPF y manual práctico AEAT de Renta 2025. El texto consolidado de la Ley consultado indica actualización publicada el 2 de septiembre de 2026. Las reglas del ejercicio 2025 no deben trasladarse automáticamente a 2026 ni a ejercicios anteriores. Esta propuesta no certifica el cumplimiento de toda la normativa española: delimita cobertura, controles y validaciones pendientes.

## 1. Qué debería vender Alquivo

«Prepara la renta de tus alquileres, entiende sus números y entrega todo organizado a tu gestor».

Dos funciones diferentes:

1. **Dossier fiscal inmobiliario:** ingresos fiscales, gastos, amortizaciones, reducciones e imputaciones, por inmueble y consolidados por contribuyente y ejercicio.
2. **Simulación personal de IRPF:** estimación del efecto de esos rendimientos en el impuesto anual del propietario, cuando haya información suficiente y su situación esté cubierta.

No prometer «tu declaración exacta», presentación ante Hacienda, asesoramiento personalizado automático ni ahorro fiscal garantizado. Un PDF con un aviso legal no compensa un cálculo incorrecto. La IA no debe decidir deducciones, inventar datos ni calcular impuestos.

## 2. Lo que existe y lo que falta

Reutilizable: carteras y autorización, propiedades y adquisición, contratos, cargos de renta, movimientos, documentos privados, suscripciones y exportación CSV protegida.

Los informes actuales de `ReportController` usan movimientos pagados. Son informes de caja, no fiscales. El valor estimado del inmueble tampoco sustituye a sus valores catastrales o bases de amortización.

Falta estructurar titularidad fiscal, periodos de uso, exigibilidad y ajustes fiscales, valores de suelo/construcción, amortización histórica, arrastres, clasificación justificable de gastos, perfil fiscal anual y reglas versionadas.

El nuevo módulo no cambiará silenciosamente las cifras del dashboard financiero. Mostrará una conciliación entre dinero cobrado/pagado y rendimiento fiscal.

## 3. Cobertura: España no es un único cálculo

Primera entrega propuesta: personas físicas residentes fiscales en España, IRPF de régimen común, inmuebles situados en España y alquiler residencial de unidades completas sin actividad económica. Incluir copropiedad ordinaria, cambios de uso durante el año y adquisición onerosa. Aplicar reglas por ejercicio, contrato, contribuyente y territorio.

La simulación personal empezará por declaración individual de perfiles expresamente soportados. Se verificará la escala y particularidades de cada comunidad incluida; no usar una escala autonómica de ejemplo para todas. La residencia fiscal del propietario no se deduce de la dirección del piso.

Detectar desde el cuestionario y mostrar como pendientes de revisión o no cubiertos:

- Regímenes forales de Navarra, Álava, Bizkaia y Gipuzkoa; no tratarlos como una escala autonómica más.
- No residentes (IRNR), sociedades (IS), actividad económica y supuestos internacionales.
- Alquiler turístico, servicios hoteleros, habitaciones y usos simultáneos parciales.
- Locales, oficinas, naves y garajes independientes, hasta validar su tratamiento y la separación IVA/retenciones. Alquivo podrá seguir gestionándolos operativamente aunque la automatización fiscal no los cubra aún.
- Herencias, donaciones, usufructos, nuda propiedad, alquiler a familiares y operaciones vinculadas, hasta tener sus reglas y pruebas específicas.
- Declaración conjunta, situaciones personales complejas, rentas irregulares y deducciones especiales no cubiertas por el simulador.
- Venta de inmuebles y ganancias patrimoniales, plusvalía municipal, ISD, Patrimonio y gravamen de grandes fortunas: fuera de este dossier de alquileres.
- Canarias, Ceuta y Melilla requieren revisar sus particularidades aplicables; no extrapolar sin más IVA ni beneficios fiscales peninsulares.

Los inmuebles excluidos NO desaparecen del total sin advertencia. El informe será «parcial: hay inmuebles o ajustes no calculados», identificará cuáles y permitirá exportar sus datos registrados sin atribuirles un resultado fiscal válido.

## 4. Reglas que el motor debe contemplar

### Ingresos y periodos

Como regla general, imputación cuando la renta resulta exigible, aunque todavía no esté cobrada. Una reclamación judicial sobre el derecho o la cuantía puede requerir tratamiento distinto. [AEAT: imputación temporal](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/imputacion-temporal-rendimientos-capital-inmobiliario.html).

Diseño: conciliar cargos, cobros parciales, anticipos y ajustes sin duplicar el cargo y su pago. Una fianza reembolsable no se convertirá automáticamente en renta: su aplicación posterior necesita clasificación. Registrar días y porcentajes destinados a alquiler, uso propio u otros usos, evitando solapamientos incoherentes.

### Gastos y saldos pendientes

Clasificar gastos relacionados y justificados, no deducir todo lo marcado como gasto financiero. Separar intereses de devolución de principal, conservación de mejora y gasto del propietario de cantidades soportadas por el inquilino. Intereses/financiación y reparación/conservación tienen un límite conjunto por inmueble ligado a sus ingresos íntegros y un posible arrastre de cuatro años. [AEAT: financiación y conservación](https://www3.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/gastos-deducibles/intereses-demas-gastos-financiacion-inmueble.html).

Comunidad, tributos deducibles, seguros y servicios requieren su tratamiento y asignación temporal. No toda vacancia permite deducir gastos; los trabajos preparatorios de un futuro alquiler requieren análisis propio. El impago no elimina por sí solo el ingreso. Para saldos de dudoso cobro, verificar los requisitos del ejercicio, evidencia de reclamación y recuperación posterior; en 2025 existe, entre otros supuestos, el de más de seis meses desde la primera gestión de cobro hasta el cierre, sin renovación del crédito. [AEAT: otros gastos](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/gastos-deducibles/otros-gastos-necesarios-obtencion-rendimientos.html).

Diseño: cada concepto tendrá base, porcentaje atribuible, importe deducible, importe pendiente/excluido, motivo y justificante. Registrar origen, consumo y caducidad de arrastres, también los anteriores a Alquivo. No confundir este límite particular con una prohibición general de rendimiento inmobiliario negativo.

### Amortización

Contemplar el límite anual ordinario del 3 % sobre la mayor base aplicable de adquisición o valor catastral, sin suelo, y el límite acumulado correspondiente. Separar mobiliario y mejoras. La guía 2025 recoge criterios recientes sobre porcentaje y amortización acumulada: no fijar una deducción invariable del 3 % sin historial ni revisar la normativa aplicable. [AEAT: amortización](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/gastos-deducibles/cantidades-destinadas-amortizacion.html).

Diseño: ficha de activos amortizables con coste, suelo/construcción, gastos de adquisición, fecha de puesta en uso, porcentaje, uso arrendado y amortización anterior. Sin base o historial necesario, pedirlo o marcar cálculo incompleto; no usar valor de mercado ni asumir amortización anterior cero.

### Reducción por alquiler de vivienda

Se aplica, cuando proceda, sobre rendimiento neto positivo, no sobre ingresos brutos ni directamente sobre la cuota de IRPF. La guía distingue contratos anteriores al 26/05/2023, con régimen transitorio del 60 %, y posteriores, con reducción general del 50 % y supuestos condicionados del 60 %, 70 % o 90 %. El nuevo esquema produce efectos desde 2024; no aplicarlo retrospectivamente sin versión anual. Los alquileres temporales no reciben automáticamente el tratamiento de vivienda habitual. [AEAT: reducciones de vivienda](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/reducciones-rendimiento-neto/arrendamiento-inmuebles-destinados-vivienda.html).

Diseño: cuestionario y evidencias, nunca selector libre «quiero el 90 %». Para beneficios especiales comprobar, según el supuesto, zona tensionada y vigencia de su declaración, renta anterior actualizada y disminución superior al 5 %, primer alquiler, edades y proporciones de inquilinos, programa social o rehabilitación cualificada en plazo. Registrar modificaciones contractuales y no clasificar toda renovación como contrato fiscal nuevo. Los casos sin prueba o no validados requerirán revisión.

### Inmuebles a disposición del propietario

Contemplar imputación inmobiliaria cuando corresponda, periodos no alquilados y exclusiones. No equivale a cobrar un alquiler ficticio ni a aplicar un tipo de IRPF. En 2025 existen porcentajes del 2 % y 1,1 % y una regla temporal específica sobre revisión catastral; no fijar «últimos diez años» para todos los ejercicios. [AEAT: imputación de rentas 2025](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-ayuda-presentacion/irpf-2025/7-cumplimentacion-irpf/7_3-rendimientos-derivados-inmuebles/7_3_2-imputacion-rentas.html).

Diseño: valor catastral, fecha/efectos de revisión, notificación y calendario de usos. Prorrateos reales, incluidos años bisiestos. La vivienda habitual y otros supuestos excluidos no se tratarán como una segunda residencia disponible.

### Titularidad

El titular de la cuenta de Alquivo no tiene por qué declarar el 100 % del inmueble. Hay que distinguir propietario, porcentaje, naturaleza de los bienes y derechos de disfrute; en usufructo los rendimientos corresponden al usufructuario. [AEAT: individualización](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c04-rendimientos-capital-inmobiliario/individualizacion-rendimientos-capital-inmobiliario.html).

Diseño: un dossier por contribuyente/ejercicio. Mostrar cifras totales del inmueble y parte atribuible claramente diferenciadas, con titularidad histórica. No crear acceso para terceros por el simple hecho de registrarlos como cotitulares.

### IVA y retenciones: extensión separada

La vivienda destinada exclusivamente a vivienda puede estar exenta de IVA; un local normalmente tributa al 21 %. Las retenciones por arrendamientos urbanos tienen reglas y excepciones propias: no equivalen al IRPF definitivo. [AEAT: vivienda e IVA](https://sede.agenciatributaria.gob.es/Sede/iva/iva-operaciones-inmobiliarias/alquilo-vivienda-tengo-que-ingresar-iva.html), [AEAT: local e IVA](https://sede.agenciatributaria.gob.es/Sede/iva/iva-operaciones-inmobiliarias/alquilo-local-tengo-que-ingresar-iva.html), [AEAT: rentas sometidas a retención](https://sede.agenciatributaria.gob.es/Sede/irpf/retenciones-ingresos-cuenta-pagos-fraccionados/retenciones-ingresos-cuenta/rentas-sometidas-retencion-ingreso-cuenta.html).

Diseño: reservar desglose de base, impuesto indirecto, retención y cobro neto. No confundir obligaciones del arrendador con las del arrendatario retenedor. No generar/presentar automáticamente modelos 303, 115 o 180. El PDF del dossier no será una factura; una futura emisión de facturas necesitará su propia revisión normativa.

## 5. IRPF personal: cómo estimarlo sin engañar

No existe un porcentaje único para los alquileres. Intervienen la escala estatal y autonómica, otras rentas, mínimos, reducciones y deducciones. El sueldo bruto anual no basta. [AEAT: gravamen estatal](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c15-calculo-impuesto-determinacion-cuotas-integras/gravamen-base-liquidable-general/gravamen-estatal.html), [AEAT: gravamen autonómico](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/irpf-2025/c15-calculo-impuesto-determinacion-cuotas-integras/gravamen-base-liquidable-general/gravamen-autonomico.html).

Propuesta de simulación comparativa:

`Impacto estimado de los inmuebles = IRPF anual estimado con esos inmuebles − IRPF anual estimado sin ellos`

Ambos escenarios deben recalcular las reglas y umbrales afectados, no limitarse a multiplicar una cifra por un marginal. Incluir rendimientos e imputaciones soportados y explicitar qué cartera se elimina en el escenario comparativo. No mezclarlo con el resultado a ingresar/devolver de la declaración, que también depende de retenciones y pagos a cuenta.

Datos opcionales del simulador, explicados con ayuda y solo según el alcance admitido:

- Ejercicio, residencia fiscal, modalidad de declaración y situación personal/familiar relevante.
- Rentas del trabajo y gastos/reducciones aplicables; distinguir bruto de rendimiento fiscal.
- Otras rentas, inmuebles fuera de Alquivo, compensaciones, aportaciones y deducciones que afecten al resultado.
- Mínimos aplicables o información mínima para obtenerlos. Evitar recopilar documentos médicos o de familiares innecesarios; revisar específicamente el tratamiento de información sensible antes de soportarlo.

Modo básico posible: escenario con una base fiscal previa y supuestos limitados, denominado explícitamente simulación parcial. No presentarlo como cuota total ni como equivalente a una declaración completa. Sin datos suficientes, mostrar «no calculable todavía», no 0 €.

El dossier inmobiliario se podrá obtener sin comunicar el sueldo. El anexo con simulación personal será independiente y optativo. El impacto fiscal global no se repartirá arbitrariamente entre pisos: la progresividad hace que impactos aislados puedan no ser sumables. Cada ficha mostrará su contribución al rendimiento fiscal; la estimación del impuesto será global.

## 6. Pantallas y documentos

### Fiscalidad

- Selector de ejercicio y contribuyente; estado de cobertura y datos pendientes.
- Resumen de ingresos fiscales, gastos admitidos, amortización, rendimiento neto, reducción, rendimiento reducido e imputaciones, sin confundir categorías.
- Tarjetas de inmuebles: listo para revisar / faltan datos / caso no cubierto.
- Acciones «Completar datos», «Estimar mi IRPF» y «Descargar dossier».
- Configuración guiada y progresiva, no formulario de declaración completo al entrar.

### Pestaña fiscal de cada inmueble

- Titularidad, valores y uso anual.
- Desglose y conciliación de ingresos y gastos; justificantes y explicación de ajustes.
- Activos amortizables, arrastres y reducción con fundamento.
- Vista previa del informe individual y avisos accionables.

### Exportaciones premium

1. **PDF anual general por contribuyente:** resumen y detalle por inmueble, porcentaje atribuible, arrastres y conciliación.
2. **PDF de un inmueble:** mismo criterio y detalle, sin exigir descargar toda la cartera.
3. **CSV de detalle fiscal:** para trabajar con el gestor; XLSX puede añadirse después si aporta utilidad.

Cada PDF identificará ejercicio, fecha, versión de reglas, alcance, fuentes, supuestos, datos pendientes y referencias a justificantes. Documentos adjuntos originales o ZIP serán opcionales y separados; evitar mezclar automáticamente contratos con información personal innecesaria. El informe personal de IRPF solo se incluirá si el usuario lo solicita.

Un borrador incompleto se podrá descargar, visiblemente marcado. «Revisado por el usuario» no significará «validado por Hacienda». No imitar un Modelo 100 oficial ni prometer importación directa a Renta WEB sin soporte verificado.

Conservar una instantánea de cada informe generado. Si cambian datos, el informe previo mantiene su contenido y aparece como anterior; una nueva exportación crea otra versión. Ajustar conservación/borrado a la política del servicio, sin convertir el historial en una retención indefinida obligatoria.

## 7. Arquitectura propuesta

Dentro del monolito Laravel, dominio `Fiscality`; no microservicios ni lógica fiscal repartida en componentes Vue.

| Pieza | Responsabilidad |
| --- | --- |
| `TaxProfile` | Contribuyente, ejercicio, residencia y alcance; simulación personal separada del dossier. |
| `PropertyTaxProfile` / `OwnershipPeriod` | Valores fiscales y titularidad con fechas. |
| `PropertyUsePeriod` | Calendario y proporciones de uso. |
| `TaxEntry` / asignaciones | Conciliación de cargos/movimientos y ajustes documentados, con referencias al origen. |
| `DepreciableAsset` | Bases, porcentajes y amortización histórica de construcción, mobiliario y mejoras. |
| `TaxCarryforward` | Saldos por inmueble/contribuyente, origen, consumo y caducidad. |
| `TaxRuleSet` | Reglas por ejercicio y jurisdicción, vigencia, fuente y estado de validación. |
| `TaxReportSnapshot` | Entradas, resultado, reglas y metadatos de una versión exportada. |

Estas son responsabilidades propuestas, no una obligación de crear una tabla o servicio para cada nombre. Concretar relaciones después de aprobar cobertura y revisar casos reales anonimizados.

Servicios separados cuando sea útil: comprobación de cobertura/completitud, cálculo inmobiliario, simulación IRPF y generación de documentos. Un único resultado del backend alimenta pantalla, CSV y PDF. Store Pinia fiscal organizado por ejercicio y contribuyente; no duplicar fórmulas en frontend.

Requisitos del motor:

- Cálculos deterministas con decimales exactos/céntimos y redondeo documentado; no floats monetarios.
- Resultado trazable hasta dato de origen, operación y regla utilizada.
- Validaciones temporales, porcentajes y unidades; ausencia de dato distinta de cero.
- Reconciliación idempotente, sin duplicar cargos y pagos ni alterar movimientos originales.
- Ajustes manuales explícitos con motivo e historial, nunca una corrección invisible del dato original.
- Cambios de reglas no reescriben exportaciones históricas. Separar propuesta legislativa, norma publicada y efectos para cada ejercicio.

## 8. Acceso, privacidad y comercialización

Propuesta comercial de beta: funcionalidad `fiscal_reports` y, cuando esté validada, `irpf_simulation` en el Plan Fundador y en la prueba de producto. Sin cobrar aparte el PDF global frente al individual. El catálogo de beta queda en Gratuito y Plan Fundador por 6,99 €/mes; el precio de Stripe debe crearse y configurarse por separado.

Propuesta tras bajar de plan: conservar lectura/descarga de informes ya generados mientras la cuenta y los datos sigan existiendo; nuevas simulaciones/exportaciones premium requieren acceso activo. No bloquear el acceso ordinario a datos propios ni confundir exportación comercial con derechos de protección de datos.

Controles que deben verificarse antes de abrir el módulo:

- Autorización por cartera, contribuyente y archivo, en solicitudes, trabajos en cola y descargas; el identificador de otro usuario no concede acceso.
- Archivos privados, sin enlaces públicos permanentes, sin caché compartida y con descargas autorizadas.
- Datos personales opcionales separados; no enviar salarios, documentos fiscales ni circunstancias familiares al asistente por defecto.
- PDF sin ejecución de contenido aportado por usuarios ni acceso arbitrario a recursos remotos; proteger también CSV frente a fórmulas.
- Cifrado y gestión de claves adecuados para los datos sensibles y copias; registros de auditoría sin volcar contenido fiscal.
- Revisión jurídica de base de tratamiento, información al usuario, encargados, conservación, borrado, acceso de soporte y posibles datos especialmente protegidos. No inventar un plazo universal de conservación ni dar por cumplido el RGPD solo por añadir consentimiento.

## 9. Entregas y criterios de aceptación

### A. Base fiscal e informe de alquileres

Perfil y cobertura, titularidad/uso, valores, conciliación, gastos, amortización, arrastres y reducciones soportadas. Pantalla y PDFs individual/global. Reglas 2025 como primer conjunto contrastable; preparar 2026 como ejercicio en curso solo después de revisar sus disposiciones aplicables, indicando los datos previstos frente a los reales.

### B. Simulación personal de IRPF

Perfil opcional, escenarios comparativos y reglas estatales/autonómicas verificadas. Entregar por cobertura validada, no activar todas las situaciones con una fórmula simplificada. Es parte del objetivo solicitado, pero no debe bloquear el valor inicial de un dossier fiable.

### C. Ampliación de cobertura

No residencial e impuestos asociados, casos de titularidad/adquisición especiales, alquiler parcial/turístico y otros regímenes, por bloques independientes con pruebas. Sociedades y no residentes serían módulos fiscales diferenciados, no condiciones dispersas dentro del cálculo residencial.

### Pruebas mínimas antes de cobrar por cálculos fiscales

- Casos de referencia revisados por un profesional fiscal: adquisición, copropiedad, periodos parciales, años bisiestos y cambios de titularidad.
- Fronteras de fecha del régimen transitorio y condiciones exactas de reducciones especiales; no aplicar reducciones sobre resultados negativos.
- Impagos, cobro posterior, anticipos y pagos parciales sin duplicidades.
- Límite de financiación/conservación por inmueble; generación, consumo y caducidad de arrastres.
- Suelo excluido, mejoras, mobiliario, amortización acumulada e historial importado.
- Vacancia, vivienda habitual y cambios de uso; falta de catastro y valores incompletos.
- Tipos progresivos, comunidad, mínimos y cambios de umbral con otras rentas; resultados parciales correctamente etiquetados.
- Totales PDF/CSV/pantalla iguales; ausencia de filtraciones entre usuarios; exportaciones antiguas reproducibles.
- Comparación de escenarios ficticios admitidos con ejemplos oficiales y [Renta WEB Open](https://sede.agenciatributaria.gob.es/Sede/eu_es/ayuda/consultas-informaticas/renta-ayuda-tecnica/renta-web-open.html), sin presentar declaraciones reales.

Un asesor fiscal debe revisar la matriz de cobertura y los casos de referencia antes de publicitar precisión fiscal. No basta con tests que repitan la misma fórmula implementada. Establecer una revisión normativa por ejercicio y ante cambios relevantes, con responsable y registro de aprobación.

## 10. Decisión propuesta para aprobar

Construir A primero y B después, ambos premium, sin convertir Alquivo en un programa integral de declaraciones. Empezar por el alquiler residencial más frecuente, identificar honestamente los casos pendientes y ampliar cobertura con controles. Mantener gestión general de todo tipo de inmuebles aunque su fiscalidad automatizada llegue por fases.

Marco legal de referencia adicional: [Ley 35/2006 del IRPF](https://www.boe.es/eli/es/l/2006/11/28/35/con) y [Reglamento del IRPF, RD 439/2007](https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820). La implementación deberá vincular cada regla concreta a la disposición y versión aplicables, no usar este documento como sustituto de la normativa.
