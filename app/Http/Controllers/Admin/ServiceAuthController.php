<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceAuthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
