<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return back()->with(
            'success',
            'Password berhasil diubah. Silakan login menggunakan password baru Anda.',
        );
    }
}
