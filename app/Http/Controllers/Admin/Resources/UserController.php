<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\User;
use App\Support\F;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin accounts. Only super admins can open this. */
/** @extends ResourceController<User> */
class UserController extends ResourceController
{
    protected string $model = User::class;

    protected string $slug = 'users';

    protected string $title = 'Akun admin';

    protected string $singular = 'Akun';

    protected array $with = ['roles'];

    protected array $search = ['name', 'email'];

    protected function fields(?Model $model = null): array
    {
        $roles = collect(Permissions::LABELS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();

        return [
            F::text('name', 'Nama lengkap', ['required' => true]),
            F::email('email', 'Email login', ['required' => true]),
            F::select('role', 'Peran', $roles, ['required' => true, 'default' => 'admin']),
            F::text('password', $model ? 'Password baru (kosongkan jika tidak diubah)' : 'Password (minimal 10 karakter)', ['input' => 'password', 'required' => ! $model, 'max' => 100]),
            F::toggle('is_active', 'Akun aktif', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nama', 'type' => 'text'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
            ['key' => 'role', 'label' => 'Peran', 'type' => 'badge', 'get' => fn (User $u) => Permissions::LABELS[$u->getRoleNames()->first()] ?? '–'],
            ['key' => 'two_factor', 'label' => '2FA', 'type' => 'bool', 'get' => fn (User $u) => $u->hasTwoFactor()],
            ['key' => 'last_login_at', 'label' => 'Terakhir masuk', 'type' => 'date'],
        ];
    }

    protected function valuesOf(?Model $model, array $fields): array
    {
        $v = parent::valuesOf($model, $fields);
        if ($model) {
            $v['role'] = $model->getRoleNames()->first();
            $v['password'] = '';
        }

        return $v;
    }

    protected function extraRules(?Model $model, Request $request): array
    {
        return [
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($model?->getKey())],
            'password' => [$model ? 'nullable' : 'required', 'string', 'min:10', 'max:100'],
        ];
    }

    protected function beforeSave(array &$data, ?Model $model, Request $request): void
    {
        $this->role = $data['role'];
        unset($data['role']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($model) {
            // never leave the system without an active super admin
            if (($this->role !== 'super_admin' || ! ($data['is_active'] ?? true)) && $this->isLastSuperAdmin($model)) {
                throw ValidationException::withMessages(['role' => 'Minimal harus ada satu super admin yang aktif.']);
            }
            if ($model->id === $request->user()->id && ! ($data['is_active'] ?? true)) {
                throw ValidationException::withMessages(['is_active' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.']);
            }
        }
    }

    protected ?string $role = null;

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var User $model */
        if ($this->role) {
            $model->syncRoles([$this->role]);
        }
        if (! $model->email_verified_at) {
            $model->forceFill(['email_verified_at' => now()])->saveQuietly();
        }
    }

    protected function isLastSuperAdmin(Model $user): bool
    {
        /** @var User $user */
        return $user->isSuperAdmin() && User::role('super_admin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }

    protected function blockDelete(Model $model): ?string
    {
        if ($model->getKey() === auth()->id()) {
            return 'Anda tidak bisa menghapus akun Anda sendiri.';
        }

        return $this->isLastSuperAdmin($model) ? 'Ini satu-satunya super admin aktif, tidak bisa dihapus.' : null;
    }

    protected function blockDeactivate(Model $model): ?string
    {
        if ($model->getKey() === auth()->id()) {
            return 'Anda tidak bisa menonaktifkan akun Anda sendiri.';
        }

        return $this->isLastSuperAdmin($model) ? 'Minimal harus ada satu super admin yang aktif.' : null;
    }

    /** Send a reset link to the account's email. */
    public function resetLink(string $id): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail($id);
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', 'Tautan reset password dikirim ke '.$user->email.'.');
    }
}
