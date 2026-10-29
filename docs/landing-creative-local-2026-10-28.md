# Landing — dirección visual y movimiento, solo local

28 de octubre de 2026. Continuación de `landing-implementation-local-2026-10-28.md`.

## Alcance

Primera pasada sobre la portada y el primer capítulo de producto, según la
dirección acordada: recuperar personalidad, profundidad y movimiento manteniendo
la claridad de la nueva landing. El resto de secciones conserva su estructura.

- Se trabaja sobre el Vue existente; no se crea otro sitio ni alojamiento.
- Disponible en `http://localhost:8080/` mediante Docker Desktop local.
- Solo se reconstruye/recrea `web`, sin dependencias. No se toca la base de datos.
- Sin commit, push, SSH, cambios de DNS ni despliegue público.
- Sin cambios en planes, IA real, autenticación, privacidad ni consentimientos.
- No se añaden proveedores, librerías de animación, cookies ni llamadas a modelos.

## Composición

- Titular simplificado «Tus alquileres, en orden.», con mayor escala y contraste
  tipográfico; se mantienen la marca, el petróleo oscuro y el acento verde.
- `LandingHeroScene.vue`: arquitectura vectorial original, dibujada en SVG y
  reproducible sin descargar imágenes, vídeo o una escena WebGL.
- Ensamblaje escalonado de las tres plantas, trazado de conexiones y entrada de
  las tarjetas. Una sola reproducción inicial; control para repetirla.
- Selector Centro / Mar / Garaje: destaca una planta y presenta los importes de
  esa propiedad de ejemplo. No representa una nueva función de unidades de
  alquiler ni lee una cartera real.
- La barra de cobros representa 1.050/1.400 EUR del ejemplo; no es una gráfica
  histórica inventada. Datos compartidos con `content/landingDemo.js`.
- Primer capítulo de producto con composición editorial, beneficios numerados
  y una demostración enmarcada con profundidad. Tras la revisión del usuario,
  el marco pasa de verde bosque a azul petróleo, coherente con las superficies
  de la app (`#121f2e`, `#0b1420`), conservando verde menta en acentos y estados.
  No se cambia la ilustración del edificio ni sus animaciones.
- Transición al alternar cobros y la respuesta ilustrativa de IA. No se simula
  una llamada a un modelo ni se modifica ningún registro.
- «Entrar» tiene un contorno más claro como acción de cabecera.

## Movimiento, rendimiento y accesibilidad

- SVG y CSS locales, sin librerías ni recursos externos nuevos.
- Las apariciones al entrar en pantalla usan IntersectionObserver, una vez por
  elemento. No hay escuchas continuas de scroll, bucles ni temporizadores.
- El observer y su escucha de preferencias se liberan al desmontar la vista.
- `prefers-reduced-motion` desactiva las animaciones y desplazamientos de las
  plantas; el estado seleccionado sigue indicado por color y texto.
- Sin JavaScript el contenido prerenderizado permanece visible y los enlaces
  de registro, inicio de sesión y secciones siguen siendo enlaces normales.
  Las ilustraciones interactivas requieren JavaScript.
- Selectores nativos con `aria-pressed`, región de datos `aria-live`, foco
  visible y manejo por teclado. Los controles no envían formularios.
- La cabecera ya no transparenta los textos de debajo.

## Correcciones encontradas en la revisión de navegador

- El router imponía 24 px de separación a todas las anclas, ignorando el margen
  CSS de la portada. La ruta `home` usa ahora 100 px; las demás conservan sus
  24 px y la restauración del scroll no cambia.
- La anchura mínima global de 320 px producía scroll horizontal en escritorio
  con ventana de 320 px y barra vertical. Se elimina ese mínimo **solo cuando
  está presente la landing** y se compacta su cabecera estrecha.

## Verificación

- Suite del frontend: 235 pruebas; incluye cinco nuevas de selector, importes,
  aislamiento de la ilustración, observer, limpieza y movimiento reducido.
- Compilación cliente y prerender de las 10 páginas públicas.
- Verificación SEO estática y por HTTP local: rutas, redirecciones, contenido,
  cabeceras, CSP, shell privado y frontera de sesión anónima.
- Revisión con Chrome headless local, perfil temporal sin cuentas, sobre
  `127.0.0.1:8080`, no sobre producción.
- Viewports de 1440, 1024, 768, 390 y 320 px; comprobación de desbordamientos y
  capturas de escritorio/móvil. No equivale a probar dispositivos iOS físicos.
- Interacciones reales: ancla bajo cabecera, selección de cada inmueble,
  selección con Espacio, alternancia cobros/IA, repetición de entrada y emulación
  de movimiento reducido. Captura adicional del HTML sin ejecutar JavaScript.
- Sin excepciones de JavaScript durante el recorrido; los ejemplos no realizan
  POST ni peticiones a endpoints del asistente. Solo configuración/catálogo
  públicos y recursos locales.

Las pautas de Sites se aplicaron a la composición, al uso de ejemplos explícitos
y al movimiento progresivo; se usó su flujo local, sin publicación.

## Próximo paso

Revisión del usuario en localhost para aprobar esta dirección antes de extender
su tratamiento visual al bloque de IA y al cierre. No se considera demostrada
una mejora de conversión, una auditoría WCAG completa ni el rendimiento en
dispositivos reales lentos.
