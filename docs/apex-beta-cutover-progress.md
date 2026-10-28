# Entrega del dominio principal — 28/10/2026

**Activa y comprobada:** `https://alquivo.com`, con portada de beta y registro
directo. Alias compatible `https://app.alquivo.com`, no indexable y sin redirección
de enlaces firmados. Sesiones/cookies host-only; los usuarios pueden necesitar
iniciar sesión una vez en el nuevo dominio.

Release inicial del cambio de dominio: `d4bcf4cde3bba7785cddfd538c1b71298d6c0816`, activado a las
05:54:09 UTC (06:54:09 en España peninsular), tras copia externa de 05:53:38 UTC.
El checkpoint privado `/opt/alquivo/shared/apex-beta-20261027` conserva la
reversión y el marcador `ACTIVATED`. No publicar su entorno ni sus archivos.

Detalle completo: [procedimiento y evidencia](apex-beta-cutover.md).

- DNS A al VPS sin proxy ni AAAA residual; MX de OVH conservados.
- HTTPS público y pruebas de portada, formulario, CSRF, cookies y APIs privadas
  aprobadas desde fuera; APIs sin sesión 401 y registro vacío con CSRF válido 422.
  Esto no constituye un alta real nueva ni una prueba de login con credenciales.
- Sin migraciones ni cambios de usuarios/documentos, sin envíos a clientes,
  sin llamadas reales a modelos ni alteración de flags de IA/pagos.
- Monitor del host instalado por separado para vigilar `alquivo.com`, con
  27 pruebas offline y control real sin incidencias tras la instalación.
- Copias, watchdog de copias, monitor y retención mantienen sus timers activos.
- Source del monitor desplegado SHA256:
  `d452bda881a482810a82d9798f2e3e210d7e7a16cb432cba6fd93de1c55a3a59`.

Rama de evidencia y ajuste operativo: `deploy/apex-beta-verified-20261028`,
derivada del release, sin incorporar trabajo pendiente del árbol principal.

**Pendiente de panel:** retirar la asociación personalizada en Pages una vez
confirmado por el titular. No se ha borrado Pages, D1 ni consentimientos, y
`alquivo-landing.pages.dev` puede seguir sirviendo el aviso anterior. Esto no
afecta a la nueva web del dominio principal.

## Ajuste posterior del registro — 28/10/2026, 06:11:33 UTC

Release actual: `602c9f924061c50810dd3cc235a20d224c882f72`.
«Tu patrimonio» es un placeholder traducido, no un valor preescrito; se conserva
el campo obligatorio y su máximo de 100 caracteres. No se renombran carteras.
222 pruebas de frontend, compilación, SEO y archivo legal aprobados.
Despliegue solo de `web`, con los otros seis contenedores, entorno, firewall y
datos intactos. Los assets públicos en ambos hosts confirman el estado inicial
vacío y el placeholder. Alertas y los cuatro temporizadores operativos activos.

Rama de la corrección y evidencia: `deploy/portfolio-placeholder-20261028`.
Checkpoint privado: `/opt/alquivo/shared/portfolio-placeholder-20261028`.
