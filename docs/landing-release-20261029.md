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

La publicación efectiva y sus comprobaciones se anotarán al finalizarla.
