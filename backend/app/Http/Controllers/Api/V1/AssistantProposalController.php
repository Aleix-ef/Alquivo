<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assistant\Services\ActionProposalService;
use App\Domain\Portfolio\Models\Portfolio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssistantProposalController extends Controller
{
    public function __construct(private readonly ActionProposalService $proposals) {}

    public function show(Request $request, string $proposal): array
    {
        return ['proposal' => $this->proposals->present(
            $this->proposals->findOwned($this->portfolio($request), $request->user(), $proposal),
        )];
    }

    public function revise(Request $request, string $proposal): array
    {
        $owned = $this->proposals->findOwned($this->portfolio($request), $request->user(), $proposal);
        $data = $this->validateInput($request, ['revision', ...$this->proposals->editableFields($owned)]);
        $revision = (int) $data['revision'];
        unset($data['revision']);

        return ['proposal' => $this->proposals->present($this->proposals->revise(
            $this->portfolio($request), $request->user(), $proposal, $revision, $data,
        ))];
    }

    public function confirm(Request $request, string $proposal): array
    {
        $data = $this->validateInput($request, ['revision']);

        return ['proposal' => $this->proposals->present($this->proposals->confirm(
            $this->portfolio($request), $request->user(), $proposal, (int) $data['revision'],
        ))];
    }

    public function cancel(Request $request, string $proposal): array
    {
        $data = $this->validateInput($request, ['revision']);

        return ['proposal' => $this->proposals->present($this->proposals->cancel(
            $this->portfolio($request), $request->user(), $proposal, (int) $data['revision'],
        ))];
    }

    private function portfolio(Request $request): Portfolio
    {
        $portfolio = $request->user()->portfolio();
        abort_unless($portfolio, 404);

        return $portfolio;
    }

    private function validateInput(Request $request, array $allowed): array
    {
        if (array_diff(array_keys($request->all()), $allowed)) {
            throw ValidationException::withMessages(['proposal' => ['La petición contiene campos que no están permitidos.']]);
        }
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);

        return $request->only($allowed);
    }
}
