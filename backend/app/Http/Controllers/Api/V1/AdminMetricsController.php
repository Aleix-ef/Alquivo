<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Administration\Services\AdminRange;
use App\Domain\Administration\Services\BusinessMetrics;
use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class AdminMetricsController extends Controller
{
    public function overview(Request $request, BusinessMetrics $metrics): array
    {
        $range = new AdminRange($request);
        $key = 'admin:overview:v1:'.hash('sha256', json_encode([$request->user()->id, $range->payload(), config('beta.program_enabled'), app()->environment()]));
        SecurityAudit::record('admin.metrics_viewed', $request->user()->id);

        return Cache::remember($key, (int) config('admin.cache_seconds'), fn () => $metrics->overview($range, $request->user()->id));
    }

    public function users(Request $request, BusinessMetrics $metrics): array
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        SecurityAudit::record('admin.users_viewed', $request->user()->id);

        return $metrics->userPage(new AdminRange($request), $request->user()->id, $data['q'] ?? null);
    }

    public function aiUsers(Request $request, BusinessMetrics $metrics): array
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        SecurityAudit::record('admin.ai_usage_viewed', $request->user()->id);

        return $metrics->aiUserPage(new AdminRange($request), $request->user()->id);
    }
}
