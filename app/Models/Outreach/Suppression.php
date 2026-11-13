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

    /**
     * Adds an address to the list, marks the matching contact, and stops every campaign that
     * still had emails to send to that person.
     */
    public static function add(string $email, string $reason = 'manual', ?string $note = null): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return;
        }
        static::firstOrCreate(['email' => $email], ['reason' => $reason, 'note' => $note, 'created_at' => now()]);
        Contact::where('email', $email)->whereNull('suppressed_at')->update(['suppressed_at' => now(), 'suppressed_reason' => $reason]);
        $ids = Contact::where('email', $email)->pluck('id');
        CampaignContact::whereIn('contact_id', $ids)->whereIn('status', ['queued', 'sent', 'auto_replied'])
            ->update(['status' => $reason === 'bounced' ? 'bounced' : 'unsubscribed', 'next_send_at' => null]);
    }
}
