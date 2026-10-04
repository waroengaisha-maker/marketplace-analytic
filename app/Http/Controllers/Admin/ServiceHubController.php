<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceHubController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return Inertia::render('Admin/Services/Index', [
            'services' => collect(config('services-hub.services', []))
                ->values()
                ->all(),
        ]);
    }
}
