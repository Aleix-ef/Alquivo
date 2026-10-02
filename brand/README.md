# Alquivo: uso de la marca

El nombre completo es el logo principal. El monograma **AL** se reserva para favicon, accesos pequeños y lugares donde no cabe el nombre.

## Archivos actuales

- `alquivo-logo-redes.png`: nombre completo blanco sobre azul petróleo; 1080 × 1080, listo para el perfil de redes y con margen para recorte circular.
- `alquivo-logo-redes.svg`: la misma composición en vector.
- `alquivo-nombre.svg`: nombre azul petróleo sobre fondo transparente; para fondos claros.
- `alquivo-nombre-claro.svg`: nombre blanco sobre fondo transparente; para fondos oscuros.
- `alquivo-icono-al.svg`: icono AL con los acentos salvia y dorado de la referencia.
- `alquivo-logo-correo.png`: referencia original conservada, sin modificar.
- `alquivo-avatar-redes.png`: antiguo avatar AL, conservado como referencia. Para el nuevo perfil utiliza `alquivo-logo-redes.png`.

El nombre se ha adaptado a trazados vectoriales siguiendo el logo de correo (letras geométricas, A sin travesaño), sin depender de fuentes ni generar una marca nueva con IA.

## Aplicación

`BrandLogo.vue` muestra el nombre por defecto y el icono con `compact`. Hereda el color del contexto para mantener el contraste. La landing, las guías, el acceso, la recuperación, el área privada y las páginas legales comparten ese componente.

Las fuentes editables están en `frontend/public/brand/alquivo-wordmark.svg` y `frontend/public/favicon.svg`. Los PNG del navegador están en `frontend/public/favicon-32.png` y `frontend/public/apple-touch-icon.png`.

## Regenerar las exportaciones

Desde la raíz del repositorio:

```sh
node tools/export-brand.mjs
```

Requiere Google Chrome (o `CHROME_BIN` con su ruta). Renderiza los SVG locales en un perfil temporal aislado, sin abrir cuentas, llamar a servicios de IA ni modificar los originales. No publiques capturas de la aplicación con datos de clientes como recursos de marca.

Con `node tools/export-brand.mjs --preview` genera además `/tmp/alquivo-brand-preview.png`, una prueba visual con los estilos reales de la aplicación y datos exclusivamente de marca: fondos claro y oscuro, menú lateral e icono compacto. No inicia sesión ni consulta carteras.
