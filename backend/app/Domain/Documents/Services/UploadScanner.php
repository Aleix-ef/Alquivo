<?php

namespace App\Domain\Documents\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UploadScanner
{
    public function scan(UploadedFile $file): void
    {
        if (! config('security.uploads_scan')) {
            abort_if(app()->isProduction(), 503, 'La subida de archivos no está disponible.');

            return;
        }

        $socket = @stream_socket_client('tcp://'.config('security.clamav_host').':'.config('security.clamav_port'), $errno, $error, 3);
        abort_unless($socket, 503, 'No se ha podido comprobar el archivo. Inténtalo más tarde.');
        $input = null;
        try {
            stream_set_timeout($socket, 10);
            $this->write($socket, "zINSTREAM\0");
            $input = fopen($file->getRealPath(), 'rb');
            if (! $input) {
                throw new RuntimeException('File unavailable');
            }
            while (! feof($input)) {
                $chunk = fread($input, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('File read failed');
                }
                if ($chunk !== '') {
                    $this->write($socket, pack('N', strlen($chunk)).$chunk);
                }
            }
            $this->write($socket, pack('N', 0));
            $response = stream_get_line($socket, 4096, "\0");
            if (is_string($response) && str_ends_with(trim($response), ' FOUND')) {
                throw ValidationException::withMessages(['file' => ['El archivo no ha superado el análisis de seguridad.']]);
            }
            if (trim((string) $response) !== 'stream: OK') {
                throw new RuntimeException('Scan incomplete');
            }
        } catch (RuntimeException) {
            abort(503, 'No se ha podido comprobar el archivo. Inténtalo más tarde.');
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            fclose($socket);
        }
    }

    private function write($socket, string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($socket, $bytes);
            if (! $written) {
                throw new RuntimeException('Scan connection failed');
            }
            $bytes = substr($bytes, $written);
        }
    }
}
