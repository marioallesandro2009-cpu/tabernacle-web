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
            ['label' => null, 'items' => [['label' => 'Dashboard', 'url' => admin_path(), 'can' => null, 'icon' => 'home']]],
            ['label' => 'Leads', 'items' => [
                ['label' => 'Leads', 'url' => admin_path().'/leads', 'can' => 'leads.manage', 'icon' => 'inbox', 'badge' => 'leads'],
                ['label' => 'Penawaran harga', 'url' => admin_path().'/quotations', 'can' => 'leads.manage', 'icon' => 'doc'],
                ['label' => 'Penerima notifikasi', 'url' => admin_path().'/recipients', 'can' => 'leads.manage', 'icon' => 'mail'],
            ]],
            ['label' => 'Outreach', 'items' => [
                ['label' => 'Kontak', 'url' => admin_path().'/outreach/contacts', 'can' => 'outreach.manage', 'icon' => 'users'],
                ['label' => 'Segmen', 'url' => admin_path().'/outreach/segments', 'can' => 'outreach.manage', 'icon' => 'tag'],
                ['label' => 'Balasan', 'url' => admin_path().'/outreach/replies', 'can' => 'outreach.manage', 'icon' => 'inbox'],
                ['label' => 'Template email', 'url' => admin_path().'/outreach/templates', 'can' => 'outreach.manage', 'icon' => 'text'],
                ['label' => 'Kampanye', 'url' => admin_path().'/outreach/campaigns', 'can' => 'outreach.manage', 'icon' => 'plane'],
                ['label' => 'Akun pengirim', 'url' => admin_path().'/outreach/accounts', 'can' => 'outreach.manage', 'icon' => 'mail'],
                ['label' => 'Daftar blokir', 'url' => admin_path().'/outreach/blocklist', 'can' => 'outreach.manage', 'icon' => 'x'],
            ]],
            ['label' => 'Paket tour', 'items' => [
                ['label' => 'Paket tour', 'url' => admin_path().'/tours', 'can' => 'tours.manage', 'icon' => 'plane'],
                ['label' => 'Destinasi', 'url' => admin_path().'/destinations', 'can' => 'tours.manage', 'icon' => 'map'],
                ['label' => 'Kategori paket', 'url' => admin_path().'/tour-categories', 'can' => 'tours.manage', 'icon' => 'tag'],
            ]],
            ['label' => 'Blog', 'items' => [
                ['label' => 'Artikel', 'url' => admin_path().'/posts', 'can' => 'blog.manage', 'icon' => 'doc'],
                ['label' => 'Kategori artikel', 'url' => admin_path().'/post-categories', 'can' => 'blog.manage', 'icon' => 'tag'],
                ['label' => 'Tag', 'url' => admin_path().'/tags', 'can' => 'blog.manage', 'icon' => 'tag'],
            ]],
            ['label' => 'Konten situs', 'items' => [
                ['label' => 'Halaman & section', 'url' => admin_path().'/pages', 'can' => 'content.manage', 'icon' => 'layout'],
                ['label' => 'Menu navigasi', 'url' => admin_path().'/menu-items', 'can' => 'content.manage', 'icon' => 'menu'],
                ['label' => 'Testimoni', 'url' => admin_path().'/testimonials', 'can' => 'content.manage', 'icon' => 'star'],
                ['label' => 'Galeri trip', 'url' => admin_path().'/galleries', 'can' => 'content.manage', 'icon' => 'image'],
                ['label' => 'FAQ', 'url' => admin_path().'/faqs', 'can' => 'content.manage', 'icon' => 'help'],
                ['label' => 'Tim', 'url' => admin_path().'/team', 'can' => 'content.manage', 'icon' => 'users'],
                ['label' => 'Teks antarmuka', 'url' => admin_path().'/ui-strings', 'can' => 'content.manage', 'icon' => 'text'],
                ['label' => 'Terjemahan belum lengkap', 'url' => admin_path().'/translations', 'can' => 'content.manage', 'icon' => 'help'],
                ['label' => 'Redirect', 'url' => admin_path().'/redirects', 'can' => 'content.manage', 'icon' => 'redirect'],
            ]],
            ['label' => 'Media', 'items' => [['label' => 'Media library', 'url' => admin_path().'/media', 'can' => 'media.manage', 'icon' => 'image']]],
            ['label' => 'Sistem', 'items' => [
                ['label' => 'Pengaturan situs', 'url' => admin_path().'/settings', 'can' => 'settings.manage', 'icon' => 'cog'],
                ['label' => 'Akun admin', 'url' => admin_path().'/users', 'can' => 'users.manage', 'icon' => 'users'],
                ['label' => 'Riwayat login', 'url' => admin_path().'/logins', 'can' => 'logs.view', 'icon' => 'users'],
                ['label' => 'Log aktivitas', 'url' => admin_path().'/activity', 'can' => 'logs.view', 'icon' => 'clock'],
                ['label' => 'Backup database', 'url' => admin_path().'/backup', 'can' => 'backup.manage', 'icon' => 'db'],
            ]],
        ];

        return collect($groups)->map(function ($g) use ($user) {
            $g['items'] = collect($g['items'])->filter(fn ($i) => $i['can'] === null || $user->can($i['can']))->values()->all();

            return $g;
        })->filter(fn ($g) => count($g['items']) > 0)->values()->all();
    }
}
