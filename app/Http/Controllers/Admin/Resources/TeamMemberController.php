<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\TeamMember;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<TeamMember> */
class TeamMemberController extends ResourceController
{
    protected string $model = TeamMember::class;

    protected string $slug = 'team';

    protected string $title = 'Tim';

    protected string $singular = 'Anggota tim';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        return [
            F::text('name', 'Nama', ['required' => true]),
            F::t('role', 'Jabatan'),
            F::trich('bio', 'Profil singkat'),
            F::image('photo_id', 'Foto'),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'photo_id', 'label' => '', 'type' => 'image'],
            ['key' => 'name', 'label' => 'Nama', 'type' => 'text'],
            ['key' => 'role', 'label' => 'Jabatan', 'type' => 'tr'],
        ];
    }
}
