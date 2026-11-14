<?php

namespace App\Jobs\Outreach;

use App\Models\Outreach\Contact;
use App\Services\Outreach\EmailChecks;
use App\Services\Outreach\EmailValidator;
use App\Services\Outreach\Verification\VerifierFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Checks up to 100 unchecked contacts, then queues itself again while more are waiting. */
class ValidateContactsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 1;

    public const BATCH = 100;

    public function handle(EmailValidator $validator, EmailChecks $checks, VerifierFactory $factory): void
    {
        $contacts = Contact::where('validation_status', 'pending')->whereNull('validated_at')->orderBy('id')->limit(self::BATCH)->get();
        foreach ($contacts as $c) {
            [$status, $note] = $validator->validate($c->email);
            // "pending" here means the DNS lookup failed; it stays that way until a manual re-check
            $c->update(['validation_status' => $status, 'validation_note' => $note, 'validated_at' => now(), 'is_role' => $checks->isRole($c->email)]);
        }
        // "verify on import" in Pengaturan: the valid ones also go through the verification service
        $valid = $contacts->filter(fn ($c) => $c->validation_status === 'valid' && $c->verify_status === null)->pluck('id')->all();
        if ($valid && setting('verify_on_import', false) && $factory->make()) {
            VerifyContactsJob::dispatch($valid);
        }
        if ($contacts->count() === self::BATCH) {
            self::dispatch();
        }
    }
}
