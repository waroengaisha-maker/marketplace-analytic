<?php

namespace Tests\Unit\Http\Responses;

use App\Http\Responses\LoginResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class LoginResponseTest extends TestCase
{
    public function test_login_response_redirects_to_canonical_application_url(): void
    {
        config(['app.url' => 'https://marketplace-analytics.my.id']);

        $response = (new LoginResponse)->toResponse(
            Request::create('/login', 'POST'),
        );

        $this->assertSame(
            'https://marketplace-analytics.my.id',
            $response->headers->get('Location'),
        );
    }
}
