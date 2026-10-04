<?php

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\LoginResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginResponseTest extends TestCase
{
    public function test_login_response_uses_a_relative_root_redirect(): void
    {
        $response = (new LoginResponse)->toResponse(
            Request::create('/login', 'POST'),
        );

        $this->assertSame('/', $response->headers->get('Location'));
    }
}
