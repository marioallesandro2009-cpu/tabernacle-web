<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    /** Manual entry for actions that are not a single model save. */
    public static function record(string $action, string $type, ?int $id, string $label, ?array $changes = null): void
    {
        try {
            self::create([
                'user_id' => auth()->id(), 'action' => $action, 'subject_type' => $type, 'subject_id' => $id,
                'subject_label' => mb_substr($label, 0, 250), 'changes' => $changes, 'ip' => request()->ip(), 'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
