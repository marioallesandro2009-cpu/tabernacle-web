<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A plain-text message with {variables}. Tied to a segment so each group can get its own wording. */
class Template extends Model
{
    protected $table = 'outreach_templates';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    /** Words that may be used inside { } in a subject or body. */
    public const VARIABLES = [
        'nama' => 'Nama kontak (kosong: "Bapak/Ibu")',
        'jabatan' => 'Jabatan',
        'organisasi' => 'Organisasi',
        'nama_pengirim' => 'Nama pengirim (dari akun email)',
    ];

    /** @return BelongsTo<Segment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }
}
