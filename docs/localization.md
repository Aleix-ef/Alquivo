# Localización de Alquivo

Estado: 29 de septiembre de 2026. Español es el idioma público y de todos los usuarios de beta. El inglés es una **vista previa parcial exclusiva del administrador local**, como fiscalidad; no se anuncia como idioma disponible.

## Implementado

- Selector 🇪🇸 Español / 🇬🇧 English solo en la barra interior de una sesión administradora confirmada por el servidor. La landing, registro, acceso, recuperación, guías y páginas legales permanecen en español. El navegador y una preferencia guardada no pueden habilitar inglés sin dicha sesión; al cerrar sesión se vuelve al español.
- Diccionario centralizado con Vue I18n, cobertura pareada de claves y prueba automática. La landing comercial, preguntas frecuentes, precios beta, acceso, registro, doble factor, recuperación, aviso breve de privacidad y navegación están traducidos.
- El texto contractual vigente está en español. Las traducciones preliminares de navegación legal quedan en el código, sin exponerse públicamente; antes de habilitar inglés para clientes se requiere revisión jurídica.
- En la vista previa del administrador, un aviso informa de que algunas pantallas siguen en español. Las guías editoriales no se presentan como inglesas.

## Antes de ofrecer inglés como idioma completo

1. Traducir todas las vistas privadas y componentes reutilizados: dashboard, propiedades, alquileres, finanzas, documentos, fiscalidad, soporte, IA, ajustes y tutorial. Mantener terminología consistente y revisar desbordamientos en móvil.
2. Traducir y probar mensajes de validación y error del backend, respuestas de API, correos transaccionales y contenido del asistente. Enviar `Accept-Language` desde el cliente y decidir la preferencia de idioma a nivel de cuenta para correos y distintos dispositivos.
3. Traducir las guías editoriales. Para textos legales, obtener traducción y revisión jurídica antes de solicitar aceptación en inglés; hasta entonces, las condiciones en castellano son las vigentes.
4. Crear rutas públicas específicas para inglés (por ejemplo `/en`) y sus metadatos, canonical, `hreflang`, sitemap y contenido prerenderizado. El selector de administrador solo cambia la interfaz cliente; **no mejora el SEO inglés**.
5. Hacer QA con usuarios angloparlantes, fechas/moneda/formularios y accesibilidad. La fiscalidad actual es española: no dar a entender que sirve para declaraciones de otros países.

Comprobaciones locales: `cd frontend && npm test && npm run build`. La prueba `tests/i18n.test.js` exige las mismas claves en ambos idiomas. Para detectar texto duro restante, revisar `frontend/src/views` y `frontend/src/components`; no traducir nombres de marca ni datos introducidos por usuarios.
