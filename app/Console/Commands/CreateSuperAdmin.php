<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {--name= : Nama lengkap} {--email= : Email login}';

    protected $description = 'Buat akun super admin (interaktif: nama, email, password, konfirmasi password)';

    public function handle(): int
    {
        // roles may not exist yet on a brand new install
        if (! Role::where('name', 'super_admin')->exists()) {
            (new RolesSeeder)->run();
        }

        $name = $this->option('name') ?: text('Nama lengkap', required: true);
        $email = $this->option('email') ?: text('Email login', required: true, validate: fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email tidak valid.');
        if (User::where('email', $email)->exists()) {
            $this->error('Email itu sudah terdaftar.');

            return self::FAILURE;
        }
        $pass = password('Password (minimal 12 karakter)', required: true, validate: fn ($v) => strlen($v) < 12 ? 'Minimal 12 karakter.' : null);
        $confirm = password('Ulangi password', required: true);
        if ($pass !== $confirm) {
            $this->error('Konfirmasi password tidak sama.');

            return self::FAILURE;
        }
        $v = Validator::make(['password' => $pass], ['password' => ['string', 'min:12']]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $pass, 'is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('super_admin');
        $this->info("Super admin dibuat: {$user->email}. Masuk di /admin/login.");

        return self::SUCCESS;
    }
}
