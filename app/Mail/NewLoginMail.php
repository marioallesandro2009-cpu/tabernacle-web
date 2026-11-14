<?php

namespace App\Mail;

use App\Models\User;
use App\Support\Analytics;
use Illuminate\Support\Facades\URL;

/** "Someone signed in to your account from a new device or IP" with a link that ends every session. */
class NewLoginMail extends SecurityMail
{
    public function __construct(public User $user, public string $ip, public string $userAgent, public bool $newDevice, public bool $newIp) {}

    protected function subjectLine(): string
    {
        return 'Login baru ke akun admin '.setting('site_name', config('app.name'));
    }

    protected function body(): array
    {
        $what = $this->newDevice && $this->newIp ? 'perangkat dan alamat IP baru' : ($this->newDevice ? 'perangkat baru' : 'alamat IP baru');

        return [
            'title' => 'Login dari '.$what,
            'lines' => ["Akun {$this->user->email} baru saja masuk ke panel admin dari {$what}."],
            'rows' => [
                'Waktu' => now()->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMMM YYYY, HH:mm').' WIB',
                'Alamat IP' => $this->ip,
                'Browser' => trim(Analytics::browser($this->userAgent).' · '.Analytics::device($this->userAgent), ' ·'),
            ],
            'button' => ['Bukan saya: akhiri semua sesi', URL::temporarySignedRoute('admin.sessions.end', now()->addDays(7), ['user' => $this->user->id])],
            'note' => 'Kalau ini memang Anda, abaikan email ini. Kalau bukan, klik tombol di atas lalu segera ganti password.',
        ];
    }
}
