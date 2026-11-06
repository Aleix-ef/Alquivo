<?php

namespace App\Domain\Administration\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class AdminRange
{
    public CarbonImmutable $from;

    public CarbonImmutable $until;

    public bool $includeSelf;

    public function __construct(Request $request)
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d'],
            'include_self' => ['sometimes', 'boolean'],
        ]);
        $today = CarbonImmutable::now(config('admin.timezone'))->startOfDay();
        $this->from = isset($data['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], config('admin.timezone')) : $today->subDays(29);
        $end = isset($data['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], config('admin.timezone')) : $today;
        if ($this->from > $end || $end->startOfDay() > $today || $this->from->diffInDays($end) > 365) {
            throw ValidationException::withMessages(['from' => ['Selecciona hasta 366 días, sin fechas futuras y con inicio anterior al final.']]);
        }
        $this->until = $end->addDay();
        $this->includeSelf = (bool) ($data['include_self'] ?? false);
    }

    public function payload(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->until->subDay()->toDateString(),
            'timezone' => config('admin.timezone'), 'include_self' => $this->includeSelf];
    }
}
