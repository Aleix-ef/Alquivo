<?php

namespace App\Domain\Assistant\Documents;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Documents\Services\UploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

final class DocumentFileGuard
{
    public function read(Document $document): array
    {
        $this->check($document->size > 0 && $document->size <= config('ai_documents.max_bytes'), 'El archivo supera el tamaño permitido.');
        $bytes = app(PrivateFileVault::class)->read($document->storage_key);
        $this->check(strlen($bytes) === $document->size, 'El archivo ha cambiado.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $this->check(in_array($mime, config('ai_documents.mime_types'), true) && $mime === $document->mime_type,
            'Sólo se admiten archivos PDF, JPG y PNG válidos.');
        $path = tempnam(sys_get_temp_dir(), 'alquivo-document-');
        try {
            chmod($path, 0600);
            file_put_contents($path, $bytes);
            app(UploadScanner::class)->scan(new UploadedFile($path, 'document', $mime, null, true));
            $pages = 1;
            if ($mime === 'application/pdf') {
                // No shell, network, rendering or OCR. Bounded parser, not a regex page count.
                $process = new Process(['prlimit', '--as=268435456', '--cpu=5', '--nofile=32', '--', 'pdfinfo', $path], env: ['LC_ALL' => 'C'], timeout: 5);
                $process->run();
                $output = $process->getOutput();
                $this->check($process->isSuccessful() && preg_match('/^Pages:\s+(\d+)$/m', $output, $matches)
                    && preg_match('/^Encrypted:\s+no/m', $output), 'PDF ilegible o protegido con contraseña.');
                $pages = (int) $matches[1];
            } else {
                $size = @getimagesizefromstring($bytes);
                $this->check($size && $size[0] > 0 && $size[1] > 0
                    && config('ai_documents.max_image_pixels') >= $size[0] * $size[1], 'Imagen inválida o demasiado grande.');
            }
            $this->check($pages > 0 && $pages <= config('ai_documents.max_pages'), 'El documento supera el límite de páginas.');

            return ['bytes' => $bytes, 'mime' => $mime, 'pages' => $pages,
                'hash' => hash_hmac('sha256', $bytes, config('app.key'))];
        } finally {
            @unlink($path);
        }
    }

    private function check(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['file' => [$message]]);
        }
    }
}
