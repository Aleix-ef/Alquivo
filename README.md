# Nareo

Producto SaaS Nareo. Este directorio es independiente del proyecto académico anterior.

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
- Configuración del perfil, cartera, moneda y contraseña del propietario.
- Ficha completa de contratos con condiciones, mensualidades, documentos e inquilinos.
- Renovaciones enlazadas sin alterar el contrato ni los cobros históricos.
- Incidencias con responsable, seguimiento, costes y gasto financiero idempotente.
- Documentos filtrables por categoría y vencimiento, integrados en calendario y avisos.
- Recordatorios completables junto a cobros, contratos y vencimientos documentales.
- Informes de flujo mensual y rendimiento por inmueble basados en movimientos confirmados.
- Exportaciones CSV seguras de propiedades, contratos y finanzas.
- Consentimiento legal versionado y base completa de verificación de correo.
- Prueba de producto de 14 días con todos los límites de Inversor y paso automático al plan gratuito.
- Eliminación protegida de cuenta, cartera y archivos asociados.
- Catálogo desacoplado de planes Propietario e Inversor, listo para conectar pagos.
- Límites de inmuebles y almacenamiento aplicados siempre desde backend.
- Stripe Checkout y Customer Portal mediante Laravel Cashier.
- Suscripciones sincronizadas por webhooks firmados como fuente de verdad.

## Stripe en desarrollo

Las variables `STRIPE_PRICE_*` deben contener identificadores de precios de Stripe (`price_...`), no importes numéricos. Para recibir eventos en local, instala Stripe CLI y ejecuta:

```bash
stripe login
stripe listen --forward-to http://127.0.0.1:8100/stripe/webhook
```

Copia el valor `whsec_...` mostrado por Stripe CLI en `STRIPE_WEBHOOK_SECRET` y reinicia el backend. La prueba comienza al registrar la cuenta, no al introducir una tarjeta. Durante 14 días se aplican siempre los límites de Inversor; después se aplica el plan gratuito si no existe una suscripción activa. El checkout conserva los días de prueba restantes y vuelve a `/plans`.
- Vue 3, Pinia, Vue Router y Axios.
- Registro, login, shell de producto, dashboard, propiedades, alquileres, finanzas, incidencias, calendario y documentos.
- Identidad visual propia sin Bootstrap.

## Desarrollo

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

Acceso demo: `demo@inmogest.test` / `demo12345`.

## Verificación

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
APP_ENV_FILE=.env.docker docker compose up --build -d
docker compose ps
```

La aplicación quedará disponible en `http://localhost:8080`. En un despliegue público deben configurarse además:

- `APP_URL` y `FRONTEND_URLS` con el dominio HTTPS real.
- `SANCTUM_STATEFUL_DOMAINS` sin protocolo.
- `SESSION_SECURE_COOKIE=true`.
- credenciales SMTP reales.
- copias de seguridad de `postgres_data` y `private_storage`.
- terminación TLS delante del contenedor `web`.

El contenedor `backend` aplica migraciones antes de iniciar PHP-FPM. `scheduler` ejecuta la generación de cargos y movimientos recurrentes; `worker` queda preparado para correo y tareas asíncronas.

## Criterios arquitectónicos

- Monolito modular: un despliegue sencillo con dominios separados.
- Todas las entidades de negocio pertenecen a una cartera.
- El backend es la fuente de verdad de métricas y reglas financieras.
- Los cargos mensuales se generan mediante un comando idempotente programado diariamente.
- Los movimientos recurrentes se generan como pendientes: el propietario confirma cuándo se han pagado o cobrado.
- Las funciones futuras no se exponen hasta aportar valor al MVP.

## Mantenibilidad y deuda técnica conocida

La base actual es adecuada para continuar el MVP y puede ser entendida por un desarrollador con experiencia en Laravel y Vue sin necesidad de rehacerla. Antes de ampliar el equipo o acelerar el desarrollo conviene consolidar estos puntos, por orden de impacto:

1. **Centralizar planes y derechos de uso.** Crear un servicio único de `Entitlements` que determine prueba activa, plan contratado, límites efectivos, renovación, cancelación y pagos fallidos. Stripe debe seguir siendo la fuente de verdad del cobro, pero el resto de la aplicación no debería interpretar directamente sus estados.
2. **Centralizar el acceso a la cartera.** Sustituir progresivamente las comprobaciones manuales de `portfolio_id` por Policies, route model binding limitado y un contexto de cartera autenticada. Así un módulo nuevo no podrá olvidar accidentalmente el aislamiento entre clientes.
3. **Extraer lógica de los controladores.** Mover validaciones repetidas a `FormRequest`, respuestas a API Resources y reglas comerciales complejas a servicios o acciones de dominio.
4. **Dividir las vistas grandes de Vue.** Extraer componentes reutilizables para modales, formularios, estados vacíos, errores y carga; utilizar composables y stores por dominio cuando exista estado compartido. Las vistas deben coordinar la pantalla, no contener toda su lógica.
5. **Reforzar facturación.** Cubrir con pruebas los endpoints, webhooks, fin de prueba, pagos fallidos, periodos de gracia, cambios programados y cancelaciones. Evitar estados contradictorios entre Cashier, Stripe y `Portfolio`.
6. **Automatizar calidad.** Añadir PHPStan/Larastan, ESLint e integración continua para ejecutar pruebas, formato, análisis estático y build en cada cambio.
7. **Documentar la incorporación de desarrolladores.** Mantener un mapa de arquitectura, glosario de negocio, ciclo de vida de suscripción, guía para crear módulos, variables de entorno y contrato OpenAPI de la API.

Esta deuda no impide probar el producto con usuarios. Sí debe resolverse antes de crecer rápidamente con varios desarrolladores o una base relevante de clientes de pago.

## Estrategia de salida al mercado

El MVP no necesita cubrir toda la gestión inmobiliaria. Su promesa inicial es que un pequeño propietario pueda saber qué tiene, cuánto gana, qué está pendiente y qué requiere atención sin mantener varias hojas de cálculo.

### Beta privada cobrable

Antes de invitar clientes reales deben quedar resueltos estos mínimos:

- Recorrido fiable de registro, prueba, contratación, cambio y cancelación.
- Correo transaccional real para verificación, recuperación y avisos esenciales.
- Almacenamiento persistente de documentos preparado para producción.
- Copias de seguridad verificadas de base de datos y archivos.
- Dominio, HTTPS, configuración segura de producción y secretos fuera del repositorio.
- Monitorización de errores, logs consultables y una forma visible de contactar con soporte.
- Textos legales y política de privacidad revisados para el mercado donde se venda.
- Analítica básica de producto: registro, primera propiedad, primer contrato, primer cobro y conversión a pago.
- Pruebas end-to-end de los recorridos críticos y webhooks de Stripe.

Con esos puntos se puede lanzar una beta privada para aproximadamente 5-15 propietarios, acompañando personalmente su alta y cobrando desde el principio o después de una prueba pactada.

### Funcionalidad a validar antes de construir

Los primeros usuarios deben decidir qué se desarrolla después. Las hipótesis con más valor para investigar son:

- Importación sencilla desde Excel/CSV para reducir la fricción de abandonar las hojas de cálculo.
- Avisos por correo de rentas pendientes, contratos próximos a vencer, documentos e incidencias.
- Mejor gestión de fianzas, revisiones de renta y periodos sin inquilino.
- Resumen fiscal orientativo y exportaciones para entregar a una gestoría.
- Gestión básica de préstamos e hipotecas para calcular patrimonio neto y flujo real.
- Onboarding guiado que lleve al usuario hasta su primer inmueble y contrato en pocos minutos.

No deben incorporarse al MVP sin validación integraciones bancarias, IA, marketplace, chat, seguros, automatizaciones avanzadas o gestión para grandes inmobiliarias.

### Señales para ampliar el producto

- Los usuarios completan la carga inicial y vuelven semanal o mensualmente.
- Consultan el dashboard para tomar decisiones, no solo para almacenar datos.
- Registran cobros y gastos de manera recurrente.
- Al menos algunos usuarios pagan sin necesitar descuentos permanentes.
- Varias personas solicitan espontáneamente la misma mejora.

Las peticiones aisladas se registran; las repetidas que refuercen la promesa principal se priorizan. El objetivo de la beta es aprender qué hace imprescindible Nareo, no cerrar anticipadamente una lista infinita de funciones.
