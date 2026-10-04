<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ServerStatusService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerStatusController extends Controller
{
    public function __invoke(Request $request, ServerStatusService $serverStatus): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return Inertia::render('Admin/Server/Index', [
            'status' => $serverStatus->snapshot(),
            'adminer' => config('adminer'),
        ]);
    }
}
