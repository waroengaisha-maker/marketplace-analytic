@component('mail::message')
# Reset Password

Halo {{ $name }},

Kami menerima permintaan untuk mengatur ulang password akun **Marketplace Analytics** Anda.

Klik tombol di bawah untuk membuat password baru.

@component('mail::button', ['url' => $url])
Reset Password
@endcomponent

Jika Anda tidak merasa melakukan permintaan ini, Anda dapat mengabaikan email ini. Password Anda tidak akan berubah tanpa tindakan Anda.

Link reset password ini memiliki masa berlaku terbatas untuk menjaga keamanan akun Anda.

Terima kasih,<br>
**Marketplace Analytics**
@endcomponent
