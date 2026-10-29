# Landing creativa — entrega del 29/10/2026

Entrega autorizada por el titular tras revisar la portada en local.

- Rama: `deploy/landing-creative-20261029`.
- Base: `2c9588f`, que conserva el código activo `602c9f9`.
- Portada con ilustración arquitectónica animada, demostración sintética de
  cobros/IA, marco azul petróleo, CTA a registro y enlaces legales compactos.
- Preservados `/privacidad`, `/aviso-legal`, guías, cookies y dominio antiguo.
- No incluye el prototipo de cartera animada dentro del resumen ni los cambios
  locales de IA, revisión de conversaciones u otros módulos.
- Backend, Compose, entorno, base de datos y Caddy no cambian.

## Dependencias

Se actualiza exclusivamente Vue I18n y sus dependencias internas de 11.4.12 a
11.4.14, por el aviso [del mantenedor GHSA-83rm-6c8r-g728](https://github.com/intlify/vue-i18n/security/advisories/GHSA-83rm-6c8r-g728).
No es una afirmación de explotación en Alquivo. Auditoría npm después del parche:
cero avisos en la entrega. Pruebas de traducción y suite frontend incluidas.

## Comprobaciones previas

- 231 pruebas de frontend aprobadas en la entrega aislada.
- Compilación pública con canonical `https://alquivo.com` e indexación activa.
- 11 páginas prerenderizadas y revisión SEO aprobada, incluidos los avisos
  históricos que el árbol principal local no incorpora.
- Comprobación de formato, diff y secretos antes de empaquetar.
- El despliegue reconstruye solo web y conserva imagen anterior para reversión.

## Publicación efectiva

- Fuente activa: `f7160cd232a6a9aa987700e732b03fd8438966c0`, en
  `https://alquivo.com`; el alias `app.alquivo.com` se conserva.
- Copia externa previa finalizada a las 17:37:18 UTC del 29/10/2026.
- Se reconstruyó y recreó únicamente web. Los seis contenedores restantes,
  entorno y firewall conservan sus identificadores/huellas por comprobación.
- Sin migraciones, escrituras de datos de usuario, envíos a clientes o llamadas
  a modelos. El prototipo del resumen está ausente del paquete y el dashboard
  coincide byte a byte con el anterior antes de activar.
- Reversión: imagen anterior `alquivo-landing-rollback:f7160cd232a6`, release
  `602c9f9` y checkpoint privado en
  `/opt/alquivo/shared/landing-creative-20261029`.
- Ambos hosts: portada, registro, acceso y políticas actual/histórica 200;
  APIs de usuario e inmuebles sin sesión 401. Nginx y monitor correctos.
- Verificación externa de SEO, recursos, estados, CSP, rutas privadas y sesión
  anónima aprobada. Chrome sin sesión: construcción, selector, ancla, marco azul,
  demostración y anchura móvil correctos, sin excepciones JavaScript.
- Copias y monitorización reanudadas; el monitor no observó incidencias.

El commit posterior a esta fuente solo registra esta evidencia documental.
