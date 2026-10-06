# Alquivo AI: latencia y mejoras conservadoras

Actualizado: 5 de octubre de 2026. Este bloque no incorpora capacidades nuevas ni autoriza la apertura de la beta.

## Comparación posterior autorizada: reducir rondas, no comprobaciones

Se hicieron 103 ejecuciones exclusivamente sintéticas a través del HTTP/orquestador de Laravel, bajo una envolvente común de 0,20 USD. Se detuvo en **0,196722125 USD reservados/conocidos conservadores**; las reservas de errores técnicos no se liberaron. 222 intentos de proveedor, tokens conocidos 914.426/16.819. No es el coste exacto facturado.

Se descarta el ajuste `low` de Luna: ahorró generación, pero perdió los tres cobros parciales de su muestra. Se mantiene el razonamiento original. La mejora aplicada permite que Laravel resuelva la referencia humana de inmueble y ejecute el resumen financiero o consulta de mensualidades en una misma tool, con los resolvedores/cálculos actuales. No se quitan autorización, unicidad, límites ni confirmación. Cambian solo los schemas de dos lecturas; catálogo y operaciones iguales. No se añade memoria ni se transmiten datos reales de usuarios.

Comparación emparejada de seis casos con tres repeticiones por variante:

| Métrica | Original | Composición elegida |
| --- | ---: | ---: |
| Ejecuciones | 18 | 18 |
| PASS dirigidos | 14 | 18 |
| Mediana por turno | 5.641,58 ms | 4.648,775 ms |
| p95 por turno | 12.271,38 ms | 6.992,86 ms |
| Llamadas al modelo | 49 | 34 |

**−17,6 % de mediana y −30,6 % de llamadas en esta muestra**, sin garantía de un segundo, de todas las preguntas o de cargas de producción. La validación amplia se detuvo en 31/101 ejecuciones por presupuesto, incluyendo un fallo técnico Luna/Sol; no se da por completa. No se alteraron prompt, historial, store, streaming, cuatro rondas, budgets o flags globales. Confirmación e idempotencia siguen cubiertas por tests HTTP simulados. No abrir beta automáticamente.

La [guía de function calling](https://developers.openai.com/api/docs/guides/function-calling) respalda componer funciones siempre consecutivas y resolver en código argumentos conocidos. Su aplicación aquí no cambia la decisión de negocio ni permite al modelo calcular deuda o ejecutar escrituras.

La suite ordinaria final tiene **480 correctas/3.243 aserciones y dos skips live**, más 64 correctas/499 aserciones y un skip en PostgreSQL temporal aislado. Clasificaciones, discrepancias grader/Codex, revisión humana pendiente, límites y artefactos están en [revisión completa de esta comparación](ai-latency-review-20261005.md). Los apartados siguientes documentan **el primer bloque previo, sin inferencias**, cuyos números no son los de esta comparación.

## Primer bloque: qué se cambió antes de la comparación

Los resúmenes financieros y la comprobación de afirmaciones sobre alquileres pendientes llamaban a `list_rent_charges` para quedarse únicamente con `summary`. Esa llamada calculaba los saldos y después cargaba hasta veinte mensualidades, contratos, inmuebles y participantes que se descartaban. Los detalles de un contrato hacían una carga similar para su resumen.

`AssistantLeasingQueries::pendingRentSummary` reutiliza exactamente la consulta y agregación de `charges`, sin cargar esa muestra. El modo de resumen interno también se usa en los detalles de contratos. No es una tool nueva, no duplica el cálculo y no cambia las respuestas de las tools, las monedas, los periodos ni los filtros. Los pagos se consultan de nuevo: no se almacenan respuestas financieras en caché.

El chat muestra una espera neutral, el tiempo transcurrido a partir de tres segundos y un aviso si supera diez segundos. Ese contador mide la espera del navegador, no el progreso de una tool. No inicia polling, reintentos ni peticiones adicionales. Se limpia al terminar o desmontar el componente; el contador no produce anuncios continuos para lectores de pantalla. No se muestran tokens del modelo antes del parsing y las validaciones financieras.

## Medición reproducible

Benchmark sintético de 25 mensualidades, un contrato y un pago parcial: diez repeticiones por variante y ámbito, en el mismo proceso de tests. La referencia sigue siendo la llamada completa a `list_rent_charges`; la alternativa devuelve su mismo resumen sin cargar filas. Son tiempos locales de la consulta y proyección, **no del chat ni de OpenAI**.

| Base | Ámbito | Consultas referencia → resumen | Media referencia → resumen |
| --- | --- | ---: | ---: |
| SQLite en memoria | Cartera | 5 → 1 | 3,715 → 0,893 ms |
| SQLite en memoria | Inmueble | 6 → 2 | 3,952 → 1,129 ms |
| PostgreSQL 17 temporal | Cartera | 5 → 1 | 6,928 → 2,286 ms |
| PostgreSQL 17 temporal | Inmueble | 6 → 2 | 7,654 → 2,752 ms |

La mejora elimina trabajo innecesario, pero **no justifica prometer segundos de aceleración**. El efecto observado aquí es de unos pocos milisegundos. Depende del tamaño de cartera, la base de datos y su infraestructura; no extrapolar estos números a cincuenta inmuebles o al servidor de producción.

Los informes sintéticos existentes ofrecen únicamente una referencia histórica: las 47 ejecuciones con código final del 1 de octubre tenían mediana de 4.065,5 ms, p95 de 8.828,61 ms y máximo de 10.987,03 ms; las cinco del 2 de octubre, mediana de 6.553 ms. No son un antes/después de este cambio ni permiten atribuir todo el tiempo a una fase concreta. En este primer bloque no se habían realizado nuevas inferencias para comparar; la comparación posterior está documentada arriba.

Repetir el benchmark sin proveedor, desde `backend/`:

```bash
ALQUIVO_BENCHMARK_ASSISTANT=YES ALQUIVO_LIVE_PRODUCTION_EVAL=NO ALQUIVO_LIVE_FINANCIAL_HISTORY=NO OPENAI_API_KEY=synthetic_offline_only RESEND_API_KEY=synthetic_offline_only php vendor/bin/phpunit --filter AssistantLeasingToolsTest
```

El test imprime solo base, ámbito, número de consultas, repeticiones y medias. No contiene prompts, nombres, IDs ni datos de usuarios. Para PostgreSQL debe usarse una base de testing vacía y dedicada, **nunca** la base de la aplicación: `RefreshDatabase` modifica la base seleccionada.

## Diagnóstico sin conversaciones

Nuevo comando operativo, solo para quien tenga acceso autorizado al servidor:

```bash
docker compose exec -T backend php artisan assistant:latency --days=7 --limit=1000
docker compose exec -T backend php artisan assistant:latency --days=7 --limit=1000 --json
```

Lee exclusivamente columnas de métricas ya existentes en `ai_run_steps`. Agrupa por modelo/tool: número de pasos, fallidos/rechazados, tiempos desconocidos, mediana, p95, máximo y porcentaje de tokens de entrada cacheados comunicado por el proveedor. No lee mensajes, respuestas, argumentos, metadatos, referencias de proveedor ni identidades de cuenta/cartera. No escribe, no consume IA y no crea una ruta HTTP pública.

Ventana admitida: 1–30 días; muestra: 1–5.000 pasos recientes. Indica cuando la muestra es parcial. Los tiempos ausentes se muestran como desconocidos, no como cero. Las métricas son **por paso**, no por turno completo: no sumar medianas ni asumir que una petición tuvo una sola ronda. La caché de tokens no equivale a cachear los datos financieros de una conversación.

Primera comprobación local: el comando leyó 37 pasos existentes de los últimos siete días, sin contenido de conversaciones ni nuevas inferencias. Los 23 pasos de proveedor Luna tenían mediana de 3.324 ms y p95 de 4.492 ms; los 14 pasos de tools, medianas por grupo de entre 7,5 y 27 ms. El porcentaje agregado de entrada cacheada registrado era 83,94 %. Es una muestra previa al cambio, no una comparación de rendimiento posterior, y no determina la duración completa de cada respuesta. Apunta a que la mayor aceleración tendrá que abordarse en las llamadas/generación del modelo, no en quitar más validaciones SQL.

Si los pasos SQL son rápidos pero los del proveedor tardan segundos, mejorar el SQL no resolverá la mayor parte de la espera. Optimizar número de llamadas, contexto, generación o modelo exige una comparación real con presupuesto explícito y regresiones semánticas de los casos sensibles. No utilizar datos de usuarios para esa evaluación.

## Lo que se mantiene intacto

- Prompt de producción, schemas, historial y herramientas disponibles.
- Luna principal, Sol exclusivamente para fallo técnico/formato, sin routing por complejidad ni Astra.
- Cuatro rondas, un tool call por respuesta y límites de tiempo, tokens y presupuesto.
- Autorización por cartera, MFA, consentimiento, cifrado, confirmación por endpoint separado e idempotencia.
- Document AI en simulación y flags globales sin cambios.
- `store=false`, parámetros de API y políticas de conservación actuales: sin solicitar nuevas opciones de retención/caché al proveedor.

La guía oficial de [optimización de latencia de OpenAI](https://developers.openai.com/api/docs/guides/latency-optimization) distingue reducir trabajo real y mejorar la espera percibida. Las llamadas sucesivas y la generación influyen en el tiempo; no basta con quitar indiscriminadamente instrucciones. La [documentación de prompt caching](https://developers.openai.com/api/docs/guides/prompt-caching) recomienda prefijos estables. Por eso no se recortaron instrucciones ni se reordenaron tools en este bloque conservador.

## Verificación del bloque

- Backend ordinario: 444 pruebas, 442 correctas, 2.907 aserciones y dos skips live opt-in previstos.
- PostgreSQL 17 aislado y sin Internet: 74 pruebas, 73 correctas, 458 aserciones y un skip live; es un subconjunto repetido, no sumar como casos nuevos. Base temporal retirada.
- Frontend: 171 pruebas correctas, incluidas seis de ciclo de vida del contador.
- Las regresiones comparan los saldos exactamente, cubren pagos nuevos/eliminados, cobros completos, inmuebles ajenos/eliminados, cancelaciones, detalles de contrato y ausencia de contenido privado en el diagnóstico.
- Build cliente/SSR y SEO de diez páginas correctos. Archivo legal `2026-10-04` sin cambios.
- Pint y Prettier correctos en los archivos modificados; `git diff --check` y el escaneo conservador de secretos correctos. Los checks globales de formato detectan seis archivos PHP y siete archivos frontend **ya sin formatear en HEAD**, ajenos a este cambio; se conservaron sin modificaciones. No son fallos funcionales ni regresiones de este bloque, pero el formato global no está completamente limpio.
- **0 llamadas, 0 tokens y 0 USD nuevos de IA.** No hay nuevas clasificaciones de calidad de Luna ni revisión humana independiente.
- No hay navegador conectado en esta sesión; la revisión visual interactiva del estado de espera queda pendiente. Los tests y el build no sustituyen esa comprobación.
- Servicios locales reconstruidos y actualizados; PostgreSQL y los volúmenes privados conservados, sin reset ni seeders. Portada y `/up`: HTTP 200; `/api/v1/auth/me` sin sesión: HTTP 401. No se ejecutaron prompts contra carteras reales ni se alteró su segundo factor para entrar. Aplicación en `http://localhost:8080`.

Las evaluaciones previas y los requisitos de lanzamiento siguen en [ai-evaluations.md](ai-evaluations.md), [ai-implementation-progress.md](ai-implementation-progress.md) y [beta-launch-guide.md](beta-launch-guide.md). No declarar la IA lista para beta por una mejora de rendimiento local.
