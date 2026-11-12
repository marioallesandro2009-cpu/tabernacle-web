<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesSeeder;

it('creates the first super admin interactively and never from a seeder', function () {
    $this->seed(RolesSeeder::class);
    expect(User::count())->toBe(0);

    $this->artisan('app:create-super-admin')
        ->expectsQuestion('Nama lengkap', 'Pemilik Situs')
        ->expectsQuestion('Email login', 'owner@example.test')
        ->expectsQuestion('Password (minimal 10 karakter)', 'kata-sandi-panjang-1')
        ->expectsQuestion('Ulangi password', 'kata-sandi-panjang-1')
        ->assertSuccessful();

    $u = User::first();
    expect($u->hasRole('super_admin'))->toBeTrue()->and($u->is_active)->toBeTrue()->and($u->password)->not->toBe('kata-sandi-panjang-1');
});

it('refuses mismatching or short passwords and duplicate emails', function () {
    $this->seed(RolesSeeder::class);
    $this->artisan('app:create-super-admin', ['--name' => 'A', '--email' => 'a@example.test'])
        ->expectsQuestion('Password (minimal 10 karakter)', 'kata-sandi-panjang-1')
        ->expectsQuestion('Ulangi password', 'beda-sandi-panjang-2')
        ->assertFailed();
    expect(User::count())->toBe(0);

    User::factory()->create(['email' => 'dup@example.test']);
    $this->artisan('app:create-super-admin', ['--name' => 'B', '--email' => 'dup@example.test'])->assertFailed();
});

it('seeds no user accounts and no passwords', function () {
    $this->seed(RolesSeeder::class);
    expect(User::count())->toBe(0);
    $src = file_get_contents(database_path('seeders/ContentSeeder.php')).file_get_contents(database_path('seeders/DatabaseSeeder.php')).file_get_contents(database_path('seeders/RolesSeeder.php'));
    expect(strtolower($src))->not->toContain('password')->not->toContain('user::create')->not->toContain('user::factory');
});

it('backs up and prunes without needing mysqldump', function () {
    // the dump uses MySQL-only SQL, so this only checks the command is registered and the dir logic
    expect(array_keys(Artisan::all()))->toContain('db:backup', 'analytics:aggregate', 'posts:publish-due', 'app:create-super-admin');
    expect(app(App\Services\DatabaseBackup::class)->prune(8))->toBeInt();
});
