<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Services\StorageUsageService;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private readonly StorageUsageService $storageUsage) {}

    public function index(Request $request)
    {
        $query = Document::where('portfolio_id', $request->user()->portfolio()->id)
            ->with(['property', 'lease.property']);
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }
        if ($request->input('status') === 'expired') {
            $query->whereDate('expires_at', '<', today());
        } elseif ($request->input('status') === 'upcoming') {
            $query->whereBetween('expires_at', [today(), today()->addDays(60)]);
        }

        return $query->orderByRaw('expires_at is null, expires_at asc')->latest('created_at')->paginate(30);
    }

    public function store(Request $request)
    {
        $portfolio = $request->user()->portfolio();
        $data = $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'], 'name' => ['nullable', 'string', 'max:150'], 'category' => ['required', Rule::in(['contract', 'invoice', 'insurance', 'tax', 'certificate', 'other'])], 'property_id' => ['nullable', 'integer'], 'lease_id' => ['nullable', 'integer'], 'issued_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at']]);
        if (! empty($data['property_id'])) {
            Property::where('portfolio_id', $portfolio->id)->findOrFail($data['property_id']);
        }if (! empty($data['lease_id'])) {
            Lease::where('portfolio_id', $portfolio->id)->findOrFail($data['lease_id']);
        }$file = $request->file('file');
        unset($data['file']);
        $document = $this->storageUsage->store($portfolio, $file, "portfolios/{$portfolio->id}/documents", fn ($key) => Document::create([...$data, 'portfolio_id' => $portfolio->id, 'name' => ($data['name'] ?? null) ?: mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 150), 'storage_key' => $key, 'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id]));

        return response()->json($document, 201);
    }

    public function download(Request $request, Document $document)
    {
        abort_unless($document->portfolio_id === $request->user()->portfolio()->id, 404);
        SecurityAudit::record('document.downloaded', $request->user()->id);

        $bytes = app(PrivateFileVault::class)->read($document->storage_key);

        return response()->streamDownload(fn () => print($bytes), $document->original_filename, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store']);
    }

    public function update(Request $request, Document $document)
    {
        $this->ensureOwned($request, $document);
        $portfolio = $request->user()->portfolio();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['sometimes', Rule::in(['contract', 'invoice', 'insurance', 'tax', 'certificate', 'other'])],
            'property_id' => ['sometimes', 'nullable', 'integer'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:issued_at'],
        ]);
        if (array_key_exists('property_id', $data) && $data['property_id']) {
            Property::where('portfolio_id', $portfolio->id)->findOrFail($data['property_id']);
        }
        $document->update($data);

        return $document->fresh('property');
    }

    public function destroy(Request $request, Document $document)
    {
        $this->ensureOwned($request, $document);
        $id = DB::transaction(function () use ($document) {
            $id = app(PrivateFileDeletion::class)->schedule($document->storage_key);
            $document->delete();

            return $id;
        });
        app(PrivateFileDeletion::class)->process($id);

        return response()->noContent();
    }

    private function ensureOwned(Request $request, Document $document): void
    {
        abort_unless($document->portfolio_id === $request->user()->portfolio()->id, 404);
    }
}
