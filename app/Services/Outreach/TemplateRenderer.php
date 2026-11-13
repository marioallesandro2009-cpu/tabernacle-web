<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Account;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Template;

/** Fills {variables} and adds the footer (company details and the unsubscribe link) to every email. */
class TemplateRenderer
{
    /** @return array{0: string, 1: string} [subject, body with footer] */
    public function render(string $subject, string $body, Contact $contact, ?Account $account): array
    {
        $subject = trim(preg_replace('/\s+/', ' ', $this->fill($subject, $contact, $account)) ?? '');

        return [$subject, rtrim($this->fill($body, $contact, $account)).$this->footer($contact)];
    }

    /** Replaces {nama}, {jabatan}, {organisasi}, {nama_pengirim}. An unknown word becomes empty. */
    public function fill(string $text, Contact $contact, ?Account $account): string
    {
        $values = [
            'nama' => $contact->name ?: 'Bapak/Ibu',
            'jabatan' => (string) $contact->title,
            'organisasi' => (string) $contact->organization,
            'nama_pengirim' => $account ? $account->name : (string) setting('site_name', config('app.name')),
        ];

        return preg_replace_callback('/\{(\w+)\}/', fn (array $m) => $values[$m[1]] ?? '', $text) ?? $text;
    }

    /** Names used in {} that this renderer does not know. @return array<int, string> */
    public function unknownVariables(string ...$texts): array
    {
        preg_match_all('/\{(\w+)\}/', implode("\n", $texts), $m);

        return array_values(array_unique(array_diff($m[1], array_keys(Template::VARIABLES))));
    }

    public function unsubscribeUrl(Contact $contact): string
    {
        return url('/u/'.$contact->unsub_token);
    }

    public function footer(Contact $contact): string
    {
        $address = trim(preg_replace('/\s*\R\s*/', ', ', (string) setting('address_footer', '')) ?? '');
        $contactLine = collect([config('mail.from.address') ?: setting('contact_email'), setting('wa_display')])->filter()->implode('  |  ');
        $lines = ['', '', '--', (string) (setting('company_name') ?: setting('site_name', config('app.name')))];
        foreach ([$address, $contactLine, url('/')] as $line) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        array_push($lines, '', 'Tidak ingin menerima email seperti ini lagi? Berhenti berlangganan di sini:', $this->unsubscribeUrl($contact));

        return implode("\n", $lines);
    }
}
