# Evaluaciones de Alquivo AI

## Estado del primer bloque

Existe un harness **offline de contratos** con nueve casos sintéticos de lectura, herramientas de fixture, un grader determinista y un comparador. No es todavía un benchmark de modelos ni una prueba de calidad del asistente completo de producción.

No realiza consultas de negocio, escrituras ni llamadas de red. Utiliza `AIProviderInterface` con `FakeAIProvider`, `AssistantReply`, las definiciones de herramientas existentes y el calculador de tarifas central. No instancia usuarios ni carteras. Los identificadores y cifras de las fixtures son inventados.

`--live` falla expresamente antes de llamar al proveedor. Para habilitar evaluación real falta compartir el prompt y flujo del orquestador de producción e integrar reserva de presupuesto por llamada, timeout global, autorización local/testing y límites explícitos. No debe eliminarse este bloqueo para hacer pasar una demostración por evaluación real.

## Uso

Desde `backend/`:

```bash
php artisan ai:eval --profile=fast
php artisan ai:eval --profile=fast --json
php artisan ai:eval --profile=analysis --json
php artisan ai:eval --profile=fast --candidate=regression --compare=reference --json
php artisan test --filter=AssistantEvaluationTest
```

El último replay introduce deliberadamente una cifra incorrecta, detecta la regresión y termina con código 1. El replay de referencia debe terminar con código 0. El JSON incluye versión y hash de la suite para comparar resultados obtenidos con los mismos casos.

Cambiar `--profile` cambia el identificador configurado y la **estimación de coste de los mismos tokens sintéticos**. No cambia las respuestas grabadas ni demuestra que un modelo sea más preciso. La latencia es la del procesamiento local, no la latencia del proveedor. No existe facturación del replay.

## Casos y métricas

Las fixtures viven en `backend/tests/fixtures/assistant/read-cases.json` y tienen: petición, contexto sintético, resultados de herramientas, secuencia/argumentos esperados, tipo de respuesta, hechos obligatorios, afirmaciones prohibidas, números permitidos y necesidad de aclaración. El replay reproduce Responses sin almacenar prompts de usuarios.

Se comprueban:

- Secuencia exacta de herramientas y argumentos, ignorando solo el orden de claves.
- Tipo de respuesta y presencia de hechos requeridos.
- Ausencia de afirmaciones expresamente prohibidas y números fuera del conjunto esperado.
- Aclaración cuando procede, mediante una heurística explícita de lenguaje.
- Llamadas, tokens, tarifas versionadas, coste estimado y latencia local p50/p95.

Los casos cubren patrimonio, gastos mensuales, resolución de inmueble, impagos, nombres ambiguos, valoración ausente, soporte, preguntas fuera de ámbito y contenido no confiable dentro de una ficha.

El detector numérico y los fragmentos de texto no detectan toda invención semántica ni aceptan todas las paráfrasis válidas. Una pregunta retórica puede confundirse con una aclaración. No deben usarse estas métricas aisladas como criterio de seguridad o cambio automático de routing. El caso de inyección reproduce una respuesta segura grabada: no demuestra resistencia de un modelo real.

## Aislamiento y fallos

Los tool calls solo pueden obtener la respuesta de fixture prevista en ese paso y con esos argumentos. Un nombre desconocido, ID distinto, campo adicional, JSON mal formado, varias herramientas simultáneas o exceso de rondas falla cerrado. El harness nunca llama a `PortfolioAssistantTools::execute` ni a herramientas de escritura.

Los tests verifican que no se producen peticiones HTTP ni consultas SQL, el comparador detecta regresiones y un intento de usar `--live` no accede al proveedor. Las pruebas de permisos y escrituras reales pertenecen a las suites del dominio/orquestador; este harness no las sustituye.

## Siguiente ampliación

1. Reutilizar prompt y orquestación de producción mediante executor de herramientas de fixture inyectable.
2. Habilitar inferencia real solo con opción explícita, entorno local/testing y presupuesto reservado antes de cada intento. Mantener datos sintéticos y herramientas dry-run.
3. Añadir casos de propuesta de gasto, revisión, cancelación y ambigüedad al quedar estable su contrato. Las confirmaciones siguen fuera de las herramientas del modelo.
4. Incorporar pares de perfiles/modelos con idéntica versión de fixtures/prompt y varias repeticiones controladas.
5. Medir hechos estructurados y evidencias, ampliar variantes y realizar revisión humana de respuestas. No seleccionar el routing por intuición ni por el resultado de un replay.
