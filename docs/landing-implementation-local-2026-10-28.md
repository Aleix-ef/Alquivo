# Landing y activación — implementación local

Fecha: 28 de octubre de 2026.

Se aplican las mejoras inmediatas del informe
`estrategia-producto-landing-beta-2026-10-28.md`. No se considera ejecutado todo
el plan comercial ni validada una mejora de conversión.

## Alcance y entorno

- Solo árbol de trabajo y Docker Desktop local (`desktop-linux`, socket Unix).
- Vista disponible en `http://localhost:8080/`.
- Se reconstruyó y recreó únicamente `web`, con `--no-deps`; no se reiniciaron
  backend, PostgreSQL, worker ni scheduler.
- No hubo commit, push, SSH, cambio de DNS ni despliegue en producción.
- No se modificaron cuotas, pagos, autenticación, consentimientos, capacidades
  del asistente ni tratamientos legales. No se consultaron modelos de IA.
- Se preservaron los cambios ajenos que ya existían en el árbol de trabajo.

## Cambios aplicados

### Portada de la beta

- Nueva vista `BetaLandingView.vue`, independiente del antiguo diseño utilizado
  por la lista de espera. No se retiró esa implementación histórica.
- Público objetivo visible desde el primer bloque: quienes gestionan sus propios
  alquileres. Se conserva «Tus alquileres, en orden. Tus números, claros.».
- Mensaje centrado en cobrado, pendiente, gastos y documentación; tres beneficios
  concretos en lugar de una lista extensa de módulos.
- CTA coherente «Probar gratis» hacia `/register`, acceso «Entrar» visible y
  enlace «Ver cómo funciona» a la demostración.
- Una única oferta de beta, sin planes de pago ocultos. Los límites visibles
  parten de los actuales 50 inmuebles/5 GB y se actualizan con el catálogo público;
  el texto de consultas de IA se obtiene del plan cuando está disponible.
  Estos valores informativos no conceden permisos ni modifican las cuotas.
- FAQ nativa, desplegable y orientada a dudas de uso, tarjeta, documentos, IA y
  ayuda. Dos enlaces legales en el pie, con información ampliada accesible.
- Guías existentes conservadas y agrupadas en un desplegable de recursos.

### Demostración sintética

- `LandingDemo.vue` permite alternar cobros y una respuesta ilustrativa de IA.
- Historia coherente: 1.400 EUR esperados, 1.050 EUR recibidos, 350 EUR pendientes
  y 130 EUR de gastos pagados. El saldo pendiente contiene 250 EUR de un alquiler
  parcial y 100 EUR de otro aún sin pagar.
- Cálculos del ejemplo en céntimos enteros, derivados de la misma fixture.
- Etiquetado explícito de ejemplo: no son capturas de una cartera real ni una
  respuesta obtenida de un modelo. No hace peticiones, no guarda datos y no
  permite escrituras.

### Diseño y disponibilidad

- Estilo oscuro, wordmark existente, composición responsive, textos principales
  de 17–18 px, controles de tamaño cómodo, foco visible y movimiento reducido.
- Cabecera compacta en móvil y listas/cifras legibles sin reducir un dashboard
  completo a una miniatura.
- Estados de disponibilidad pública de IA separados: desconocido, activo e
  inactivo. SSR, carga o fallo de API ya no se interpretan como «en preparación».
  No se activó ningún flag de IA por marketing.
- CSS de portada incluido en la carga inicial para conservar el diseño sin
  JavaScript. Los ejemplos financieros y los enlaces se prerenderizan.

### SEO y compartición

- Descripción pública actualizada, sin la afirmación antigua de IA en preparación.
- Imagen Open Graph/Twitter reutilizando, sin editar, el PNG de marca existente.
- Canonical, allowlist de indexación, guías, sitemap y páginas privadas
  `noindex` conservados. Ninguna URL privada o con token recibe imagen/canonical.
- No se han añadido analytics, cookies, grabaciones ni proveedores nuevos.

### Primer recorrido interior

- El siguiente paso del resumen prioriza configurar el primer alquiler antes
  de completar una valoración.
- Acción directa al apartado de alquiler del inmueble.
- Alternativa visible «No lo alquilo aún: ver su ficha».
- Valoración explícitamente opcional después de existir un alquiler.
- Se reconocen contratos de renta cero y las métricas globales de ocupación/renta
  para no deducir que no hay alquileres solo por el recorte de seis inmuebles.
- Ninguna escritura automática ni nuevo onboarding obligatorio.

## Verificación realizada

- `npm test`: **230 pruebas, 230 PASS**, incluidas las de la lista de espera.
  El primer intento aislado no pudo abrir el puerto de la prueba HTTP (`EPERM`);
  la repetición con acceso a sockets locales pasó completa.
- Nuevas regresiones de cifras del ejemplo, interacción real del componente Vue,
  estados de disponibilidad, siguiente paso y metadatos de compartición.
- `npm run build` y `npm run test:seo`: **10 páginas públicas/estáticas** verificadas.
- También se compiló con origen HTTPS e indexación pública simulados para validar
  canonical, imagen social y sitemap. Después se restauró la compilación local
  con indexación desactivada; no se editaron archivos `.env`.
- `SEO_BASE_URL=http://127.0.0.1:8080 npm run test:seo`: HTTP, recursos, estilos
  iniciales, estados, redirecciones, cabeceras, rutas privadas y frontera de
  sesión anónima correctos en Docker local.
- Archivo legal `2026-10-27-r1` verificado sin modificar su contenido.
- No había navegador conectado: no se presenta esto como validación visual,
  auditoría WCAG, prueba con lector de pantalla ni recorrido de alta completo.

## Pendiente: no ocultarlo como trabajo terminado

- Revisión visual con móvil/escritorio, zoom y teclado, y observación de primeros
  propietarios. No hay datos de conversión antes/después.
- Capturas o vídeo de una tarea real con cartera sintética. El ejemplo interactivo
  actual es una ilustración, no sustituye esa evidencia de producto.
- Medición de rendimiento real y, según resultados, carga diferida de recursos.
- Definir privacidad, retención y eventos antes de instrumentar el embudo.
- Revisar disponibilidad de datos/sugerencias del asistente por separado, sin
  ampliar acciones ni alterar confirmaciones dentro de este cambio de landing.
- Revisión del tratamiento de valoraciones incompletas en métricas globales;
  este cambio solo reordena el siguiente paso y no recalcula el patrimonio.
- Alias `www` y cualquier publicación: requieren intervención posterior en
  infraestructura/producción y no se han realizado.
