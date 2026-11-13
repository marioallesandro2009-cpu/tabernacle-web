<?php

namespace App\Jobs\Outreach;

use App\Models\Outreach\Contact;
use App\Services\Outreach\EmailValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Checks up to 100 unchecked contacts, then queues itself again while more are waiting. */
class ValidateContactsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 1;

    public const BATCH = 100;

    public function handle(EmailValidator $validator): void
    {
        $contacts = Contact::where('validation_status', 'pending')->whereNull('validated_at')->orderBy('id')->limit(self::BATCH)->get();
        foreach ($contacts as $c) {
            [$status, $note] = $validator->validate($c->email);
            // "pending" here means the DNS lookup failed; it stays that way until a manual re-check
            $c->update(['validation_status' => $status, 'validation_note' => $note, 'validated_at' => now()]);
        }
        if ($contacts->count() === self::BATCH) {
            self::dispatch();
        }
    }
}
