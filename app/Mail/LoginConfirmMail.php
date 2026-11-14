<?php

namespace App\Mail;

use App\Models\User;
use App\Support\Analytics;

/** One-time link to finish a sign-in from an unknown device while the account is protected. */
class LoginConfirmMail extends SecurityMail
{
    public function __construct(public User $user, public string $url, public string $ip, public string $userAgent) {}

    protected function subjectLine(): string
    {
        return 'Konfirmasi login ke panel admin';
    }

    protected function body(): array
    {
        return [
            'title' => 'Konfirmasi login dari perangkat baru',
            'lines' => ['Akun Anda sedang dalam mode terlindungi karena banyak percobaan masuk yang gagal. Untuk menyelesaikan login dari perangkat ini, klik tombol di bawah. Link berlaku 15 menit dan hanya bisa dipakai sekali.'],
            'rows' => ['Alamat IP' => $this->ip, 'Browser' => trim(Analytics::browser($this->userAgent).' · '.Analytics::device($this->userAgent), ' ·')],
            'button' => ['Ya, ini saya: lanjutkan login', $this->url],
            'note' => 'Kalau Anda tidak sedang mencoba masuk, abaikan email ini. Password Anda tetap aman selama tidak ada yang mengklik link ini.',
        ];
    }
}
