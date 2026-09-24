<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Support\Models\SupportAttachment;
use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportAccess;
use App\Domain\Support\Services\SupportChat;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SupportChatController extends Controller
{
    public function __construct(private SupportAccess $access, private SupportChat $chat) {}

    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['waiting_support', 'waiting_customer', 'closed'])], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $query = $this->access->query($request);
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        $result = $query->orderByDesc('updated_at')->orderBy('id')->paginate(20);

        return response()->json([
            'conversations' => $result->getCollection()->map(fn ($conversation) => $this->summary($conversation, $request)),
            'current_page' => $result->currentPage(), 'last_page' => $result->lastPage(),
            'can_manage' => ! $this->access->isPublic($request) && $this->access->canManage($request->user()),
            'retention_days' => (int) config('support.chat_retention_days', 180),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(SupportChatRequest $request)
    {
        abort_if($this->access->isTeam($request), 403);
        $conversation = $this->chat->send($request, $request->validated());

        return response()->json($this->summary($conversation, $request), 201)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $conversation)
    {
        $data = $request->validate(['after' => ['nullable', 'integer', 'min:0'], 'before' => ['nullable', 'integer', 'min:1']]);
        abort_if(isset($data['after'], $data['before']), 422);
        $thread = $this->access->query($request)->findOrFail($conversation);
        $query = $thread->messages()->with('attachments');
        if (isset($data['after'])) {
            $query->where('id', '>', $data['after'])->orderBy('id');
        } else {
            if (isset($data['before'])) {
                $query->where('id', '<', $data['before']);
            }
            $query->orderByDesc('id');
        }
        $messages = $query->limit(50)->get()->sortBy('id')->values();

        return response()->json([
            'conversation' => $this->summary($thread, $request),
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id, 'body' => $message->body, 'author_role' => $message->author_role,
                'created_at' => $message->created_at->toIso8601String(),
                'attachments' => $message->attachments->map(fn ($file) => $file->only(['id', 'filename', 'size', 'mime_type'])),
            ]),
            'has_older' => $messages->isNotEmpty() && $thread->messages()->where('id', '<', $messages->first()->id)->exists(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function send(SupportChatRequest $request, string $conversation)
    {
        $thread = $this->chat->send($request, $request->validated(), $conversation);
        if ($this->access->isTeam($request)) {
            SecurityAudit::record('support.replied', $request->user()->id);
        }

        return response()->json($this->summary($thread, $request), 201)->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $conversation)
    {
        $data = $request->validate(['message_id' => ['required', 'integer', 'min:1']]);
        $thread = $this->access->query($request)->findOrFail($conversation);
        $thread->messages()->findOrFail($data['message_id']);
        $column = $this->access->isTeam($request) ? 'team_read_id' : 'customer_read_id';
        // Do not extend retention simply because someone opens or polls a chat.
        DB::table('support_conversations')->where('id', $thread->id)->where($column, '<', $data['message_id'])->update([$column => $data['message_id']]);

        return response()->noContent();
    }

    public function close(Request $request, string $conversation)
    {
        $thread = $this->access->query($request)->findOrFail($conversation);
        $thread->update(['status' => 'closed']);
        if ($this->access->isTeam($request)) {
            SecurityAudit::record('support.closed', $request->user()->id);
        }

        return response()->json($this->summary($thread, $request));
    }

    public function attachment(Request $request, string $conversation, string $attachment)
    {
        $thread = $this->access->query($request)->findOrFail($conversation);
        $file = SupportAttachment::whereIn('message_id', $thread->messages()->select('id'))->findOrFail($attachment);
        SecurityAudit::record('support.attachment_downloaded', $request->user()?->id);
        $bytes = app(PrivateFileVault::class)->read($file->storage_key);

        return response()->streamDownload(fn () => print ($bytes), $file->filename, ['Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function summary(SupportConversation $conversation, Request $request): array
    {
        $team = $this->access->isTeam($request);
        $read = $team ? $conversation->team_read_id : $conversation->customer_read_id;

        return [
            'id' => $conversation->id, 'subject' => $conversation->subject, 'status' => $conversation->status,
            'updated_at' => $conversation->updated_at->toIso8601String(),
            'last_message_id' => $conversation->last_message_id,
            'unread' => $conversation->last_message_id > $read && $conversation->last_author === ($team ? 'customer' : 'support'),
            ...($team ? ['name' => $conversation->name, 'email' => $conversation->email, 'source' => $conversation->user_id ? 'account' : 'visitor'] : []),
        ];
    }
}
