# Alquivo

Producto SaaS Alquivo. Este directorio es independiente del proyecto académico anterior.

## Beta de validación sin cobros — 15 de septiembre de 2026

**Contratación desactivada por defecto** (`BILLING_ENABLED=false`). Las cuotas siguen visibles, pero ni la interfaz ni la API permiten abrir Checkout o el portal de Stripe. La prueba sigue durando 14 días y después se aplica Gratuito. No se cancelan automáticamente suscripciones o enlaces externos anteriores: revisar Stripe antes de abrir la beta. Las referencias a contratación más abajo describen el funcionamiento preparado para cuando se reactive, no el estado actual.

Se han incorporado cifrado autenticado de documentos/fotos con una clave independiente, doble factor opcional en Configuración para todos los planes, códigos de recuperación de un solo uso, copias locales cifradas con Restic y un ensayo de restauración aislado. La configuración de producción incluye PostgreSQL privado, proxy HTTPS, worker, scheduler y antivirus; todavía no se ha desplegado contra un dominio real.

**Manual operativo y pendientes:** [preparación de la beta](docs/release-candidate.md). Incluye claves, rotación, recuperación, pagos, despliegue y límites de estas protecciones. No se han contratado servicios ni realizado cargos. Correo real, copia externa, monitorización, documentación legal y revisión visual siguen pendientes antes de recibir usuarios reales.

Comandos locales:

```bash
php tools/check-secrets.php
bash tools/init-backup-secret.sh
docker compose cp backend:/app/storage/app/keys/documents.json secrets/documents.json
bash tools/backup.sh
bash tools/restore-check.sh
```

Conserva las claves en un gestor de contraseñas externo al equipo. La copia en `backups/repository` y las claves en `secrets/` no se suben a Git. El script de copia pausa brevemente la aplicación; el de restauración solo usa contenedores y volúmenes nuevos y desechables.

**Verificación local:** 143 pruebas de backend / 702 aserciones, 5 suites de frontend y compilación correctas. Auditorías Composer/npm sin avisos conocidos en la comprobación del 15 de septiembre. Análisis básico de patrones de secretos sin hallazgos en archivos publicables ni en el historial revisado. Acceso HTTP normal y con doble factor (cuenta temporal eliminada), rechazo de reutilización de código, bloqueo real de Checkout/portal y restauración de PostgreSQL en contenedores aislados correctos. El ensayo recuperó ambas claves mediante una prueba sintética; la base local no contenía documentos ni fotos. La conversión de archivos antiguos y la rotación se prueban con archivos sintéticos en la suite.

## Preparación de la beta — 14 de septiembre de 2026

La beta tendrá **registro abierto**, aunque los primeros usuarios lleguen por invitación personal. No hay listas de correos autorizados ni códigos de acceso. Contacto configurado: **soporte@alquivo.com** (`SUPPORT_EMAIL`). Configurarlo no crea el buzón ni demuestra que reciba correo: hay que verificar su entrega antes de publicar.

- **Acceso:** los correos de recuperación llevan a `/reset-password`. Los de verificación pasan por `/verify-email`, conservan la firma de Laravel y permiten iniciar sesión antes de confirmar; no dependen de que el lector de correo envíe un `Referer` de la app. Se comprueban enlaces caducados, manipulados y de otra cuenta.
- **Funciones pendientes de validar:** Fiscalidad queda oculta y su API deshabilitada con `FISCALITY_ENABLED=false`. El asistente queda oculto y no admite nuevas consultas con `ASSISTANT_VALIDATED=false`. No se borran sus datos ni su implementación. Desaparecen también sus promesas en los planes, la landing y las guías. Las guías normales siguen en Ayuda y soporte; allí se puede eliminar el historial antiguo de IA aunque esté desactivada.
- **Paso a Gratuito:** se mantiene editable el primer inmueble añadido (orden de ID); los excedentes quedan en consulta, con sus datos, documentos y exportaciones accesibles. Se bloquean modificaciones también por API y cambios de asociación que intenten saltarse el límite. Se permite eliminar archivos y archivar inmuebles vacíos para liberar espacio. No hay borrados automáticos de datos por bajar de plan.
- **Generación al superar el límite:** no se crean nuevas rentas ni movimientos recurrentes de los inmuebles en consulta. Las reglas conservan su próxima fecha; al recuperar el plan, los movimientos recurrentes pendientes se generan con la lógica de recuperación existente, sin duplicados. Las rentas conservan su lógica mensual, sin prometer una reconstrucción automática de todos los meses omitidos. Los estados vencidos de cargos históricos pueden seguir actualizándose.
- **Facturación:** se conserva el acceso al portal con pagos pendientes y se bloquea otra contratación si existe una suscripción local o en Stripe. Checkout usa un intento persistente por usuario, bloqueo compartido e idempotencia; los reintentos reutilizan parámetros y sesión. Se comprueba que el precio remoto sea activo, mensual, en euros y coincida con 6,99 €. Volver por una URL de éxito no se presenta como prueba de pago: los webhooks siguen siendo la fuente de confirmación.
- **Moneda:** los nuevos registros usan EUR. Se impide renombrar importes existentes como otra moneda; las carteras antiguas mantienen su valor almacenado. No se ha implementado conversión multidivisa.
- **Logs web:** el formato de acceso de Nginx omite consultas y referencias para no registrar tokens de recuperación o verificación. Aplicar la misma política al proxy y a los servicios externos de monitorización.

Para verificar el código: `cd backend && php artisan test`; desde `frontend`, `npm test` y `npm run build`. La nueva migración `2026_09_14_000000_create_billing_checkout_attempts` es aditiva; el arranque habitual la aplica sin reiniciar los datos. `node tools/check-session.mjs` comprueba la sesión sobre Docker y respeta las funciones deshabilitadas, sin consultar a IA ni cobrar.

Verificación del 15 de septiembre: **117 pruebas / 591 aserciones de backend**, las 4 suites de frontend y la compilación correctas. Migración aplicada en PostgreSQL local, Nginx validado y comprobación HTTP de login, restauración, datos y logout correcta. La configuración pública confirma IA y fiscalidad desactivadas y el correo de soporte elegido. Aplicación local en `http://localhost:8080`. Sin navegador conectado para revisión visual; sin pruebas reales de correo, IA o pagos en este cierre.

**Pendiente antes de cobrar/publicar:** configurar y verificar el precio mensual de Stripe y sus webhooks en el entorno elegido; probar contratación, renovación, impago y cancelación en sandbox; completar dominio/HTTPS, correo real, alojamiento, antivirus, copias con restauración, monitorización y textos legales. Las pruebas simuladas no sustituyen esas verificaciones. No se han creado productos/precios externos, enviado correos reales ni realizado cargos en este cierre.

## Interfaz Alquivo

### Formulario de soporte

En **Ayuda y soporte** se puede escribir una consulta con nombre, asunto, mensaje y adjuntos, usando como correo de respuesta el de la cuenta (también si todavía no está verificada). La landing, el acceso, el registro y las páginas legales muestran el botón flotante **¿Te ayudamos?** abajo a la derecha; el formulario público solicita además el correo del visitante. El texto se mantiene al cerrar/reabrir el panel y ante errores mientras se permanece en la página; no se guarda en almacenamiento persistente del navegador.

Los endpoints `POST /api/v1/support` y `POST /api/v1/public/support` admiten hasta 3 imágenes JPG/PNG/WebP o PDF, máximo 2 MB cada uno. Validan contenido MIME y tamaño, pasan los adjuntos por el scanner existente (obligatorio en producción) y comparten límites por IP y globales, más un campo trampa antispam. El mensaje HTML se escapa, el destinatario siempre procede de `SUPPORT_EMAIL` y el correo del visitante solo se usa como `Reply-To`. No se envía respuesta automática a direcciones introducidas por terceros.

Los adjuntos usan archivos temporales de la petición y se envían de forma síncrona al buzón; no se crean enlaces públicos ni documentos de cartera. No hay un gestor de tickets ni un historial de soporte en base de datos. El correo incluye una referencia para localizarlo en el buzón. Configura la retención, permisos y proveedor del buzón en la documentación de privacidad antes de publicar.

**Activación del envío:** configurar un mailer real (`MAIL_MAILER`, credenciales privadas y remitente autorizado) y comprobar entrega a `soporte@alquivo.com`. El mailer local `log` no envía ni registra el contenido de estas consultas; devuelve un aviso de envío no configurado y conserva el formulario. También se rechazan transportes de prueba y failover que puedan terminar escribiendo datos en logs. No se han enviado correos reales en las pruebas. SMTP tiene un timeout de 15 segundos; la interfaz no confirma éxito ante un error del proveedor. Si el navegador pierde la respuesta, podría haberse aceptado el correo antes del corte: comprobar el buzón antes de reintentar repetidamente.

Pruebas: `php artisan test --filter=SupportApiTest` verifica envíos simulados, cuenta/no autenticado, privacidad, adjuntos reales sin depender de GD, rechazo de MIME falso, tamaño, cantidad, scanner, errores y límites. `npm test` incluye validación de adjuntos del formulario. Sigue siendo necesaria la prueba de entrega con el proveedor real y una revisión visual en navegador.

Verificación de esta ampliación: 130 pruebas / 639 aserciones de backend y 5 suites de frontend correctas, compilación y acceso HTTP sobre Docker correctos. El entorno local sigue usando `MAIL_MAILER=log`: el formulario se puede revisar, pero no enviará consultas hasta configurar un proveedor real. El correo recibido identifica expresamente si procede de una cuenta con correo verificado, sin verificar o de un visitante.

Interfaz oscura fija, navegación móvil, dashboard con datos reales y propiedades con búsqueda, filtros, fotografías privadas y galería. Sin foto se utiliza una ilustración identificada como tal. Las fuentes y la imagen editorial del acceso se sirven desde la aplicación.

El [sistema visual](docs/design-system.md) documenta los estilos, componentes, accesibilidad, recursos gráficos y comprobaciones. Para revisar la app en Docker: `docker compose up -d --build` y abrir `http://localhost:8080`.

## Estado actual

- Laravel 13 con API versionada en `/api/v1`.
- Autenticación con Sanctum.
- Sesión web mediante cookie HttpOnly y protección CSRF; no se guardan tokens en el navegador.
- Cartera SaaS y aislamiento por cartera.
- Propiedades, contactos y arrendamientos activos con aislamiento por cartera.
- Cargos mensuales separados de los movimientos reales y soporte para pagos parciales.
- Finanzas con ingresos, gastos, beneficio y alquiler pendiente.
- Documentos privados con descarga autorizada y asociación a propiedades o contratos.
- Incidencias con prioridad, estado y costes opcionales.
- Calendario unificado de rentas, vencimientos y recordatorios.
- Reglas de ingresos y gastos recurrentes mensuales, trimestrales o anuales.
- Confirmación manual de movimientos previstos para no confundir previsión con dinero real.
- Cancelación segura de movimientos previstos y edición protegida de movimientos manuales.
- Edición y eliminación autorizada de documentos junto con su archivo privado.
- Mantenimiento de contactos sin permitir borrar el historial de un alquiler.
- Dashboard financiero agregado desde backend.
- Onboarding contextual de propiedad, valoración y primer alquiler.
- Agenda de personas e inquilinos reutilizable al crear contratos.
- Configuración del perfil, cartera y contraseña del propietario; moneda informativa sin conversión.
- Ficha completa de contratos con condiciones, mensualidades, documentos e inquilinos.
- Renovaciones enlazadas sin alterar el contrato ni los cobros históricos.
- Incidencias con responsable, seguimiento, costes y gasto financiero idempotente.
- Documentos filtrables por propiedad, categoría y vencimiento, integrados en calendario y avisos.
- Acceso desde la ficha a incidencias y documentos del inmueble seleccionado.
- Recordatorios completables junto a cobros, contratos y vencimientos documentales.
- Informes de flujo mensual y rendimiento por inmueble basados en movimientos confirmados.
- Exportaciones CSV seguras de propiedades, contratos y finanzas.
- Consentimiento legal versionado y base completa de verificación de correo.
- Prueba de producto de 14 días con todos los límites del Plan Fundador y paso automático al plan gratuito.
- Eliminación protegida de cuenta, cartera y archivos asociados.
- Catálogo de beta simplificado: Gratuito y Plan Fundador por 6,99 €/mes.
- Límites de inmuebles y almacenamiento aplicados siempre desde backend.
- Stripe Checkout y Customer Portal mediante Laravel Cashier.
- Suscripciones sincronizadas por webhooks firmados como fuente de verdad.
- Asistente conversacional de solo lectura con historial privado, aislamiento por cartera y cuotas mensuales.
- Consultas asistidas sobre patrimonio, inmuebles, finanzas, cobros, contratos, incidencias, documentos y recordatorios.

## Asistente de Alquivo

Implementado pero **oculto por defecto en la beta**. Solo habilitar `ASSISTANT_VALIDATED=true` después de validar respuestas reales, cuotas y tratamiento de datos; también requiere `ASSISTANT_ENABLED=true` y una clave configurada. El apartado siguiente describe su funcionamiento cuando está habilitado.

El botón **Asistente IA** de la barra superior abre un panel lateral ampliable, a pantalla completa en móvil. Incluye preguntas adaptadas a la sección, contexto del inmueble abierto, comparación de meses e inmuebles, desglose de movimientos, enlaces para contrastar respuestas y copia de texto. **Cómo usar Alquivo** ofrece guías con buscador sin gastar consultas ni necesitar saldo de IA. **Ayuda y soporte** ocupa la parte inferior izquierda del menú y tiene su propia página; configura `SUPPORT_EMAIL` con una dirección real para habilitar el contacto por correo.

El frontend nunca recibe la clave ni consulta directamente al proveedor: Laravel autoriza al usuario, limita la cartera y ejecuta ocho herramientas de lectura. Las respuestas del proveedor se solicitan con almacenamiento remoto desactivado; el historial que ve el usuario se conserva en la base de datos de Alquivo y puede eliminarse desde el panel.

Incluye la mascota de Alquivo con poses de bienvenida, consulta y respuesta. Las consultas ajenas a la aplicación, la falta de datos, las peticiones de escritura y los problemas que requieren soporte tienen respuestas diferenciadas. El [documento del asistente](docs/assistant.md) explica las protecciones, sus límites y las pruebas pendientes con saldo real; no basta con que pasen los tests simulados para dar por validada la calidad del modelo.

Para activar respuestas reales, añade al entorno privado del backend y reconstruye el servicio:

```dotenv
OPENAI_API_KEY=sk-...
OPENAI_ASSISTANT_MODEL=gpt-5.4-mini
```

El modelo es configurable sin cambios de código. Las cuotas conservadoras de beta están centralizadas en `backend/config/assistant.php`: 5 consultas/mes en Gratuito y 50 tanto en el Plan Fundador como durante la prueba. Existe además un presupuesto mensual de tokens independiente del historial, para que borrar conversaciones no recupere consumo ni un uso intensivo vuelva imprevisible el coste. Ambos límites deben ajustarse con datos reales. Antes de producción se debe reflejar el tratamiento de datos por IA en privacidad, condiciones y registro de proveedores; el asistente no envía archivos ni el contenido de documentos.

## Fiscalidad premium (beta)

Implementada pero **deshabilitada por defecto** con `FISCALITY_ENABLED=false`. No forma parte de las prestaciones anunciadas mientras no se revise y active explícitamente.

El apartado **Fiscalidad** (`/fiscality`) incluye fichas anuales por inmueble, cálculo explicado y dossier PDF/CSV general o individual, con historial privado de versiones. Está incluido en el Plan Fundador y durante la prueba. La primera entrega cubre supuestos residenciales ordinarios del ejercicio **2025** y señala los datos o casos pendientes; está en beta y requiere revisión fiscal profesional antes de su comercialización como cálculo validado.

La [documentación fiscal](docs/fiscalidad.md) detalla cobertura, arquitectura y limitaciones. La simulación personal de IRPF, 2026 y los casos especiales siguen pendientes. Los informes de finanzas siguen mostrando el flujo de caja, separado del criterio fiscal.

## Stripe en desarrollo

`STRIPE_PRICE_FOUNDER_MONTHLY` debe contener el identificador del precio mensual de 6,99 € creado en Stripe (`price_...`), no el importe numérico. Los precios antiguos no se reutilizan: las suscripciones de prueba locales anteriores deben cancelarse y recrearse. Durante la beta no hay cambios entre planes de pago: Checkout activa el Plan Fundador y el portal de Stripe permite actualizar el método de pago, consultar facturas o cancelar; al finalizar la suscripción se aplica Gratuito. Para recibir eventos en local, instala Stripe CLI y ejecuta:

```bash
stripe login
stripe listen --forward-to http://127.0.0.1:8100/stripe/webhook
```

Copia el valor `whsec_...` mostrado por Stripe CLI en `STRIPE_WEBHOOK_SECRET` y reinicia el backend. La prueba comienza al registrar la cuenta, no al introducir una tarjeta. Durante 14 días se aplican siempre los límites del Plan Fundador; después se aplica el plan gratuito si no existe una suscripción activa. El checkout conserva los días de prueba restantes y vuelve a `/plans`. En la beta solo se ofrece facturación mensual.

## Cierre funcional de beta

Las listas de producto recorren todas las páginas de la API para que contratos, contactos, documentos, incidencias y movimientos antiguos no desaparezcan. Los cobros admiten importes parciales, corrección y eliminación atómica; un movimiento vinculado no puede editarse por la ruta genérica y descuadrar la mensualidad. Las propiedades vacías pueden archivarse, pero se conserva cualquier inmueble con historial. También se puede elegir y eliminar la fotografía de portada, editar movimientos manuales y gestionar recordatorios. Las acciones destructivas usan diálogos propios de Alquivo y las pantallas principales muestran estados de carga, error y reintento.
- Vue 3, Pinia, Vue Router y Axios.
- Registro, login, shell de producto, dashboard, propiedades, alquileres, finanzas, incidencias, calendario y documentos.
- Identidad visual propia sin Bootstrap.
- Landing pública orientada al registro: propuesta de valor, producto, planes, preguntas frecuentes y acceso directo a la prueba.

## Desarrollo

### Arranque rápido con Docker

Abre Docker Desktop y ejecuta `./levantar-alquivo.sh` desde la carpeta superior (`Inmogest`). El script espera a que los servicios estén listos y muestra la dirección local. También funciona invocándolo por su ruta desde otra carpeta.

Después de cambiar el código, utiliza `./levantar-alquivo.sh --actualizar` para reconstruir las imágenes. Los datos existentes se conservan y el backend aplica las migraciones pendientes al arrancar.

Backend:

```bash
cd backend
php artisan migrate
php artisan serve --port=8100
```

Frontend:

```bash
cd frontend
npm install
npm run dev
```

En otro proceso debe ejecutarse el planificador que genera los cargos mensuales y actualiza atrasos:

```bash
cd backend
php artisan schedule:work
```

En desarrollo el frontend consulta `http://127.0.0.1:8100/api/v1`. Puede cambiarse con `VITE_API_URL`.

Para cargar una cartera de demostración en local:

```bash
cd backend
php artisan db:seed
```

Acceso demo: `demo@alquivo.test` / `demo12345`.

## Verificación

Con Docker levantado y la cuenta demo local, ejecutar `node tools/check-session.mjs` desde la raíz comprueba el recorrido de sesión por HTTP. Las peticiones GET utilizan `Referer` sin `Origin`, igual que las del navegador. Es importante mantener `Referrer-Policy: same-origin` en Nginx y Laravel: `no-referrer` impide a Sanctum reconocer estas peticiones de la SPA y provoca una vuelta al login después de aceptar la contraseña. La prueba no consume IA ni modifica la cartera.

```bash
cd backend && php artisan test
cd frontend && npm run build
```

## Contenedores

La topología incluida utiliza PostgreSQL 17, PHP-FPM, un worker, un scheduler y Nginx sirviendo Vue y la API bajo el mismo origen.

```bash
cp .env.docker.example .env.docker
docker run --rm php:8.3-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Copia la clave generada en `APP_KEY` dentro de `.env.docker`, cambia la contraseña de PostgreSQL y arranca:

```bash
APP_ENV_FILE=.env.docker docker compose --env-file .env.docker up --build -d
docker compose ps
```

La aplicación quedará disponible en `http://localhost:8080`, accesible únicamente desde el equipo local. Para un despliegue público utiliza la plantilla independiente `docker-compose.production.yml` y sigue [seguridad y lanzamiento](docs/security-and-launch.md); no reutilices la configuración local.

### Compatibilidad del cambio de marca

Los textos, recursos activos, remitente y cuenta demo usan **Alquivo**. La caché del navegador pasa a `alquivo_user` / `alquivo_portfolio` y migra automáticamente las claves antiguas. El cambio de nombre de la cookie puede requerir iniciar sesión una vez de nuevo.

Las instalaciones nuevas usan `alquivo` como base y usuario de PostgreSQL. **En este entorno ya existente se conservan la base, el usuario y los volúmenes anteriores**: el archivo privado `.env` de la raíz fija la conexión compatible. No lo borres ni cambies esos valores sin migrar PostgreSQL; usa `docker compose up -d --build` para actualizar este entorno, sin sustituirlo por la receta de instalación nueva. La cuenta demo local se ha renombrado sin volver a sembrar ni modificar su cartera o suscripción.

Los nombres del directorio de trabajo, proyecto/volúmenes de Docker, historial/ramas/remoto de Git y las imágenes históricas de referencia se conservan intencionadamente. No son marca visible de la aplicación. El repositorio remoto y la marca alojada en Stripe requieren una revisión separada; este cambio no modifica cuentas externas ni productos/precios de Stripe. Configura un remitente real al preparar producción y comprueba que el buzón de soporte elegido, soporte@alquivo.com, puede enviar y recibir correo.

El despliegue público requiere:

- `APP_URL` y `FRONTEND_URLS` con el dominio HTTPS real.
- `SANCTUM_STATEFUL_DOMAINS` sin protocolo.
- `SESSION_SECURE_COOKIE=true`.
- credenciales SMTP reales.
- copias de seguridad de `postgres_data` y `private_storage`.
- terminación TLS delante del contenedor `web`.

El contenedor `backend` aplica migraciones antes de iniciar PHP-FPM. `scheduler` ejecuta la generación de cargos y movimientos recurrentes; `worker` queda preparado para correo y tareas asíncronas.

## Criterios arquitectónicos

### Seguridad y salida a producción

Se han reforzado autenticación, verificación de correo, firmas de Stripe, límites de IA y almacenamiento, privacidad del chatbot, borrado de archivos, cabeceras web y dependencias. Las migraciones nuevas conservan el cupo mensual de IA separado del historial. Cada usuario debe activar el asistente tras leer el aviso de privacidad.

`php artisan security:check` valida la configuración técnica y debe fallar en el entorno local. En producción la aplicación bloquea solicitudes si faltan protecciones esenciales. El detalle de controles, pruebas, operación, requisitos legales y tareas todavía pendientes está en [docs/security-and-launch.md](docs/security-and-launch.md). Los textos legales siguen siendo provisionales: no presentar el producto como plenamente conforme hasta completar los datos del titular, proveedores, contratos y pruebas del despliegue.

- Monolito modular: un despliegue sencillo con dominios separados.
- Todas las entidades de negocio pertenecen a una cartera.
- El backend es la fuente de verdad de métricas y reglas financieras.
- Los cargos mensuales se generan mediante un comando idempotente programado diariamente.
- Los movimientos recurrentes se generan como pendientes: el propietario confirma cuándo se han pagado o cobrado.
- Las funciones futuras no se exponen hasta aportar valor al MVP; la IA comienza como una capa de consulta estrictamente de lectura.

## Mantenibilidad y deuda técnica conocida

La base actual es adecuada para continuar el MVP y puede ser entendida por un desarrollador con experiencia en Laravel y Vue sin necesidad de rehacerla. Antes de ampliar el equipo o acelerar el desarrollo conviene consolidar estos puntos, por orden de impacto:

1. **Centralizar planes y derechos de uso.** Crear un servicio único de `Entitlements` que determine prueba activa, plan contratado, límites efectivos, renovación, cancelación y pagos fallidos. Stripe debe seguir siendo la fuente de verdad del cobro, pero el resto de la aplicación no debería interpretar directamente sus estados.
2. **Centralizar el acceso a la cartera.** Sustituir progresivamente las comprobaciones manuales de `portfolio_id` por Policies, route model binding limitado y un contexto de cartera autenticada. Así un módulo nuevo no podrá olvidar accidentalmente el aislamiento entre clientes.
3. **Extraer lógica de los controladores.** Mover validaciones repetidas a `FormRequest`, respuestas a API Resources y reglas comerciales complejas a servicios o acciones de dominio.
4. **Dividir las vistas grandes de Vue.** Extraer componentes reutilizables para modales, formularios, estados vacíos, errores y carga; utilizar composables y stores por dominio cuando exista estado compartido. Las vistas deben coordinar la pantalla, no contener toda su lógica.
5. **Reforzar facturación.** Cubrir con pruebas de integración Checkout, portal, webhooks, fin de prueba, pagos fallidos, periodos de gracia y cancelaciones. Evitar estados contradictorios entre Cashier, Stripe y `Portfolio`.
6. **Automatizar calidad.** Añadir PHPStan/Larastan, ESLint e integración continua para ejecutar pruebas, formato, análisis estático y build en cada cambio.
7. **Documentar la incorporación de desarrolladores.** Mantener un mapa de arquitectura, glosario de negocio, ciclo de vida de suscripción, guía para crear módulos, variables de entorno y contrato OpenAPI de la API.

Esta deuda no impide probar el producto con usuarios. Sí debe resolverse antes de crecer rápidamente con varios desarrolladores o una base relevante de clientes de pago.

## Estrategia de salida al mercado

El MVP no necesita cubrir toda la gestión inmobiliaria. Su promesa inicial es que un pequeño propietario pueda saber qué tiene, cuánto gana, qué está pendiente y qué requiere atención sin mantener varias hojas de cálculo.

### Beta abierta con incorporación acompañada

Antes de invitar clientes reales deben quedar resueltos estos mínimos:

- Recorrido fiable de registro, prueba, contratación y cancelación.
- Correo transaccional real para verificación, recuperación y avisos esenciales.
- Almacenamiento persistente de documentos preparado para producción.
- Copias de seguridad verificadas de base de datos y archivos.
- Dominio, HTTPS, configuración segura de producción y secretos fuera del repositorio.
- Monitorización de errores, logs consultables y una forma visible de contactar con soporte.
- Textos legales y política de privacidad revisados para el mercado donde se venda.
- Analítica básica de producto: registro, primera propiedad, primer contrato, primer cobro y conversión a pago.
- Pruebas end-to-end de los recorridos críticos y webhooks de Stripe.

Con esos puntos se puede empezar invitando personalmente a unos 5-15 propietarios y acompañando su alta, manteniendo el registro abierto a otras personas. No se aplican listas privadas de acceso. La contratación y sus condiciones deben estar verificadas antes de cobrar.

### Funcionalidad a validar antes de construir

Los primeros usuarios deben decidir qué se desarrolla después. Las hipótesis con más valor para investigar son:

- Importación sencilla desde Excel/CSV para reducir la fricción de abandonar las hojas de cálculo.
- Avisos por correo de rentas pendientes, contratos próximos a vencer, documentos e incidencias.
- Mejor gestión de fianzas, revisiones de renta y periodos sin inquilino.
- Resumen fiscal orientativo y exportaciones para entregar a una gestoría.
- Gestión básica de préstamos e hipotecas para calcular patrimonio neto y flujo real.
- Onboarding guiado que lleve al usuario hasta su primer inmueble y contrato en pocos minutos.

No deben incorporarse al MVP sin validación integraciones bancarias, marketplace, chat entre personas, seguros, automatizaciones avanzadas o gestión para grandes inmobiliarias. El asistente de lectura debe medirse como experimento de beta antes de ampliar sus capacidades.

### Señales para ampliar el producto

- Los usuarios completan la carga inicial y vuelven semanal o mensualmente.
- Consultan el dashboard para tomar decisiones, no solo para almacenar datos.
- Registran cobros y gastos de manera recurrente.
- Al menos algunos usuarios pagan sin necesitar descuentos permanentes.
- Varias personas solicitan espontáneamente la misma mejora.

Las peticiones aisladas se registran; las repetidas que refuercen la promesa principal se priorizan. El objetivo de la beta es aprender qué hace imprescindible Alquivo, no cerrar anticipadamente una lista infinita de funciones.
