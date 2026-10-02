import { createI18n } from "vue-i18n";

const messages = {
  es: {
    language: { choose: "Idioma de la aplicación", numberLocale: "es-ES" },
    privacy: {
      heading: "Sobre tus datos.",
      controller: "Responsable:",
      unknown: "titular de Alquivo (identificación pendiente)",
      register:
        "Usamos tus datos para crear y gestionar tu cuenta, ejecutar el contrato y proteger el servicio.",
      support:
        "Usamos tus datos y los archivos que compartas para atender esta consulta: por el contrato o las medidas precontractuales, o por el interés legítimo en gestionar otras consultas.",
      access:
        "Acceden el personal autorizado y los proveedores necesarios que se identifican en la política de privacidad. Puedes ejercer tus derechos en",
      full: "Leer la información completa",
    },
    legal: {
      heading: "Información legal",
      notice: "Aviso legal",
      terms: "Condiciones",
      privacy: "Privacidad",
      cookies: "Cookies",
      processing: "Tratamiento de datos",
      spanishOnly:
        "El texto jurídico vigente está disponible en español. Esta interfaz en inglés es una ayuda de navegación, no una traducción contractual. Si necesitas aclaraciones, contacta con soporte.",
    },

    common: {
      login: "Entrar",
      startFree: "Empieza gratis",
      email: "Email",
      password: "Contraseña",
      name: "Nombre",
      confirmPassword: "Confirma la contraseña",
      backToHome: "Volver al inicio de Alquivo",
      wait: "Un momento…",
      retry: "Reintentar",
    },
    nav: {
      dashboard: "Resumen",
      properties: "Propiedades",
      leases: "Alquileres",
      people: "Personas",
      finance: "Finanzas",
      reports: "Informes",
      tax: "Fiscalidad",
      issues: "Incidencias",
      calendar: "Calendario",
      documents: "Documentos",
      settings: "Configuración",
      plans: "Planes",
      support: "Ayuda y soporte",
      portfolio: "Tu patrimonio",
      more: "Más herramientas",
      personalSpace: "Espacio personal",
      teamInbox: "Bandeja del equipo",
      tour: "Ver recorrido",
      account: "Mi cuenta personal",
      signOut: "Cerrar sesión",
      closeNav: "Cerrar navegación",
      openNav: "Abrir navegación",
      skip: "Saltar al contenido",
      breadcrumb: "Mi espacio",
      verifyPrompt: "Confirma tu correo para proteger tu cuenta.",
      resend: "Reenviar",
      sending: "Enviando…",
      notNow: "Ahora no",
      adminNotice:
        "Administrador local · Funciones en pruebas visibles solo para esta cuenta. Los pagos siguen bloqueados durante la beta y la IA mantiene sus límites de consumo.",
      readOnlyNotice:
        "Tu plan permite gestionar {limit} inmuebles. Los {count} restantes están en modo consulta, sin borrar ningún dato.",
      viewPlan: "Ver mi plan",
      legalInfo: "Información legal",
      resendWait: "Espera un minuto antes de volver a solicitar el enlace.",
      resendError:
        "No se pudo solicitar el enlace. Inténtalo de nuevo más tarde.",
      logoutError: "No se pudo cerrar la sesión. Inténtalo de nuevo.",
      englishPreview:
        "Traducción en curso: algunas pantallas y mensajes de soporte siguen en español.",
    },
    auth: {
      storyEyebrow: "Tu patrimonio, con claridad",
      storyTitle: "El control de hoy, un mayor mañana.",
      storyBody:
        "Gestiona tus alquileres, entiende tu rentabilidad y haz crecer tu patrimonio desde un único lugar.",
      storyFoot: "Controla hoy. Decide mejor mañana.",
      welcome: "Bienvenido de nuevo",
      createPortfolio: "Crea tu cartera",
      access: "Accede a Alquivo",
      firstProperty: "Empieza añadiendo tu primer inmueble.",
      continue: "Continúa donde lo dejaste.",
      betaIntro:
        "Estás entrando en la beta de Alquivo. El registro está abierto: puedes gestionar inmuebles, alquileres, cobros y documentos, y contarnos qué mejorarías.",
      verified: "Correo confirmado. Ya puedes entrar.",
      verifyYou: "Verifica que eres tú",
      emailUnavailable:
        "No hemos podido enviar el código. Prueba el autenticador o un código de recuperación.",
      emailCodePrefix: "Te hemos enviado un código de 6 cifras a",
      verifiedEmail: "tu correo verificado",
      emailCodeExpiry: "Caduca en 5 minutos.",
      recoveryHint:
        "Introduce uno de tus códigos de recuperación. Solo se puede usar una vez.",
      authenticatorHint:
        "Introduce el código de 6 cifras de tu aplicación de autenticación. Si acabas de usar ese código, espera a que cambie.",
      recoveryCode: "Código de recuperación",
      accessCode: "Código de acceso",
      rememberDevice: "Recordar este dispositivo durante 90 días",
      sharedDevice: "No lo actives en un dispositivo compartido.",
      verifying: "Verificando…",
      verifyAndEnter: "Verificar y entrar",
      sendAnother: "Enviar otro código",
      useRecovery: "Usar un código de recuperación",
      backToCode: "Volver al código de verificación",
      backToCredentials: "Volver al correo y contraseña",
      portfolioName: "Nombre de la cartera",
      forgotPassword: "He olvidado mi contraseña",
      createMyPortfolio: "Crear mi cartera",
      haveAccount: "¿Ya tienes cuenta?",
      noAccount: "¿Todavía no tienes cuenta?",
      createAccount: "Crear cuenta",
      acceptPrefix: "Acepto las",
      terms: "condiciones de uso",
      acceptSuffix:
        ", incluido el acuerdo de encargo cuando corresponda. He leído el aviso de privacidad anterior.",
      accessError: "No hemos podido completar el acceso.",
      codeError: "No se pudo enviar el código.",
    },
    recovery: {
      secureAccess: "Acceso seguro",
      story: "Recupera el control de tu cartera.",
      account: "Cuenta",
      newPassword: "Nueva contraseña",
      recoverPassword: "Recuperar contraseña",
      passwordUpdated: "Contraseña actualizada. Ya puedes entrar.",
      sentIfExists: "Si existe la cuenta, recibirás un enlace por email.",
      savePassword: "Guardar contraseña",
      sendLink: "Enviar enlace",
      backToLogin: "Volver al acceso",
      requestError: "No se pudo completar la solicitud.",
    },
    marketing: {
      how: "Cómo funciona",
      assistant: "Asistente IA",
      features: "Funciones",
      plans: "Planes",
      guides: "Guías",
      freeBeta: "Beta gratuita",
      freePlan: "Plan gratuito",
      assistantAvailable: "Asistente IA disponible",
      assistantSoon: "Asistente IA en preparación",
      heroFirst: "Gestiona tu cartera hoy.",
      heroSecond: "Entiéndela mejor con IA.",
      heroWithAI:
        "Organiza inmuebles, cobros y contratos desde un mismo lugar. Pregunta al asistente por la información que hayas registrado y decide si quieres activarlo tras leer su aviso de privacidad.",
      heroWithoutAI:
        "Organiza inmuebles, cobros y contratos desde un mismo lugar. Estamos preparando un asistente para consultar los datos que registres en Alquivo.",
      tryBeta: "Probar la beta gratis",
      createFree: "Crear cuenta gratis",
      learnAssistant: "Conocer el asistente",
      noCard: "Sin tarjeta",
      betaPropertyLimit: "Hasta 50 inmuebles",
      noAutoRenew: "Sin renovación automática",
      previewPortfolio: "Tu patrimonio",
      thisYear: "+ 4,2% este año",
      monthlyIncome: "Ingresos del mes",
      plannedExpenses: "Gastos previstos",
      thisMonth: "Este mes",
      netYield: "Rentabilidad neta",
      stable: "Estable",
      incomeTrend: "Evolución de ingresos",
      comingUp: "Próximamente",
      rentPayment: "Cobro alquiler · 3 sep.",
      contractRenewal: "Renovación contrato · 12 sep.",
      sampleDisclaimer: "Ejemplo ilustrativo con datos ficticios.",
      available: "Disponible",
      inPreparation: "En preparación",
      simpleQuestion: "Una pregunta sencilla.",
      contextualAnswer: "Una respuesta con contexto.",
      assistantDetailOn:
        "Consulta con el asistente la información que tengas registrada: alquileres, cobros, contratos y avisos. Si falta un dato, te lo dirá. Tú decides si lo activas antes de usarlo.",
      assistantDetailOff:
        "Estamos preparando un asistente que consultará la información que registres: alquileres, cobros, contratos y avisos. Te avisaremos cuando esté disponible.",
      basedOnRecords: "Respuestas basadas en tus registros",
      clearAmounts: "Importes y fechas fáciles de revisar",
      noInventing: "Sin inventar información que falta",
      startBeta: "Empezar con la beta gratuita",
      startFree: "Empezar gratis",
      futureConversation:
        "Ejemplo ilustrativo de una conversación futura con Alquivo AI",
      preview: "Vista previa",
      fictionalExample: "Ejemplo ilustrativo · Sin datos reales",
      exampleQuestion: "¿Qué alquileres y contratos debería revisar?",
      exampleAnswerTitle: "Así responderá Alquivo",
      exampleAnswer:
        "Consultaré los cobros y las fechas que hayas registrado. Te mostraré los datos y de dónde salen; si falta información, te lo indicaré.",
      topics: "Temas de consulta previstos",
      payments: "Cobros",
      contracts: "Contratos",
      alerts: "Avisos",
      lessSpreadsheets: "Menos hojas de cálculo. Más perspectiva.",
      essentials: "Lo esencial, donde debe estar.",
      notERP:
        "No necesitas aprender un ERP. Añade tu cartera y deja que Alquivo ordene el día a día.",
      addPortfolio: "Añade tu cartera",
      addPortfolioBody:
        "Registra cada inmueble y su información clave sin formularios interminables.",
      organiseMovements: "Ordena tus movimientos",
      organiseMovementsBody:
        "Ten a mano cobros, gastos y vencimientos para saber qué ocurre este mes.",
      decideClearly: "Decide con claridad",
      decideClearlyBody:
        "Consulta el valor, el resultado y la rentabilidad de tu patrimonio de un vistazo.",
      fullView: "Una visión completa, sin ruido.",
      madeForInvestors: "Hecho para quien invierte.",
      oneView: "Tu patrimonio, en una sola vista",
      oneViewBody:
        "Valor estimado, ingresos, gastos y rentabilidad en un resumen que puedes entender en segundos.",
      annualNet: "Resultado neto anual",
      exampleNotForecast: "Ejemplo ilustrativo, no una previsión de resultados",
      paymentsControl: "Cobros bajo control",
      paymentsControlBody:
        "Registra alquileres e incidencias de pago y detecta rápidamente lo pendiente.",
      nothingMissed: "Nada se te pasa",
      nothingMissedBody:
        "Contratos, renovaciones y recordatorios en una agenda pensada para propietarios.",
      documentsPlace: "Documentos, en su sitio",
      documentsPlaceBody:
        "Guarda contratos y facturas junto al inmueble al que pertenecen.",
      attendImportant: "Atiende lo importante",
      attendImportantBody:
        "Centraliza incidencias para no depender de mensajes y notas dispersas.",
      peaceOfMind: "Pensado para tu tranquilidad.",
      yourData: "La información de tus inmuebles es tuya.",
      isolatedPortfolio: "Cada cartera opera de forma aislada.",
      privateDocuments: "Tus documentos se guardan de forma privada.",
      noCardToStart: "Puedes empezar sin compartir una tarjeta.",
      startGently: "Empieza con calma.",
      betaFree: "La beta es gratis. Tu opinión nos ayuda a crecer.",
      planGrows: "Un plan que acompaña tu cartera.",
      betaDetails:
        "Hasta 50 inmuebles y 5 GB de documentos y fotos durante toda la beta, sin tarjeta ni pagos. Incluye las funciones ya disponibles; no las que siguen en revisión.",
      freeDetails:
        "Empieza con el plan gratuito y amplía cuando lo necesites. Sin tarjeta ni pagos automáticos.",
      priceInformative:
        "Estamos validando Alquivo: puedes probarlo gratis. Los precios son informativos; todavía no aceptamos pagos.",
      betaAccess: "Acceso durante toda la beta",
      founderPrice: "Precio fundador",
      perMonth: "/ mes",
      free: "Gratis",
      forOne: "Para empezar con un inmueble.",
      forUpTo: "Para gestionar hasta {count} inmuebles.",
      yearlyPrice: "{price} al año si prefieres pagar anualmente.",
      dashboardFeature: "Dashboard, alquileres y finanzas",
      propertyLimitFeature: "Hasta 50 inmuebles",
      storageFeature: "5 GB de documentos y fotos",
      reportsFeature: "Informes y exportación de datos",
      faqEyebrow: "Dudas habituales.",
      faqTitle: "Claro desde el principio.",
      faqCard: "¿Necesito tarjeta para probar Alquivo?",
      faqCardBeta: "No. La Beta es gratuita y no te pediremos datos de pago.",
      faqCardFree:
        "No. Puedes empezar con el plan gratuito sin tarjeta. Solo pagarás si decides contratar un plan.",
      faqAfterBeta: "¿Qué pasará al terminar la beta?",
      faqFreePlan: "¿Puedo seguir en el plan gratuito?",
      faqAfterBetaAnswer:
        "Te avisaremos con antelación y te ofreceremos condiciones especiales de agradecimiento por haber participado. Tú decidirás si quieres continuar: no habrá ningún cobro automático. Podrás exportar tus datos antes del cambio.",
      faqFreePlanAnswer:
        "Sí. Puedes gestionar un inmueble con el plan gratuito y decidir cuándo quieres ampliar. No se realiza ningún cobro automático.",
      faqAI: "¿Ya puedo usar el asistente de IA?",
      faqAIYes:
        "Sí. Si tu plan incluye consultas, puedes activar el asistente tras revisar el aviso de privacidad. Responde usando los datos que hayas registrado.",
      faqAINo:
        "Aún estamos revisando sus respuestas. Te avisaremos cuando esté disponible; ya puedes organizar tus inmuebles, alquileres y finanzas.",
      faqTypes: "¿Puedo gestionar distintos tipos de inmueble?",
      faqTypesAnswer:
        "Sí. Alquivo está preparado para viviendas, locales, oficinas, garajes, trasteros, terrenos y edificios.",
      faqAdvisor: "¿Alquivo sustituye a mi asesor?",
      faqAdvisorAnswer:
        "No. Es tu espacio para organizar el patrimonio y tener una visión clara. No ofrece asesoramiento fiscal, legal ni financiero.",
      whatIs: "Qué es Alquivo",
      whatIsTitle: "gestión de alquileres para propietarios.",
      whatIsBody:
        "Alquivo es una aplicación web para organizar inmuebles, inquilinos, contratos, cobros, gastos, documentos e incidencias. Está pensada para propietarios particulares y pequeños inversores que quieren tener una visión clara de su cartera.",
      softwareCategory: "Software de gestión inmobiliaria",
      webBrowser: "Navegador web",
      notAgency:
        "No es una inmobiliaria, no cobra automáticamente a tus inquilinos y no sustituye a tu asesor. Los resúmenes dependen de los datos que registres.",
      resources: "Recursos para propietarios",
      resourcesTitle: "Empieza por tenerlo claro.",
      startToday: "Empieza hoy",
      closingFirst: "Deja de buscar tus números.",
      closingSecond: "Empieza a entenderlos.",
      ownSpace: "Tu cartera merece un espacio propio.",
      createAccount: "Crear mi cuenta gratis",
      footer: "Gestiona tus alquileres sin complicaciones.",
      ownerGuides: "Guías para propietarios",
      betaName: "Beta gratuita",
      mainNav: "Navegación principal",
      accessConditions: "Condiciones de acceso",
      productPreview: "Vista previa del panel de Alquivo",
      guidesSpanish:
        "Las guías detalladas todavía están disponibles solo en español. Puedes usar el resto de Alquivo en inglés.",
    },
  },
  en: {
    language: { choose: "Application language", numberLocale: "en-GB" },
    privacy: {
      heading: "About your data.",
      controller: "Data controller:",
      unknown: "Alquivo operator (identification pending)",
      register:
        "We use your data to create and manage your account, perform the contract and protect the service.",
      support:
        "We use your data and the files you share to handle this request, under the contract or pre-contractual steps, or our legitimate interest in handling other enquiries.",
      access:
        "Authorised staff and the necessary providers named in our privacy policy can access your data. You can exercise your rights by emailing",
      full: "Read the full information",
    },
    legal: {
      heading: "Legal information",
      notice: "Legal notice",
      terms: "Terms",
      privacy: "Privacy",
      cookies: "Cookies",
      processing: "Data processing",
      spanishOnly:
        "The authoritative legal text is currently available in Spanish. This English interface helps you navigate; it is not a contractual translation. Contact support if you need clarification.",
    },

    common: {
      login: "Log in",
      startFree: "Get started free",
      email: "Email",
      password: "Password",
      name: "Name",
      confirmPassword: "Confirm password",
      backToHome: "Back to the Alquivo homepage",
      wait: "One moment…",
      retry: "Try again",
    },
    nav: {
      dashboard: "Overview",
      properties: "Properties",
      leases: "Rentals",
      people: "People",
      finance: "Finances",
      reports: "Reports",
      tax: "Spanish taxes",
      issues: "Issues",
      calendar: "Calendar",
      documents: "Documents",
      settings: "Settings",
      plans: "Plans",
      support: "Help and support",
      portfolio: "Your portfolio",
      more: "More tools",
      personalSpace: "Personal space",
      teamInbox: "Team inbox",
      tour: "Take the tour",
      account: "My account",
      signOut: "Log out",
      closeNav: "Close navigation",
      openNav: "Open navigation",
      skip: "Skip to content",
      breadcrumb: "My space",
      verifyPrompt: "Confirm your email to protect your account.",
      resend: "Resend",
      sending: "Sending…",
      notNow: "Not now",
      adminNotice:
        "Local admin · Experimental features are only visible to this account. Payments remain disabled during beta and AI usage limits still apply.",
      readOnlyNotice:
        "Your plan lets you manage {limit} properties. The remaining {count} are read-only; no data has been deleted.",
      viewPlan: "View my plan",
      legalInfo: "Legal information",
      resendWait: "Please wait a minute before requesting another link.",
      resendError: "We couldn't request the link. Please try again later.",
      logoutError: "We couldn't log you out. Please try again.",
      englishPreview:
        "Translation in progress: some screens and support messages are still in Spanish.",
    },
    auth: {
      storyEyebrow: "A clearer view of your property portfolio",
      storyTitle: "Stay in control today. Build more tomorrow.",
      storyBody:
        "Manage rentals, understand your returns and grow your property portfolio in one place.",
      storyFoot: "Take control today. Make better decisions tomorrow.",
      welcome: "Welcome back",
      createPortfolio: "Create your portfolio",
      access: "Log in to Alquivo",
      firstProperty: "Start by adding your first property.",
      continue: "Pick up where you left off.",
      betaIntro:
        "You're joining the Alquivo beta. Registration is open: manage properties, rentals, payments and documents, and tell us what we could improve.",
      verified: "Email confirmed. You can now log in.",
      verifyYou: "Verify it's you",
      emailUnavailable:
        "We couldn't send the code. Try your authenticator app or a recovery code.",
      emailCodePrefix: "We sent a six-digit code to",
      verifiedEmail: "your verified email",
      emailCodeExpiry: "It expires in five minutes.",
      recoveryHint:
        "Enter one of your recovery codes. Each code can only be used once.",
      authenticatorHint:
        "Enter the six-digit code from your authenticator app. If you've just used it, wait for the next code.",
      recoveryCode: "Recovery code",
      accessCode: "Access code",
      rememberDevice: "Remember this device for 90 days",
      sharedDevice: "Don't enable this on a shared device.",
      verifying: "Verifying…",
      verifyAndEnter: "Verify and log in",
      sendAnother: "Send another code",
      useRecovery: "Use a recovery code",
      backToCode: "Back to verification code",
      backToCredentials: "Back to email and password",
      portfolioName: "Portfolio name",
      forgotPassword: "Forgot your password?",
      createMyPortfolio: "Create my portfolio",
      haveAccount: "Already have an account?",
      noAccount: "New to Alquivo?",
      createAccount: "Create account",
      acceptPrefix: "I accept the",
      terms: "terms of use",
      acceptSuffix:
        ", including the data processing agreement where applicable. I have read the privacy notice above.",
      accessError: "We couldn't complete your sign-in.",
      codeError: "We couldn't send the code.",
    },
    recovery: {
      secureAccess: "Secure access",
      story: "Get back into your portfolio.",
      account: "Account",
      newPassword: "New password",
      recoverPassword: "Reset password",
      passwordUpdated: "Password updated. You can now log in.",
      sentIfExists:
        "If the account exists, you'll receive an email with a reset link.",
      savePassword: "Save password",
      sendLink: "Send reset link",
      backToLogin: "Back to login",
      requestError: "We couldn't complete your request.",
    },
    marketing: {
      how: "How it works",
      assistant: "AI assistant",
      features: "Features",
      plans: "Plans",
      guides: "Guides",
      freeBeta: "Free beta",
      freePlan: "Free plan",
      assistantAvailable: "AI assistant available",
      assistantSoon: "AI assistant coming soon",
      heroFirst: "Manage your portfolio today.",
      heroSecond: "Understand it better with AI.",
      heroWithAI:
        "Keep properties, rent payments and contracts in one place. Ask the assistant about the information you've entered and choose whether to enable it after reading its privacy notice.",
      heroWithoutAI:
        "Keep properties, rent payments and contracts in one place. We're preparing an assistant to help you explore your Alquivo data.",
      tryBeta: "Try the beta for free",
      createFree: "Create a free account",
      learnAssistant: "Meet the assistant",
      noCard: "No card required",
      betaPropertyLimit: "Up to 50 properties",
      noAutoRenew: "No automatic renewal",
      previewPortfolio: "Your portfolio",
      thisYear: "+4.2% this year",
      monthlyIncome: "Income this month",
      plannedExpenses: "Expected expenses",
      thisMonth: "This month",
      netYield: "Net yield",
      stable: "Stable",
      incomeTrend: "Income trend",
      comingUp: "Coming up",
      rentPayment: "Rent payment · Sep 3",
      contractRenewal: "Contract renewal · Sep 12",
      sampleDisclaimer: "Illustration using fictional data.",
      available: "Available",
      inPreparation: "In preparation",
      simpleQuestion: "A simple question.",
      contextualAnswer: "An answer with context.",
      assistantDetailOn:
        "Ask the assistant about your saved rentals, payments, contracts and alerts. If information is missing, it will tell you. You choose whether to enable it before using it.",
      assistantDetailOff:
        "We're preparing an assistant to explore the rentals, payments, contracts and alerts you save. We'll let you know when it's available.",
      basedOnRecords: "Answers based on your records",
      clearAmounts: "Amounts and dates you can check",
      noInventing: "No invented missing information",
      startBeta: "Start the free beta",
      startFree: "Get started free",
      futureConversation:
        "Illustration of a future conversation with Alquivo AI",
      preview: "Preview",
      fictionalExample: "Illustration · No real data",
      exampleQuestion: "Which rentals and contracts should I review?",
      exampleAnswerTitle: "How Alquivo will respond",
      exampleAnswer:
        "I'll check the payments and dates you've entered. I'll show you the figures and where they come from, and tell you if information is missing.",
      topics: "Planned question topics",
      payments: "Payments",
      contracts: "Contracts",
      alerts: "Alerts",
      lessSpreadsheets: "Fewer spreadsheets. More perspective.",
      essentials: "Everything essential, where it belongs.",
      notERP:
        "You don't need to learn an ERP. Add your portfolio and let Alquivo organise the everyday details.",
      addPortfolio: "Add your portfolio",
      addPortfolioBody:
        "Save each property and its key details without endless forms.",
      organiseMovements: "Organise your finances",
      organiseMovementsBody:
        "Keep payments, expenses and due dates close at hand, so you know what's happening this month.",
      decideClearly: "Decide with clarity",
      decideClearlyBody:
        "See your portfolio's value, results and yield at a glance.",
      fullView: "The full picture, without the noise.",
      madeForInvestors: "Made for property investors.",
      oneView: "Your portfolio at a glance",
      oneViewBody:
        "Estimated value, income, expenses and yield in a summary you can understand in seconds.",
      annualNet: "Annual net result",
      exampleNotForecast: "Illustration, not a forecast of returns",
      paymentsControl: "Stay on top of payments",
      paymentsControlBody:
        "Track rent and payment issues, and quickly spot what is outstanding.",
      nothingMissed: "Don't miss what matters",
      nothingMissedBody:
        "Contracts, renewals and reminders in a calendar built for owners.",
      documentsPlace: "Documents where they belong",
      documentsPlaceBody:
        "Keep contracts and invoices with the property they belong to.",
      attendImportant: "Focus on what matters",
      attendImportantBody:
        "Keep issues in one place instead of scattered messages and notes.",
      peaceOfMind: "Designed for peace of mind.",
      yourData: "Your property information belongs to you.",
      isolatedPortfolio: "Each portfolio is kept separate.",
      privateDocuments: "Your documents are stored privately.",
      noCardToStart: "Start without sharing card details.",
      startGently: "Start at your own pace.",
      betaFree: "The beta is free. Your feedback helps us grow.",
      planGrows: "A plan that grows with your portfolio.",
      betaDetails:
        "Up to 50 properties and 5 GB for documents and photos throughout the beta, without a card or payments. Includes released features, not those still under review.",
      freeDetails:
        "Start on the free plan and upgrade when you need to. No card or automatic payments.",
      priceInformative:
        "We're validating Alquivo: you can try it free. Prices are for information only; we are not accepting payments yet.",
      betaAccess: "Access throughout the beta",
      founderPrice: "Founder price",
      perMonth: "/ month",
      free: "Free",
      forOne: "For your first property.",
      forUpTo: "For up to {count} properties.",
      yearlyPrice: "{price} per year if you prefer annual billing.",
      dashboardFeature: "Dashboard, rentals and finances",
      propertyLimitFeature: "Up to 50 properties",
      storageFeature: "5 GB for documents and photos",
      reportsFeature: "Reports and data exports",
      faqEyebrow: "Common questions.",
      faqTitle: "Clear from the start.",
      faqCard: "Do I need a card to try Alquivo?",
      faqCardBeta: "No. The beta is free and we won't ask for payment details.",
      faqCardFree:
        "No. You can start on the free plan without a card. You only pay if you choose a paid plan.",
      faqAfterBeta: "What happens when the beta ends?",
      faqFreePlan: "Can I stay on the free plan?",
      faqAfterBetaAnswer:
        "We'll let you know in advance and offer special terms to thank you for taking part. You decide whether to continue: there will be no automatic charges. You can export your data before the change.",
      faqFreePlanAnswer:
        "Yes. You can manage one property on the free plan and decide when to upgrade. There are no automatic charges.",
      faqAI: "Can I use the AI assistant now?",
      faqAIYes:
        "Yes. If your plan includes AI questions, you can enable the assistant after reading its privacy notice. It answers using the data you have saved.",
      faqAINo:
        "We're still reviewing its answers. We'll let you know when it's ready; you can already organise your properties, rentals and finances.",
      faqTypes: "Can I manage different property types?",
      faqTypesAnswer:
        "Yes. Alquivo supports homes, commercial premises, offices, garages, storage units, land and buildings.",
      faqAdvisor: "Does Alquivo replace my adviser?",
      faqAdvisorAnswer:
        "No. It helps you organise your portfolio and see the full picture. It does not provide tax, legal or financial advice.",
      whatIs: "What is Alquivo",
      whatIsTitle: "rental management for property owners.",
      whatIsBody:
        "Alquivo is a web app for organising properties, tenants, contracts, payments, expenses, documents and issues. It is designed for individual owners and small investors who want a clear view of their portfolio.",
      softwareCategory: "Property management software",
      webBrowser: "Web browser",
      notAgency:
        "It is not an estate agency, it does not charge your tenants automatically and it does not replace your adviser. Summaries depend on the data you enter.",
      resources: "Resources for owners",
      resourcesTitle: "Start with a clearer picture.",
      startToday: "Start today",
      closingFirst: "Stop hunting for your numbers.",
      closingSecond: "Start understanding them.",
      ownSpace: "Your portfolio deserves its own space.",
      createAccount: "Create my free account",
      footer: "Manage rentals without the hassle.",
      ownerGuides: "Guides for owners",
      betaName: "Free beta",
      mainNav: "Main navigation",
      accessConditions: "Access terms",
      productPreview: "Alquivo dashboard preview",
      guidesSpanish:
        "Our detailed guides are currently available in Spanish only. You can use the rest of Alquivo in English.",
    },
  },
};

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: "es",
  fallbackLocale: "es",
  messages,
});

let adminLocalePreference = "es";

function applyLocale(locale) {
  i18n.global.locale.value = locale;
  if (typeof document !== "undefined") document.documentElement.lang = locale;
}

export function forceSpanish() {
  applyLocale("es");
}

export function syncLocaleForSession(isAdmin) {
  if (!isAdmin) {
    adminLocalePreference = "es";
    forceSpanish();
    return;
  }
  if (typeof window !== "undefined") {
    try {
      const saved = window.localStorage.getItem("alquivo:locale");
      if (saved === "es" || saved === "en") adminLocalePreference = saved;
    } catch {
      // Private browsing can disable storage; keep the in-memory choice.
    }
  }
  applyLocale(adminLocalePreference);
}

export function setLocale(locale, isAdmin = false) {
  if (locale !== "es" && locale !== "en") return;
  if (!isAdmin) {
    forceSpanish();
    return;
  }
  adminLocalePreference = locale;
  applyLocale(locale);
  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem("alquivo:locale", locale);
    } catch {
      // The choice still works for this browser session.
    }
  }
}

export function currentLocale() {
  return i18n.global.locale.value;
}
