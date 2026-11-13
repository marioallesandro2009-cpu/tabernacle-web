<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;

/** The do-not-contact list. It applies to every campaign and survives deleting a contact. */
class Suppression extends Model
{
    public $timestamps = false;

    protected $table = 'outreach_suppressions';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    /** Adds an address to the list and marks the matching contact. */
    public static function add(string $email, string $reason = 'manual', ?string $note = null): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return;
        }
        static::firstOrCreate(['email' => $email], ['reason' => $reason, 'note' => $note, 'created_at' => now()]);
        Contact::where('email', $email)->whereNull('suppressed_at')->update(['suppressed_at' => now(), 'suppressed_reason' => $reason]);
    }
}
