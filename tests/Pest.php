<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

use App\Models\User;
use Database\Seeders\RolesSeeder;

/** Creates an active admin account with the given role. Passwords here are throwaway test values. */
function adminUser(string $role = 'super_admin', array $attrs = []): User
{
    test()->seed(RolesSeeder::class);
    $user = User::factory()->create(array_merge(['is_active' => true], $attrs));
    $user->assignRole($role);

    return $user;
}
