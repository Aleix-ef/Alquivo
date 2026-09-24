<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assistant\Documents\DocumentAiAccess;
use App\Domain\Assistant\Documents\DocumentDemoFixtures;
use App\Domain\Assistant\Documents\DocumentExtractionService;
use App\Domain\Assistant\Models\AiDocumentExtraction;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentAiController extends Controller
{
    public function __construct(private DocumentExtractionService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        app(DocumentAiAccess::class)->assertAvailable($user, $user->portfolio(), false);

        return ['simulated' => true, 'notice_version' => config('ai_documents.notice_version'),
            'accepted' => $user->document_ai_accepted_at && $user->document_ai_notice_version === config('ai_documents.notice_version'),
            'limits' => ['bytes' => config('ai_documents.max_bytes'), 'pages' => config('ai_documents.max_pages')],
            'extractions' => AiDocumentExtraction::where('user_id', $user->id)->where('portfolio_id', $user->portfolio()->id)
                ->latest()->limit(30)->get()->map(fn ($e) => collect($this->service->present($e))->except('draft'))];
    }

    public function consent(Request $request)
    {
        $user = $request->user();
        app(DocumentAiAccess::class)->assertAvailable($user, $user->portfolio(), false);
        $request->validate(['accepted' => 'accepted', 'notice_version' => ['required', Rule::in([config('ai_documents.notice_version')])]]);
        $user->forceFill(['document_ai_accepted_at' => now(), 'document_ai_notice_version' => config('ai_documents.notice_version')])->save();

        return ['accepted' => true];
    }

    public function revoke(Request $request)
    {
        $user = $request->user();
        app(DocumentAiAccess::class)->assertAvailable($user, $user->portfolio(), false);
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->forceFill(['document_ai_accepted_at' => null, 'document_ai_notice_version' => null])->save();
            AiDocumentExtraction::where('user_id', $user->id)->where('status', '!=', 'confirmed')
                ->update(['status' => 'cancelled', 'draft' => null]);
            AiDocumentExtraction::where('user_id', $user->id)->where('status', 'confirmed')->update(['draft' => null]);
        });

        return response()->noContent();
    }

    public function example(Request $request, string $kind)
    {
        app(DocumentAiAccess::class)->assertAvailable($request->user(), $request->user()->portfolio(), false);
        abort_unless(in_array($kind, ['invoice', 'contract'], true), 404);

        return response()->streamDownload(fn () => print (app(DocumentDemoFixtures::class)->pdf($kind)), 'alquivo-demo-'.$kind.'.pdf',
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['document_id' => 'required|integer', 'kind' => ['required', Rule::in(['invoice', 'contract'])]]);
        $portfolio = $request->user()->portfolio();
        app(DocumentAiAccess::class)->assertAvailable($request->user(), $portfolio);
        $document = Document::where('portfolio_id', $portfolio->id)->findOrFail($data['document_id']);
        $extraction = $this->service->start($request->user(), $portfolio, $document, $data['kind']);

        return response()->json($this->service->present($extraction), 202);
    }

    public function show(Request $request, string $extraction)
    {
        return $this->service->present($this->service->owned($request->user(), $request->user()->portfolio(), $extraction));
    }

    public function mutate(Request $request, string $extraction, string $operation)
    {
        return $this->service->present($this->service->mutate($request->user(), $request->user()->portfolio(), $extraction, $operation, $request->all()));
    }
}
