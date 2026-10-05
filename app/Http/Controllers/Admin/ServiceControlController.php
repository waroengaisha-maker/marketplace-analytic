<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServiceControlController extends Controller
{
    private const ACTIONS = ['start', 'stop', 'restart'];

    public function status(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        try {
            $response = $this->manager()->get('/status');

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::error('Service manager status request failed.', [
                'exception' => $e::class,
            ]);

            return response()->json(['message' => 'Service manager is unavailable.'], 503);
        }
    }

    public function action(Request $request, string $service, string $action): JsonResponse
    {
        $this->authorizeSuperAdmin($request);
        abort_unless(in_array($action, self::ACTIONS, true), 404);
        abort_unless(collect(config('services-hub.services', []))->contains('key', $service), 404);

        try {
            $response = $this->manager()->post('/services/'.$service.'/'.$action);

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            Log::error('Service manager action request failed.', [
                'service' => $service,
                'action' => $action,
                'exception' => $e::class,
            ]);

            return response()->json(['message' => 'Service manager is unavailable.'], 503);
        }
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function manager()
    {
        return Http::baseUrl((string) config('services-hub.manager.url'))
            ->withToken((string) config('services-hub.manager.token'))
            ->acceptJson()
            ->timeout(10);
    }
}
