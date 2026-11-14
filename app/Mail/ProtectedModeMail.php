<?php

namespace App\Mail;

use App\Models\User;

/** Warning: many failed sign-ins on this account, protected mode is on. */
class ProtectedModeMail extends SecurityMail
{
    public function __construct(public User $user, public int $failures) {}

    protected function subjectLine(): string
    {
        return 'Peringatan: banyak percobaan masuk ke akun admin Anda';
    }

    protected function body(): array
    {
        return [
            'title' => 'Akun Anda sedang dicoba dibobol',
            'lines' => [
                "Dalam satu jam terakhir ada {$this->failures} percobaan masuk yang gagal ke akun {$this->user->email}, dari beberapa alamat IP.",
                'Akun Anda TIDAK dikunci. Anda tetap bisa masuk seperti biasa dari perangkat yang sudah pernah dipakai. Dari perangkat baru, sistem akan meminta konfirmasi lewat link yang dikirim ke email ini.',
                'Mode terlindungi ini berakhir sendiri setelah 24 jam tanpa percobaan gagal. Kalau Anda curiga password sudah diketahui orang lain, ganti password lewat "Lupa password" dan pilih untuk mencabut semua sesi dan perangkat.',
            ],
            'button' => ['Buka halaman masuk', admin_url('/login')],
        ];
    }
}
