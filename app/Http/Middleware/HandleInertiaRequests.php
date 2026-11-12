<?php

namespace App\Http\Middleware;

use App\Models\Lead;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'admin';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => ['name' => (string) setting('site_name', config('app.name')), 'url' => url('/')],
            'auth' => $user ? [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'avatar' => $user->avatar_id ? media_url($user->avatar_id, 160) : null,
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $user->isSuperAdmin() ? array_keys(Permissions::ALL) : $user->getAllPermissions()->pluck('name')->all(),
                'two_factor' => $user->hasTwoFactor(),
            ] : null,
            'flash' => fn () => ['success' => $request->session()->get('success'), 'error' => $request->session()->get('error')],
            'nav' => fn () => $user ? $this->nav($user) : [],
            'newLeads' => fn () => $user ? Lead::where('status', 'baru')->where('type', 'form')->count() : 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function nav($user): array
    {
        $groups = [
            ['label' => null, 'items' => [['label' => 'Dashboard', 'url' => '/admin', 'can' => null, 'icon' => 'home']]],
            ['label' => 'Leads', 'items' => [
                ['label' => 'Leads', 'url' => '/admin/leads', 'can' => 'leads.manage', 'icon' => 'inbox', 'badge' => 'leads'],
                ['label' => 'Penerima notifikasi', 'url' => '/admin/recipients', 'can' => 'leads.manage', 'icon' => 'mail'],
            ]],
            ['label' => 'Paket tour', 'items' => [
                ['label' => 'Paket tour', 'url' => '/admin/tours', 'can' => 'tours.manage', 'icon' => 'plane'],
                ['label' => 'Destinasi', 'url' => '/admin/destinations', 'can' => 'tours.manage', 'icon' => 'map'],
                ['label' => 'Kategori paket', 'url' => '/admin/tour-categories', 'can' => 'tours.manage', 'icon' => 'tag'],
            ]],
            ['label' => 'Blog', 'items' => [
                ['label' => 'Artikel', 'url' => '/admin/posts', 'can' => 'blog.manage', 'icon' => 'doc'],
                ['label' => 'Kategori artikel', 'url' => '/admin/post-categories', 'can' => 'blog.manage', 'icon' => 'tag'],
                ['label' => 'Tag', 'url' => '/admin/tags', 'can' => 'blog.manage', 'icon' => 'tag'],
            ]],
            ['label' => 'Konten situs', 'items' => [
                ['label' => 'Halaman & section', 'url' => '/admin/pages', 'can' => 'content.manage', 'icon' => 'layout'],
                ['label' => 'Menu navigasi', 'url' => '/admin/menu-items', 'can' => 'content.manage', 'icon' => 'menu'],
                ['label' => 'Testimoni', 'url' => '/admin/testimonials', 'can' => 'content.manage', 'icon' => 'star'],
                ['label' => 'Galeri trip', 'url' => '/admin/galleries', 'can' => 'content.manage', 'icon' => 'image'],
                ['label' => 'FAQ', 'url' => '/admin/faqs', 'can' => 'content.manage', 'icon' => 'help'],
                ['label' => 'Tim', 'url' => '/admin/team', 'can' => 'content.manage', 'icon' => 'users'],
                ['label' => 'Teks antarmuka', 'url' => '/admin/ui-strings', 'can' => 'content.manage', 'icon' => 'text'],
                ['label' => 'Redirect', 'url' => '/admin/redirects', 'can' => 'content.manage', 'icon' => 'redirect'],
            ]],
            ['label' => 'Media', 'items' => [['label' => 'Media library', 'url' => '/admin/media', 'can' => 'media.manage', 'icon' => 'image']]],
            ['label' => 'Sistem', 'items' => [
                ['label' => 'Pengaturan situs', 'url' => '/admin/settings', 'can' => 'settings.manage', 'icon' => 'cog'],
                ['label' => 'Akun admin', 'url' => '/admin/users', 'can' => 'users.manage', 'icon' => 'users'],
                ['label' => 'Riwayat login', 'url' => '/admin/logins', 'can' => 'logs.view', 'icon' => 'users'],
                ['label' => 'Log aktivitas', 'url' => '/admin/activity', 'can' => 'logs.view', 'icon' => 'clock'],
                ['label' => 'Backup database', 'url' => '/admin/backup', 'can' => 'backup.manage', 'icon' => 'db'],
            ]],
        ];

        return collect($groups)->map(function ($g) use ($user) {
            $g['items'] = collect($g['items'])->filter(fn ($i) => $i['can'] === null || $user->can($i['can']))->values()->all();

            return $g;
        })->filter(fn ($g) => count($g['items']) > 0)->values()->all();
    }
}
