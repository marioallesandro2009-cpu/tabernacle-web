<?php

namespace App\Exports;

use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One row per person in a campaign: what was sent, and how they responded. */
class CampaignResultsSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public const STATUS = [
        'queued' => 'Antre', 'sent' => 'Terkirim', 'replied' => 'Dibalas', 'bounced' => 'Bounce', 'auto_replied' => 'Balasan otomatis',
        'unsubscribed' => 'Berhenti langganan', 'invalid' => 'Tidak valid', 'failed' => 'Gagal kirim',
    ];

    public const LABEL = ['interested' => 'Tertarik', 'not_now' => 'Nanti dulu', 'not_interested' => 'Tidak tertarik'];

    public function __construct(protected Campaign $campaign) {}

    public function title(): string
    {
        return 'Hasil kampanye';
    }

    public function array(): array
    {
        $id = $this->campaign->id;
        $sent = Message::where('campaign_id', $id)->where('direction', 'out')->where('status', 'sent')
            ->selectRaw('campaign_contact_id, count(*) as n')->groupBy('campaign_contact_id')->pluck('n', 'campaign_contact_id');
        $replies = Message::where('campaign_id', $id)->where('direction', 'in')->where('kind', 'reply')->orderBy('sent_at')->get()
            ->groupBy('campaign_contact_id')->map(fn ($g) => $g->last());
        $ab = $this->campaign->subject_b !== null && $this->campaign->subject_b !== '';
        $tz = Campaign::TIMEZONE;

        $head = ['Nama', 'Jabatan', 'Organisasi', 'Email', 'Segmen', 'Akun pengirim'];
        if ($ab) {
            $head[] = 'Versi subjek';
        }
        $rows = [array_merge($head, ['Email terkirim', 'Pertama dikirim', 'Terakhir dikirim', 'Status', 'Dibalas pada', 'Label balasan', 'Isi balasan'])];

        CampaignContact::where('campaign_id', $id)->with(['contact.segment:id,name', 'account:id,email'])->orderBy('id')
            ->chunk(500, function ($chunk) use (&$rows, $sent, $replies, $ab, $tz) {
                foreach ($chunk as $cc) {
                    $c = $cc->contact;
                    $r = $replies->get($cc->id);
                    $row = [$this->safe($c?->name), $this->safe($c?->title), $this->safe($c?->organization), $c?->email, $c?->segment?->name, $cc->account?->email];
                    if ($ab) {
                        $row[] = $cc->variant;
                    }
                    $rows[] = array_merge($row, [
                        (int) ($sent[$cc->id] ?? 0),
                        $cc->first_sent_at?->timezone($tz)->format('Y-m-d H:i'),
                        $cc->last_sent_at?->timezone($tz)->format('Y-m-d H:i'),
                        self::STATUS[$cc->status] ?? $cc->status,
                        $r?->sent_at?->timezone($tz)->format('Y-m-d H:i'),
                        $r ? (self::LABEL[$r->label] ?? '') : '',
                        $r ? $this->safe(mb_substr(trim(preg_replace('/\s+/', ' ', (string) $r->body) ?? ''), 0, 500)) : '',
                    ]);
                }
            });

        return $rows;
    }

    /** Stops spreadsheet formula injection from text people typed. */
    protected function safe(?string $v): ?string
    {
        return ($v !== null && $v !== '' && str_contains('=+-@', $v[0])) ? "'".$v : $v;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
