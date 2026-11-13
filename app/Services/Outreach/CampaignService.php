<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use DomainException;

class CampaignService
{
    /** Contacts that already count as invalid when a campaign starts. */
    public const BAD = ['invalid_format', 'no_mx', 'empty'];

    /**
     * Puts the campaign's contacts in line and starts it. Contacts added to the segment later
     * are picked up if the campaign is started again after a pause.
     *
     * @throws DomainException with a message meant for the admin
     */
    public function start(Campaign $c): void
    {
        $problem = $this->problem($c);
        if ($problem) {
            throw new DomainException($problem);
        }
        Contact::query()->when($c->segment_id, fn ($q) => $q->where('segment_id', $c->segment_id))
            ->select(['id', 'email', 'validation_status', 'suppressed_at', 'suppressed_reason'])
            ->chunkById(500, function ($contacts) use ($c) {
                $have = CampaignContact::where('campaign_id', $c->id)->whereIn('contact_id', $contacts->pluck('id'))->pluck('contact_id')->flip();
                $rows = [];
                foreach ($contacts as $contact) {
                    if (isset($have[$contact->id])) {
                        continue;
                    }
                    $rows[] = [
                        'campaign_id' => $c->id, 'contact_id' => $contact->id, 'status' => $this->initialStatus($contact),
                        'current_step' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
                if ($rows) {
                    CampaignContact::insert($rows);
                }
            });
        $c->update(['status' => 'running', 'started_at' => $c->started_at ?? now(), 'completed_at' => null]);
    }

    public function pause(Campaign $c): void
    {
        if ($c->status === 'running') {
            $c->update(['status' => 'paused']);
        }
    }

    public function resume(Campaign $c): void
    {
        $this->start($c); // also picks up contacts added since, and re-checks the setup
    }

    /** Why this campaign cannot start, or null when it can. */
    public function problem(Campaign $c): ?string
    {
        if (! $c->steps()->whereNotNull('template_id')->exists()) {
            return 'Pilih template untuk langkah 1 dulu.';
        }
        if (! $c->accounts()->where('is_active', true)->exists()) {
            return 'Pilih minimal satu akun pengirim yang aktif.';
        }
        if (empty($c->send_days)) {
            return 'Pilih minimal satu hari kirim.';
        }
        if ($c->window_start >= $c->window_end) {
            return 'Jam mulai harus lebih awal dari jam selesai.';
        }

        return null;
    }

    protected function initialStatus(Contact $contact): string
    {
        if ($contact->suppressed_at) {
            return $contact->suppressed_reason === 'bounced' ? 'bounced' : 'unsubscribed';
        }
        if ($contact->email === null || in_array($contact->validation_status, self::BAD, true)) {
            return 'invalid';
        }

        return 'queued'; // still "pending" addresses wait here until they are checked
    }

    /**
     * The numbers shown on the campaign page.
     *
     * @return array<string, int|float>
     */
    public function stats(Campaign $c): array
    {
        $by = CampaignContact::where('campaign_id', $c->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $total = (int) $by->sum();
        $started = CampaignContact::where('campaign_id', $c->id)->whereNotNull('first_sent_at')->count();
        $replied = (int) ($by['replied'] ?? 0);

        return [
            'contacts' => $total, 'queued' => (int) ($by['queued'] ?? 0), 'started' => $started,
            'sent_messages' => Message::where('campaign_id', $c->id)->where('direction', 'out')->where('status', 'sent')->count(),
            'replied' => $replied, 'bounced' => (int) ($by['bounced'] ?? 0), 'auto_replied' => (int) ($by['auto_replied'] ?? 0),
            'unsubscribed' => (int) ($by['unsubscribed'] ?? 0), 'invalid' => (int) ($by['invalid'] ?? 0), 'failed' => (int) ($by['failed'] ?? 0),
            'reply_rate' => $started ? round($replied / $started * 100, 1) : 0,
        ];
    }
}
