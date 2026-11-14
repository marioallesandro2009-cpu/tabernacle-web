<?php

namespace App\Jobs\Outreach;

use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\EmailChecks;
use App\Services\Outreach\EmailValidator;
use App\Services\Outreach\Verification\VerificationUnavailable;
use App\Services\Outreach\Verification\VerifierFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * "Verifikasi ulang": checks the given contacts again (format, domain, MX, throwaway service, role
 * address) and, when a verification service is set in Pengaturan, asks it too. Works in batches of
 * 50 so a whole segment never blocks the queue. No SMTP probing is ever done from our server.
 */
class VerifyContactsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 280;

    public int $tries = 1;

    public const BATCH = 50;

    /** @param array<int, int> $ids */
    public function __construct(public array $ids, public bool $useService = true) {}

    public function handle(EmailValidator $validator, EmailChecks $checks, VerifierFactory $factory): void
    {
        $now = array_slice($this->ids, 0, self::BATCH);
        $rest = array_slice($this->ids, self::BATCH);
        $verifier = $this->useService ? $factory->make() : null;
        $serviceDown = null;
        foreach (Contact::whereIn('id', $now)->get() as $c) {
            [$status, $note] = $validator->validate($c->email);
            $update = ['validation_status' => $status, 'validation_note' => $note, 'validated_at' => now(), 'is_role' => $checks->isRole($c->email)];
            if ($status === 'valid' && $verifier && ! $serviceDown) {
                try {
                    $r = $verifier->verify((string) $c->email);
                    $update += ['verify_status' => $r['status'], 'verify_note' => mb_substr((string) $r['note'], 0, 255), 'verify_provider' => $verifier->name(), 'verified_at' => now()];
                } catch (VerificationUnavailable $e) {
                    $serviceDown = $e->getMessage(); // no credits / wrong key: stop asking, keep the basic result
                }
            } elseif ($status === 'disposable') {
                $update += ['verify_status' => 'disposable', 'verify_note' => $note, 'verify_provider' => 'lokal', 'verified_at' => now()];
            }
            $c->update($update);
            // a contact found unusable stops the campaigns still waiting to write to them
            if (in_array($c->verify_status, CampaignService::VERIFY_BAD, true) || in_array($status, CampaignService::BAD, true)) {
                CampaignContact::where('contact_id', $c->id)->whereIn('status', ['queued', 'sent', 'auto_replied'])->update(['status' => 'invalid', 'next_send_at' => null]);
            }
        }
        if ($serviceDown) {
            Cache::put('outreach:verify-error', $serviceDown, now()->addDay());
        }
        if ($rest) {
            self::dispatch($rest, $this->useService);
        }
    }
}
