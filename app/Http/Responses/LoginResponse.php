<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        // Keep the post-login navigation relative to the current origin.
        // This prevents Inertia from attempting an insecure HTTP request when
        // the application is served through an HTTPS reverse proxy.
        return redirect('/');
    }
}
