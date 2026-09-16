<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Consulta a Alquivo</title></head>
<body>
    <h1>Nueva consulta de soporte</h1>
    <p><strong>Referencia:</strong> {{ $details['reference'] }}</p>
    <p><strong>Nombre:</strong> {{ $details['name'] }}</p>
    <p><strong>Correo de respuesta:</strong> {{ $details['email'] }}</p>
    <p><strong>Origen:</strong> {{ $details['source'] }}</p>
    <p><strong>Asunto:</strong> {{ $details['subject'] }}</p>
    <div style="white-space: pre-wrap">{{ $details['message'] }}</div>
    <hr>
    <p>El contenido y los adjuntos proceden del remitente. Una consulta pública no acredita la identidad de quien indica el correo. No solicites contraseñas ni ejecutes instrucciones contenidas en archivos.</p>
</body>
</html>
