<?php

namespace App\Domain\Support\Services;

use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Documents\Services\UploadScanner;
use App\Domain\Support\Models\SupportAttachment;
use App\Domain\Support\Models\SupportConversation;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupportChat
{
    public function __construct(private SupportAccess $access, private PrivateFileVault $vault, private UploadScanner $scanner) {}

    public function send(Request $request, array $data, ?string $conversationId = null): SupportConversation
    {
        // Authorize before scanning uploads; never spend work on a foreign thread.
        $scope = $this->access->query($request);
        $creating = $conversationId === null;
        if (! $creating) {
            (clone $scope)->findOrFail($conversationId);
        }
        $files = array_values($request->file('attachments', []));
        foreach ($files as $index => $file) {
            try {
                $this->scanner->scan($file);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['attachments.'.$index => ['El archivo no ha superado el análisis de seguridad.']]);
            }
        }
        $stored = [];
        try {
            $write = function () use ($request, $data, $conversationId, $scope, $creating, $files, &$stored) {
                return DB::transaction(function () use ($request, $data, $conversationId, $scope, $creating, $files, &$stored) {
                    $query = (clone $scope)->lockForUpdate();
                    $conversation = $creating ? $query->where('creation_key', $data['conversation_id'])->first() : $query->find($conversationId);
                    if (! $conversation) {
                        abort_unless($creating, 404);
                        abort_if((clone $scope)->count() >= 20, 422, 'Has alcanzado el límite de conversaciones conservadas. Continúa en una consulta existente.');
                        $public = $this->access->isPublic($request);
                        $conversation = SupportConversation::create([
                            'id' => (string) Str::uuid(), 'creation_key' => $data['conversation_id'], ...$this->access->owner($request),
                            'name' => $public ? $data['name'] : $request->user()->name,
                            'email' => $public ? ($data['email'] ?? null) : $request->user()->email,
                            'subject' => $data['subject'],
                        ]);
                    }
                    $id = $conversation->id;
                    $team = $this->access->isTeam($request);
                    $role = $team ? 'support' : 'customer';
                    $author = $this->access->isPublic($request) ? null : $request->user()->id;
                    $fingerprint = hash('sha256', json_encode([
                        $role, $author, $data['message'],
                        array_map(fn ($file) => [$file->getMimeType(), hash_file('sha256', $file->getRealPath())], $files),
                    ]));
                    $existing = $conversation->messages()->where('client_id', $data['client_id'])->first();
                    if ($existing) {
                        abort_unless($existing->request_hash && hash_equals($existing->request_hash, $fingerprint), 409, 'Este envío ya existe. Actualiza la conversación antes de enviar otro mensaje.');

                        return $conversation;
                    }
                    abort_if($conversation->messages()->count() >= 100, 422, 'Esta conversación ha alcanzado su límite. Abre una nueva consulta indicando esta referencia.');
                    $used = SupportAttachment::whereIn('message_id', $conversation->messages()->select('id'))->sum('size');
                    abort_if($used + array_sum(array_map(fn ($file) => $file->getSize(), $files)) > 20 * 1024 * 1024, 422, 'Esta conversación admite un máximo de 20 MB en adjuntos.');
                    $message = $conversation->messages()->create([
                        'author_user_id' => $author, 'request_hash' => $fingerprint,
                        'author_role' => $role, 'body' => $data['message'], 'client_id' => $data['client_id'],
                    ]);
                    foreach ($files as $index => $file) {
                        $key = $this->vault->store($file, "support/conversations/{$id}");
                        $stored[] = $key;
                        if (! Storage::disk('local')->exists($key)) {
                            throw new \RuntimeException('Support attachment storage failed');
                        }
                        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$file->getMimeType()];
                        $message->attachments()->create([
                            'id' => (string) Str::uuid(), 'storage_key' => $key,
                            'filename' => 'adjunto-'.($index + 1).'.'.$extension,
                            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                        ]);
                    }
                    $conversation->update([
                        'status' => $team ? 'waiting_customer' : 'waiting_support',
                        'last_author' => $role, 'last_message_id' => $message->id,
                        $team ? 'team_read_id' : 'customer_read_id' => $message->id,
                    ]);

                    return $conversation;
                });
            };
            // Serializes creation/retries and the per-owner retention cap. Existing
            // threads additionally use database row locks across different agents.
            $owner = $this->access->owner($request);
            $key = hash('sha256', json_encode($owner));

            return Cache::lock('support-write:'.$key, 30)->block(5, $write);
        } catch (LockTimeoutException) {
            abort(409, 'Hay otro envío en curso. Espera un momento y vuelve a intentarlo.');
        } catch (\Throwable $exception) {
            foreach ($stored as $key) {
                $cleanup = app(PrivateFileDeletion::class);
                $cleanup->process($cleanup->schedule($key));
            }
            throw $exception;
        }
    }

    // Call inside the same transaction as deletion; retries survive process failure.
    public function scheduleDeletion(SupportConversation $conversation): int
    {
        $id = app(PrivateFileDeletion::class)->schedule("support/conversations/{$conversation->id}", true);
        $conversation->delete();

        return $id;
    }
}
