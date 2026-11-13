<?php

namespace App\Support;

/** Role and permission map. super_admin passes every check (see AppServiceProvider). */
class Permissions
{
    public const ALL = [
        'content.manage' => 'Kelola konten (halaman, menu, destinasi, FAQ, testimoni, galeri, tim, teks antarmuka, redirect)',
        'tours.manage' => 'Kelola paket tour',
        'blog.manage' => 'Kelola blog',
        'media.manage' => 'Kelola media library',
        'leads.manage' => 'Kelola leads dan penerima notifikasi',
        'leads.export' => 'Ekspor laporan leads',
        'analytics.view' => 'Lihat analitik pengunjung',
        'users.manage' => 'Kelola akun admin',
        'settings.manage' => 'Kelola pengaturan situs dan sistem',
        'logs.view' => 'Lihat log aktivitas',
        'backup.manage' => 'Backup database',
        'outreach.manage' => 'Kelola Outreach (kampanye email B2B: akun pengirim, kontak, template, kampanye, balasan)',
    ];

    public const ROLES = [
        'super_admin' => ['*'],
        'admin' => ['content.manage', 'tours.manage', 'blog.manage', 'media.manage', 'leads.manage', 'leads.export', 'analytics.view', 'outreach.manage'],
        'editor' => ['blog.manage', 'media.manage'],
    ];

    public const LABELS = ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'editor' => 'Editor'];
}
