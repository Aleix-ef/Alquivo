<?php

namespace App\Domain\Assistant\Providers;

use App\Domain\Assistant\Contracts\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class OpenAIProvider implements AIProviderInterface
{
    public function generate(array $request, float $timeout): array
    {
        if (! filled(config('services.openai.key'))) {
            throw new ProviderException('not_configured');
        }
        try {
            $response = Http::baseUrl(rtrim((string) config('assistant.base_url'), '/'))
                ->withToken((string) config('services.openai.key'))->acceptJson()
                ->connectTimeout(min(5, $timeout))->timeout($timeout)->withoutRedirecting()
                ->post('/responses', [...$request, 'store' => false])->throw();
        } catch (ConnectionException) {
            throw new ProviderException('connection_error', true);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            // Never persist provider error bodies: they may echo request data.
            throw new ProviderException('http_'.$status, $status === 429 || $status >= 500);
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new ProviderException('invalid_response');
        }

        return $data;
    }
}
