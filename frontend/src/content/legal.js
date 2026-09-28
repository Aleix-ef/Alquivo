import { legalOperator } from "./legalOperator.js";

// Keep in sync with backend/config/legal.php. Archive each published revision.
export const legalVersion = "2026-09-24";
export const operator = legalOperator;
export const pendingValue = "Pendiente de completar antes de publicar la beta";
export const legalDraft =
  !operator.reviewedForPublication ||
  [
    operator.name,
    operator.taxId,
    operator.address,
    operator.backupRetention,
    operator.mailboxRetention,
    operator.infrastructureLogRetention,
  ].some((value) => !value.trim()) ||
  operator.providers.some((provider) =>
    [provider.name, provider.location, provider.safeguards].some(
      (value) => !value.trim(),
    ),
  );

const contact = `Puedes escribir a ${operator.email}.`;
const links = {
  privacy: { to: "/privacy", label: "Política de privacidad" },
  processing: {
    to: "/data-processing",
    label: "Acuerdo de encargo y proveedores",
  },
  cookies: { to: "/cookies", label: "Política de cookies" },
  notice: { to: "/legal", label: "Aviso legal e identificación del titular" },
};

export const legalPages = [
  {
    name: "legal",
    path: "/legal",
    title: "Aviso legal",
    label: "Aviso legal",
    summary: "Quién presta el servicio y cómo contactar con Alquivo.",
    sections: [
      {
        id: "titular",
        title: "1. Titular del servicio",
        identity: true,
        paragraphs: [
          "Alquivo es el nombre comercial de este servicio de gestión inmobiliaria. Su titular y prestador es la persona o entidad identificada a continuación. Estos datos también identifican al responsable del tratamiento de los datos de cuenta y contacto.",
        ],
      },
      {
        id: "objeto",
        title: "2. Finalidad del sitio",
        paragraphs: [
          "El sitio presenta Alquivo y permite acceder a su aplicación, consultar sus condiciones y solicitar soporte. El servicio ayuda a organizar inmuebles, alquileres, ingresos, gastos y documentos. La información de las guías es general y no constituye asesoramiento jurídico, fiscal ni financiero adaptado a una situación personal.",
        ],
        links: [
          {
            to: "/terms",
            label: "Consultar las condiciones de uso de la beta",
          },
        ],
      },
      {
        id: "propiedad",
        title: "3. Contenidos y propiedad intelectual",
        paragraphs: [
          "El software, la identidad visual y los contenidos propios del sitio están protegidos por los derechos que correspondan a su titular. Los componentes y materiales de terceros conservan sus respectivas licencias. Puedes consultar y guardar estos textos para tu uso personal; no se concede por ello una licencia para explotar comercialmente el software o la marca.",
          "Los datos y documentos que aportes no pasan a ser propiedad de Alquivo. Su tratamiento se limita a prestar el servicio conforme a las condiciones y, cuando corresponda, al acuerdo de encargo.",
        ],
      },
      {
        id: "contacto",
        title: "4. Contacto y comunicaciones",
        paragraphs: [
          `${contact} Puedes comunicar errores, incidencias o contenido que consideres ilícito indicando la información necesaria para localizarlo, sin enviar datos personales innecesarios. Los enlaces a páginas externas se ofrecen como referencia; cada tercero gestiona su propio sitio.`,
        ],
        links: [links.privacy, links.cookies],
      },
    ],
  },
  {
    name: "terms",
    path: "/terms",
    title: "Condiciones de uso",
    label: "Condiciones",
    summary:
      "Una beta gratuita, sin cargos automáticos, para organizar tu patrimonio y ayudarnos a mejorar Alquivo.",
    sections: [
      {
        id: "servicio",
        title: "1. Servicio y aceptación",
        paragraphs: [
          "Estas condiciones regulan el uso de Alquivo y se formalizan en castellano entre el titular identificado en el aviso legal y la persona que crea la cuenta. Para registrarte debes ser mayor de edad y tener capacidad para contratar; si actúas por otra persona o entidad, debes estar autorizado para representarla.",
          "Antes de crear la cuenta puedes revisar y corregir los datos del formulario. Al marcar la casilla y completar el registro aceptas esta versión de las condiciones, incluido el acuerdo de encargo cuando resulte aplicable. Guardamos la fecha y versión aceptadas. Puedes imprimir o guardar estos textos desde el navegador y solicitar una copia al soporte. La política de privacidad informa sobre el uso de tus datos; su lectura no equivale a consentir publicidad ni funciones opcionales.",
        ],
        links: [links.notice, links.processing, links.privacy],
      },
      {
        id: "beta",
        title: "2. Qué incluye la beta gratuita",
        paragraphs: [
          "Durante la beta, la cuenta permite gestionar hasta 10 inmuebles y utilizar hasta 1 GB de almacenamiento, con las funciones habilitadas en la aplicación. Las funciones de IA, cuando estén disponibles, tienen límites de uso específicos que se muestran antes de activarlas o utilizarlas. Que una función se anuncie como próxima no significa que esté incluida o disponible.",
          "La beta no exige tarjeta ni genera cuotas. No se transforma automáticamente en una suscripción de pago. Si al terminar ofrecemos otros planes, conocerás su precio, duración y condiciones y solo se contratarán mediante una aceptación expresa por tu parte. Una futura oferta de fundador será opcional.",
          "La beta sirve para validar un producto que aún evoluciona: puede haber errores, interrupciones o ajustes. Esta circunstancia no elimina nuestras obligaciones de protección de datos ni los derechos que te reconozca la ley. No existe una garantía de atención inmediata o disponibilidad ininterrumpida.",
        ],
      },
      {
        id: "cuenta",
        title: "3. Tu cuenta y un uso adecuado",
        paragraphs: [
          "Facilita datos de cuenta correctos, protege tus credenciales y mantén accesible tu correo. El doble factor refuerza la seguridad; recordar un dispositivo evita ese segundo paso durante un máximo de 90 días, pero no sustituye la contraseña. Utilízalo solo en dispositivos de confianza. Comunica cuanto antes cualquier acceso que no reconozcas.",
          "Debes tener una base legítima para incorporar los datos de inquilinos y otras personas e informarles cuando corresponda. No necesitas basarlo todo en su consentimiento: la base depende de la relación y de la finalidad del tratamiento. Introduce solo la información necesaria y evita datos especialmente sensibles que no sean precisos para la gestión del alquiler.",
        ],
        items: [
          "No utilices cuentas ajenas, eludas límites ni intentes acceder a carteras o archivos de otras personas.",
          "No subas malware, contenido ilícito ni materiales que vulneren derechos de terceros.",
          "No compartas la cuenta para ofrecer acceso a terceros sin autorización ni uses la aplicación para enviar comunicaciones no solicitadas.",
        ],
      },
      {
        id: "datos",
        title: "4. Tus documentos y resultados",
        paragraphs: [
          "Conservas los derechos sobre tus documentos y contenidos. Nos autorizas únicamente a alojarlos y procesarlos en lo necesario para prestarte el servicio, atender tus instrucciones y proteger su funcionamiento. No se ceden para su venta ni para publicidad.",
          "Los saldos, rentabilidades y resúmenes dependen de la información introducida. Comprueba los resultados antes de tomar decisiones o cumplir obligaciones fiscales o contractuales. Conserva los originales que debas custodiar legalmente. El servicio no sustituye al registro, a una gestoría ni a un asesor profesional.",
        ],
      },
      {
        id: "ia",
        title: "5. Funciones de inteligencia artificial",
        paragraphs: [
          "Si una función de IA está habilitada para tu cuenta, su uso requiere revisar su aviso específico y activarla. El asistente puede equivocarse o no disponer de información suficiente. Sus respuestas no constituyen asesoramiento profesional ni decisiones automatizadas sobre inquilinos.",
          "Cuando el asistente prepare una operación, revísala antes de confirmarla. La activación del chat no autoriza por sí sola el envío de documentos a un proveedor de IA. Una función de análisis documental real requerirá un aviso y una activación independientes. Puedes dejar de usar el asistente sin perder el acceso a la gestión ordinaria.",
        ],
        links: [{ to: "/privacy#ia", label: "Qué datos utiliza el asistente" }],
      },
      {
        id: "soporte",
        title: "6. Soporte y comunicaciones del servicio",
        paragraphs: [
          `Puedes abrir varios tickets y continuar cada conversación dentro de la aplicación. También puedes contactar por correo. ${contact} El soporte no es un servicio de emergencias. Para resolver una consulta comparte solo lo necesario y oculta los datos de terceros en las capturas.`,
          "Podemos enviarte comunicaciones necesarias sobre seguridad, acceso, soporte y cambios relevantes del servicio. Crear una cuenta no te suscribe a campañas de publicidad.",
        ],
      },
      {
        id: "baja",
        title: "7. Baja, suspensión y fin de la beta",
        paragraphs: [
          "Puedes dejar de utilizar el servicio y eliminar tu cuenta desde Configuración. Antes de eliminarla, descarga tus documentos y las exportaciones disponibles de inmuebles, contratos y movimientos. Si necesitas otros datos, solicítalos al soporte antes de borrar la cuenta. El borrado de la cuenta también elimina su cartera y sus tickets del sistema activo; los archivos pendientes de borrado se reintentan. La eliminación no puede deshacerse desde la aplicación.",
          "Podremos limitar o suspender el acceso ante usos ilícitos, intentos de acceso ajeno, riesgos de seguridad o incumplimientos graves. La medida será proporcional y, cuando sea posible sin agravar el riesgo ni incumplir la ley, explicaremos el motivo y cómo solicitar su revisión. No emplearemos la suspensión para forzarte a contratar un plan de pago.",
          "Si decidimos finalizar la beta o reducir de forma sustancial sus prestaciones, avisaremos con al menos 30 días naturales para que puedas decidir y descargar tus datos, salvo que una obligación legal o una urgencia de seguridad exija actuar antes. No borraremos deliberadamente una cartera por el mero anuncio de un plan futuro.",
        ],
      },
      {
        id: "responsabilidad",
        title: "8. Responsabilidad y derechos",
        paragraphs: [
          "Cada parte responde conforme a la legislación aplicable. El carácter gratuito o experimental del servicio no excluye la responsabilidad que legalmente corresponda a Alquivo, ni limita los derechos irrenunciables de los consumidores. Ninguna cláusula excluye la responsabilidad por dolo ni impone una renuncia general a reclamar.",
          "Los cambios relevantes se comunicarán con antelación razonable. Cuando una modificación exija una nueva aceptación o consentimiento, se solicitará expresamente; el silencio no sirve para contratar un plan de pago.",
        ],
      },
      {
        id: "reclamaciones",
        title: "9. Legislación y reclamaciones",
        paragraphs: [
          `${contact} Intentaremos resolver cualquier reclamación identificando el problema y la cuenta afectada. Se aplica la legislación española, sin perjuicio de la protección obligatoria que corresponda a una persona consumidora por su residencia. Los tribunales competentes serán los determinados por la ley; no se impone un fuero que prive al consumidor de sus derechos.`,
        ],
      },
    ],
  },
  {
    name: "privacy",
    path: "/privacy",
    title: "Política de privacidad",
    label: "Privacidad",
    summary:
      "Qué datos utilizamos, para qué los necesitamos y cómo ejercer tus derechos.",
    sections: [
      {
        id: "responsable",
        title: "1. Quién trata tus datos",
        identity: true,
        paragraphs: [
          "El titular de Alquivo actúa como responsable de los datos necesarios para gestionar las cuentas, la relación con los usuarios, el soporte y la seguridad del servicio. Sus datos de contacto figuran a continuación.",
          "Cuando un propietario incorpora datos de inquilinos, avalistas, proveedores u otras personas y decide para qué los utiliza, Alquivo los trata por su cuenta para prestar el servicio. Cuando esa relación esté sujeta al RGPD, se regula mediante el acuerdo de encargo. Si eres inquilino y tu propietario usa Alquivo, dirígete a él para conocer su información de privacidad; también puedes escribirnos para trasladarle una solicitud relacionada con sus datos, sin revelar información de otra cuenta.",
        ],
        links: [links.processing],
      },
      {
        id: "finalidades",
        title: "2. Datos, finalidades y bases jurídicas",
        table: {
          headings: ["Uso", "Datos necesarios", "Base jurídica"],
          rows: [
            [
              "Crear y mantener tu cuenta",
              "Nombre, correo, contraseña protegida, preferencias, fecha y versión de condiciones aceptadas.",
              "Ejecución del contrato (art. 6.1.b RGPD).",
            ],
            [
              "Gestionar tu cartera",
              "Inmuebles, contratos, contactos, importes, fechas, incidencias, fotografías y documentos que aportes.",
              "Prestación del servicio; respecto a datos de terceros, instrucciones del propietario según el acuerdo de encargo.",
            ],
            [
              "Atender consultas y tickets",
              "Nombre, correo cuando lo facilites, asunto, mensajes, adjuntos y estado de la conversación.",
              "Contrato o medidas precontractuales cuando correspondan; interés legítimo en atender y gestionar las demás consultas.",
            ],
            [
              "Proteger cuentas y prevenir abuso",
              "Datos de sesión, verificaciones, dispositivos de confianza, eventos de acceso y datos técnicos de conexión.",
              "Interés legítimo en proteger el servicio y a sus usuarios (art. 6.1.f RGPD).",
            ],
            [
              "Cumplir obligaciones y atender reclamaciones",
              "La información estrictamente necesaria para cada obligación o reclamación.",
              "Obligación legal (art. 6.1.c RGPD) e interés legítimo en la defensa de derechos.",
            ],
          ],
        },
        paragraphs: [
          "Los datos se obtienen de ti, de las operaciones que realizas en la aplicación y de la conexión necesaria para usarla. Los datos de terceros proceden del propietario que los incorpora. Los campos señalados como obligatorios son necesarios para completar el registro o atender la solicitud; no proporcionarlos puede impedir esa operación. El contenido adicional de documentos y mensajes lo decides tú.",
          "La beta no solicita tarjetas ni cobra cuotas. No utilizamos tus contratos para publicidad, no vendemos datos personales y no se han integrado herramientas de seguimiento publicitario o analítica de terceros. Las métricas técnicas de funcionamiento no se utilizan para evaluar la solvencia de personas.",
        ],
      },
      {
        id: "soporte",
        title: "3. Conversaciones de soporte",
        paragraphs: [
          "Los tickets permiten abrir varias consultas y seguir cada conversación. Pueden ver su contenido el titular de la conversación y el personal autorizado de soporte. El permiso de soporte no concede acceso a la cartera inmobiliaria del usuario. Este chat se atiende por personas y no utiliza IA.",
          "Cuando hay correo operativo, las notificaciones al equipo indican que existe un ticket o una respuesta y cómo abrir la bandeja. No incluyen el texto ni los adjuntos del ticket. Si utilizas el formulario alternativo que envía un correo o escribes directamente al buzón, el proveedor de correo procesa el contenido que envías.",
          "Sin iniciar sesión, el acceso a tus tickets depende de la sesión de ese navegador. Indicar una dirección de correo no acredita su titularidad ni permite recuperar el historial en otro dispositivo. Si la sesión caduca, puedes perder el acceso aunque el ticket siga conservado. Las consultas abiertas desde tu cuenta quedan vinculadas a ella.",
        ],
      },
      {
        id: "ia",
        title: "4. Asistente de IA opcional",
        paragraphs: [
          "El asistente solo se utiliza cuando la función está habilitada y aceptas su aviso específico. La activación es voluntaria y puedes revocarla. Para los datos personales que aportas en tu propio nombre, esa elección se basa en tu consentimiento; para los datos de otras personas, el envío es una instrucción del propietario y requiere que este disponga de su propia base jurídica. Tu aceptación no sustituye los derechos de los inquilinos.",
          "Cuando utilizas el chat, OpenAI recibe tu pregunta, el historial reciente y los datos seleccionados que necesita para responder. Pueden incluir nombres de contactos y su relación con contratos, nombres de inmuebles, títulos de incidencias, importes y fechas. Las herramientas del chat no recuperan DNI, correos, teléfonos ya guardados, notas anteriores, direcciones completas ni el contenido de los documentos. Si escribes esos datos en un mensaje, o los incluyes en nombres o títulos, también pueden llegar al proveedor; evita hacerlo si no es necesario.",
          "Las propuestas de gastos, cobros o cambios de datos requieren tu confirmación antes de guardarse como operaciones reales. Borrar el chat no borra las operaciones que ya hayas confirmado. El servicio no adopta decisiones exclusivamente automatizadas con efectos jurídicos o similares sobre ti o tus inquilinos.",
          "El historial y los borradores del asistente se eliminan a los 30 días mediante la tarea de limpieza; también puedes borrarlos o desactivar la función. Las métricas de uso, coste y trazabilidad sin el texto de la conversación se conservan durante 12 meses completos más el mes en curso. La aplicación solicita al proveedor que no almacene las respuestas mediante la opción de almacenamiento de la API; eso no garantiza la inexistencia de registros de seguridad del proveedor. Sus condiciones, ubicación y garantías deben figurar en la relación de proveedores antes de activar el servicio público.",
          "La importación documental disponible para pruebas administrativas utiliza una simulación local que no envía archivos a OpenAI. Tiene un aviso separado y almacena sus borradores y fragmentos cifrados hasta 30 días o hasta revocar ese permiso. Los originales y las operaciones confirmadas siguen en sus módulos. Antes de habilitar análisis documental real se informará del proveedor y de los datos enviados y se solicitará una activación independiente; el permiso del chat no lo cubre.",
        ],
        links: [
          {
            to: "/data-processing#proveedores",
            label: "Proveedores y transferencias de datos",
          },
        ],
      },
      {
        id: "conservacion",
        title: "5. Cuánto tiempo conservamos los datos",
        table: {
          headings: ["Información", "Conservación"],
          rows: [
            [
              "Cuenta, cartera y documentos",
              "Mientras uses el servicio; puedes solicitar o ejecutar la eliminación. El borrado de archivos pendiente se reintenta hasta completarlo.",
            ],
            [
              "Tickets de soporte",
              "180 días desde el último mensaje o cambio de estado. Los vinculados a una cuenta también se eliminan al borrar esa cuenta.",
            ],
            [
              "Conversaciones y borradores de IA",
              "30 días, o antes si los eliminas o revocas la función conforme a su aviso. Las operaciones confirmadas permanecen en la cartera.",
            ],
            [
              "Métricas de uso y coste de IA",
              "12 meses completos más el mes en curso, sin el contenido de los mensajes.",
            ],
            [
              "Registro de auditoría de seguridad de la aplicación",
              "30 días. Incluye identificador de usuario y una huella de la conexión, sin contraseñas ni documentos.",
            ],
            [
              "Dispositivos de confianza",
              "El reconocimiento dura como máximo 90 días. El registro técnico se elimina al revocarlo, sustituirlo por otros dispositivos según el límite de la cuenta o eliminar la cuenta.",
            ],
            ["Copias de seguridad", operator.backupRetention || pendingValue],
            [
              "Mensajes del buzón y formularios enviados por correo",
              operator.mailboxRetention || pendingValue,
            ],
            [
              "Registros del alojamiento y servicios técnicos",
              operator.infrastructureLogRetention || pendingValue,
            ],
          ],
        },
        paragraphs: [
          "Los plazos técnicos de limpieza pueden requerir la siguiente ejecución programada. La eliminación en el sistema activo no equivale al borrado instantáneo de todas las copias de seguridad: estas deben caducar según el plazo indicado y no se utilizan para fines ordinarios. Si se restaura una copia, deben reaplicarse las solicitudes de borrado.",
          "Cuando una obligación legal o una reclamación justifique conservar datos concretos, se limitarán a lo necesario, con acceso restringido durante el plazo aplicable, y se eliminarán al finalizar. No se conservará por ese motivo toda una cartera de forma indefinida.",
        ],
      },
      {
        id: "destinatarios",
        title: "6. Quién puede acceder y dónde se tratan los datos",
        paragraphs: [
          "Acceden las personas autorizadas que lo necesitan para su función y los proveedores contratados de alojamiento, almacenamiento, copias y correo. El proveedor de IA interviene únicamente en las funciones activadas que lo requieren. Podemos comunicar datos a autoridades cuando exista una obligación legal.",
          "Los proveedores deben estar sujetos a acuerdos adecuados de protección de datos. Si hay acceso o tratamiento desde fuera del Espacio Económico Europeo, se indicarán el país y la garantía aplicable, como una decisión de adecuación vigente o cláusulas contractuales tipo acompañadas de las medidas necesarias. No debe entenderse que todos los datos permanecen en Europa por el mero hecho de utilizar un servidor europeo.",
        ],
        links: [
          {
            to: "/data-processing#proveedores",
            label: "Consultar proveedores, ubicaciones y garantías",
          },
        ],
      },
      {
        id: "derechos",
        title: "7. Tus derechos y cómo ejercerlos",
        paragraphs: [
          `${contact} Puedes pedir acceso, rectificación, supresión, limitación, portabilidad cuando proceda u oponerte a tratamientos basados en interés legítimo. Si un uso depende de tu consentimiento, puedes retirarlo en cualquier momento sin afectar a la licitud de lo ya realizado. Indica qué derecho quieres ejercer y la cuenta o información afectada. No envíes un DNI por defecto: solo pediremos información adicional proporcionada si es necesaria para comprobar tu identidad.`,
          "Responderemos, con carácter general, en un mes desde la recepción. Si la complejidad o el número de solicitudes justifican una ampliación legal, te informaremos dentro de ese primer mes. Puedes reclamar ante la Agencia Española de Protección de Datos si consideras que tus derechos no han sido atendidos, y ante la autoridad que te corresponda según la normativa aplicable.",
        ],
        externalLinks: [
          {
            href: "https://www.aepd.es/",
            label: "Agencia Española de Protección de Datos",
          },
        ],
      },
      {
        id: "seguridad",
        title: "8. Seguridad y actualizaciones",
        paragraphs: [
          "La aplicación limita el acceso por cuenta y cartera y almacena los documentos de forma privada y cifrada. Las contraseñas se guardan mediante una función de hash. Ofrece doble factor y controles de acceso para las funciones de soporte. Estas medidas reducen riesgos, pero ningún sistema permite prometer seguridad absoluta.",
          "La fecha de esta página identifica la versión de la información. Comunicaremos cambios relevantes y pediremos una nueva autorización si resulta necesaria para una función opcional. Puedes consultar por separado el uso de cookies y almacenamiento del navegador.",
        ],
        links: [links.cookies],
      },
    ],
  },
  {
    name: "cookies",
    path: "/cookies",
    title: "Política de cookies",
    label: "Cookies",
    summary:
      "Alquivo utiliza almacenamiento técnico para el acceso y la seguridad. No incorpora cookies de publicidad ni analítica de terceros.",
    sections: [
      {
        id: "uso",
        title: "1. Qué son y para qué se utilizan",
        paragraphs: [
          "Las cookies son pequeños valores que el navegador guarda y envía al sitio. El almacenamiento local permite guardar información en ese navegador sin enviarla automáticamente en cada solicitud. Alquivo utiliza estas tecnologías para mantener la sesión solicitada, proteger formularios, recordar dispositivos cuando lo pides y mostrar información de tu cuenta.",
        ],
      },
      {
        id: "inventario",
        title: "2. Inventario de almacenamiento propio",
        table: {
          headings: ["Nombre y tipo", "Finalidad", "Duración"],
          rows: [
            [
              "alquivo-session (cookie de sesión)",
              "Mantener el acceso y vincular los tickets de visitantes a su sesión. El nombre puede variar según el despliegue.",
              "Configuración de referencia: 120 minutos de inactividad; la actividad renueva la sesión.",
            ],
            [
              "XSRF-TOKEN (cookie técnica)",
              "Proteger las peticiones frente a solicitudes fraudulentas.",
              "Vinculada a la duración de la sesión, con renovación durante el uso.",
            ],
            [
              "alquivo_trusted_device (cookie opcional de seguridad)",
              "Reconocer este dispositivo cuando marcas «recordar este dispositivo» al superar el doble factor.",
              "Máximo de 90 días desde su emisión; no se renueva por el mero hecho de iniciar sesión.",
            ],
            [
              "alquivo_user y alquivo_portfolio (almacenamiento local)",
              "Mostrar datos básicos de la cuenta y cartera. No sustituyen las cookies de autenticación ni dan acceso por sí solos.",
              "Hasta cerrar sesión, limpiar los datos del sitio o sustituirlos por una actualización. El navegador también puede eliminarlos.",
            ],
          ],
        },
        paragraphs: [
          "Algunas instalaciones antiguas pueden conservar claves de visualización ig_user e ig_portfolio, que se sustituyen por las actuales o se eliminan al cerrar sesión. No son identificadores publicitarios.",
        ],
      },
      {
        id: "consentimiento",
        title: "3. Elección y consentimiento",
        paragraphs: [
          "Las tecnologías estrictamente necesarias para el servicio que solicitas están exceptuadas del consentimiento de cookies. Por eso no mostramos un botón general para aceptar publicidad que no existe. Recordar un dispositivo es una opción que activas tú y puedes revocar desde la configuración de seguridad.",
          "Si incorporamos analítica o publicidad que requiera consentimiento, informaremos de sus proveedores, finalidades y duración y ofreceremos una elección real antes de activarlas. Rechazarlas no impedirá utilizar las funciones que no las necesiten.",
        ],
      },
      {
        id: "control",
        title: "4. Cómo eliminarlas",
        paragraphs: [
          "Puedes borrar o bloquear las cookies y los datos del sitio desde la configuración de privacidad de tu navegador. Cerrar sesión elimina la caché local de la cuenta, pero no equivale por sí solo a revocar todos los dispositivos de confianza. Utiliza también las opciones de seguridad si quieres que vuelva a solicitarse el doble factor.",
          "Bloquear el almacenamiento necesario puede impedir iniciar sesión o continuar un ticket como visitante. Si compartes ordenador, cierra sesión y evita recordar el dispositivo. Para consultas sobre privacidad, contacta con el titular indicado en el aviso legal.",
        ],
        links: [links.notice, links.privacy],
      },
    ],
  },
  {
    name: "data-processing",
    path: "/data-processing",
    title: "Acuerdo de encargo de tratamiento",
    label: "Tratamiento de datos",
    summary:
      "Cómo trata Alquivo los datos de tus inquilinos y otras personas cuando utilizas la aplicación como responsable del tratamiento.",
    sections: [
      {
        id: "partes",
        title: "1. Partes y alcance",
        paragraphs: [
          "Este acuerdo forma parte de las condiciones aceptadas al registrar la cuenta y se aplica cuando el usuario trata datos personales como responsable sujeto al RGPD. El responsable es el usuario o la entidad a la que representa. El encargado es el titular de Alquivo identificado en el aviso legal. No regula los tratamientos propios de cuenta, seguridad y relación comercial de Alquivo, que se describen en su política de privacidad.",
          "El objeto es prestar el servicio de gestión inmobiliaria siguiendo las instrucciones del responsable. Dura mientras se preste el servicio y hasta la devolución o supresión de los datos. En caso de conflicto sobre el tratamiento por cuenta del usuario, prevalece este acuerdo sobre otras condiciones del servicio.",
        ],
        links: [links.notice, links.privacy],
      },
      {
        id: "operaciones",
        title: "2. Operaciones, datos y personas afectadas",
        paragraphs: [
          "El servicio permite recoger, organizar, almacenar, consultar, actualizar, relacionar, exportar y suprimir datos y documentos. Trata datos identificativos y de contacto, relaciones contractuales, inmuebles, importes, fechas, pagos, incidencias y el contenido de los archivos que el responsable aporte. Las personas afectadas pueden ser propietarios, inquilinos, avalistas, representantes, proveedores y contactos relacionados con la gestión.",
          "No está diseñado para tratar categorías especiales de datos, antecedentes penales ni realizar evaluaciones automatizadas de solvencia. El responsable debe minimizar el contenido de los documentos y consultar previamente cualquier necesidad de tratamiento que exceda este alcance.",
        ],
      },
      {
        id: "instrucciones",
        title: "3. Instrucciones y deberes del responsable",
        paragraphs: [
          "Las operaciones que el usuario realiza en la aplicación, este acuerdo y las solicitudes verificadas al soporte constituyen instrucciones documentadas. El responsable determina las finalidades y su base jurídica, informa a los interesados y garantiza que las instrucciones son lícitas. La activación de una función opcional no constituye el consentimiento de las personas cuyos datos haya incorporado.",
          "Alquivo tratará los datos únicamente conforme a esas instrucciones, también respecto a transferencias internacionales, salvo obligación legal aplicable. En ese supuesto informará al responsable antes de tratar los datos, salvo prohibición legal. Si considera que una instrucción infringe la normativa, lo comunicará y podrá suspender esa operación hasta aclararla.",
        ],
      },
      {
        id: "confidencialidad",
        title: "4. Confidencialidad y seguridad",
        paragraphs: [
          "Alquivo limitará el acceso a las personas que lo necesiten para prestar el servicio y estén sujetas a compromisos de confidencialidad u obligaciones equivalentes. Esta obligación continuará al terminar la relación.",
          "Se aplicarán medidas técnicas y organizativas proporcionadas al riesgo: aislamiento de carteras y autorización de accesos, archivos privados cifrados, protección de credenciales, comunicaciones cifradas en el servicio público, gestión de permisos de soporte y administración, registro de eventos de seguridad, actualización de componentes y procedimientos de recuperación y borrado. Las copias y su restauración deben mantenerse de acuerdo con las condiciones operativas publicadas. El detalle técnico necesario podrá facilitarse de forma reservada, sin divulgar claves ni información que comprometa a otros usuarios.",
        ],
      },
      {
        id: "proveedores",
        title: "5. Subencargados y ubicación del tratamiento",
        providers: true,
        paragraphs: [
          "El responsable autoriza los proveedores identificados en la relación siguiente para los servicios descritos. La autorización no se extiende a proveedores sin identificar. Antes de la apertura pública deben figurar la entidad contratada, la ubicación relevante del tratamiento y la garantía de cualquier transferencia fuera del Espacio Económico Europeo.",
          "Alquivo impondrá a sus subencargados obligaciones de protección de datos equivalentes a las que correspondan en este acuerdo y seguirá respondiendo ante el responsable por el cumplimiento de sus obligaciones. Informará de las incorporaciones o sustituciones previstas con al menos 15 días de antelación, para que el responsable pueda objetar por razones de protección de datos. Si no se alcanza una solución, podrá dejar de utilizar la función afectada o finalizar el servicio con devolución o supresión de sus datos. No se someterán sus datos al proveedor objetado mientras se resuelve la objeción.",
          "La IA solo interviene cuando la función esté habilitada y el responsable la active tras consultar su aviso. Activar el chat no autoriza enviar archivos originales para análisis documental. No se atribuye al consentimiento del usuario la función de sustituir las garantías legales de una transferencia internacional.",
        ],
      },
      {
        id: "derechos",
        title: "6. Ayuda para atender derechos",
        paragraphs: [
          "Alquivo ayudará al responsable, teniendo en cuenta la naturaleza del tratamiento, a responder a solicitudes de acceso, rectificación, supresión, oposición, limitación y portabilidad. Si recibe directamente una solicitud sobre datos tratados por cuenta del usuario, se la trasladará sin dilación indebida y no resolverá por su cuenta salvo autorización u obligación legal.",
          `El responsable puede pedir esa asistencia a través del soporte. ${contact} Se utilizará la información mínima necesaria para identificar la solicitud y comprobar la legitimidad de quien la formula.`,
        ],
      },
      {
        id: "incidentes",
        title: "7. Incidentes y evaluación de riesgos",
        paragraphs: [
          "Si Alquivo conoce una violación de seguridad de los datos tratados por cuenta del responsable, se lo comunicará sin dilación indebida. Facilitará, según esté disponible, la naturaleza del incidente, las categorías de datos y personas afectadas, sus posibles consecuencias, las medidas adoptadas y un contacto para seguimiento. Podrá completar la información de forma progresiva.",
          "Alquivo colaborará con el responsable en la valoración de riesgos, las comunicaciones a autoridades o interesados, las evaluaciones de impacto y las consultas previas que procedan, teniendo en cuenta la información disponible y el alcance del servicio. El responsable conserva las decisiones y obligaciones que le correspondan; Alquivo mantiene las propias.",
        ],
      },
      {
        id: "devolucion",
        title: "8. Devolución y supresión",
        paragraphs: [
          "Al finalizar el servicio, el responsable podrá obtener sus datos mediante las descargas y exportaciones disponibles o solicitar al soporte la devolución del resto en un formato de uso común. Debe hacerlo antes de ordenar un borrado irreversible. Alquivo suprimirá los datos por instrucción del responsable y al concluir su tratamiento, salvo conservación exigida por la ley.",
          "Los fallos de borrado de archivos se reintentan. Las copias de seguridad se eliminan conforme al plazo publicado, permanecen aisladas del uso ordinario y, si se restauran, deben reaplicarse las instrucciones de supresión. La finalización no autoriza reutilizar los datos para otros fines.",
        ],
        links: [
          { to: "/privacy#conservacion", label: "Plazos de conservación" },
        ],
      },
      {
        id: "evidencias",
        title: "9. Información y comprobaciones",
        paragraphs: [
          "Alquivo pondrá a disposición del responsable la información necesaria para acreditar las obligaciones de este acuerdo y permitirá y contribuirá a comprobaciones razonables, incluidas auditorías por el responsable o un auditor autorizado. Se coordinarán su alcance y forma para proteger la seguridad, la continuidad del servicio y los datos de otras personas, sin impedir el ejercicio efectivo de este derecho ni las facultades de la autoridad de control.",
        ],
      },
    ],
  },
];
