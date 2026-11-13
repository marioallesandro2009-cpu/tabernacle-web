<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Contact;
use App\Models\Outreach\ContactImport;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Suppression;
use Illuminate\Support\Str;

/** Turns an uploaded sheet into contacts using the column mapping the admin chose. */
class ContactImporter
{
    public const FIELDS = [
        'name' => 'Nama', 'title' => 'Jabatan', 'organization' => 'Organisasi', 'email' => 'Email',
        'phone' => 'Telepon', 'segment' => 'Segmen', 'notes' => 'Catatan',
    ];

    /** Header words that tell which column is which (compared with spaces and symbols removed). */
    protected const GUESS = [
        'name' => ['nama', 'name', 'fullname', 'namalengkap', 'contact', 'contactname', 'kontak', 'pic'],
        'title' => ['jabatan', 'title', 'position', 'role', 'jobtitle', 'posisi'],
        'organization' => ['organisasi', 'organization', 'organisation', 'company', 'perusahaan', 'instansi', 'institusi', 'institution', 'gereja', 'church', 'lembaga', 'namaperusahaan'],
        'email' => ['email', 'emailaddress', 'surel', 'mail', 'alamatemail', 'emailkantor'],
        'phone' => ['telepon', 'telp', 'phone', 'hp', 'nohp', 'whatsapp', 'wa', 'mobile', 'nomortelepon', 'notelp', 'handphone'],
        'segment' => ['segmen', 'segment', 'kategori', 'category', 'grup', 'group', 'kelompok'],
        'notes' => ['catatan', 'notes', 'note', 'keterangan', 'remark', 'remarks'],
    ];

    /** Second chance for headers such as "Nama Gereja": a word inside the header. The order matters ("name" last). */
    protected const CONTAINS = [
        'organization' => ['organisasi', 'organization', 'company', 'perusahaan', 'instansi', 'gereja', 'church', 'lembaga', 'institusi'],
        'email' => ['mail', 'surel'],
        'phone' => ['telp', 'telepon', 'phone', 'whatsapp', 'handphone'],
        'segment' => ['segmen', 'segment', 'kategori'],
        'title' => ['jabatan', 'position', 'posisi'],
        'notes' => ['catatan', 'keterangan', 'notes'],
        'name' => ['nama', 'name'],
    ];

    /** @var array<string, int> segment name (lowercase) => id */
    protected array $segments = [];

    public function __construct(protected SheetReader $reader = new SheetReader) {}

    /**
     * Best guess of the column for each field.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    public function guess(array $headers): array
    {
        $map = array_fill_keys(array_keys(self::FIELDS), null);
        foreach ($headers as $i => $h) {
            $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower($h));
            foreach (self::GUESS as $field => $words) {
                if ($map[$field] === null && in_array($key, $words, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        foreach (self::CONTAINS as $field => $words) {
            if ($map[$field] !== null) {
                continue;
            }
            foreach ($headers as $i => $h) {
                $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower($h));
                if (! in_array($i, $map, true) && collect($words)->contains(fn ($w) => str_contains($key, $w))) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /** Reads the whole file and creates the contacts. Updates the counters on $import as it goes. */
    public function run(ContactImport $import, string $absolutePath): void
    {
        $map = $import->mapping ?? [];
        $first = true;
        $seen = [];
        $this->reader->read($absolutePath, function (array $rows) use ($import, $map, &$first, &$seen) {
            if ($first) {
                array_shift($rows); // header row
                $first = false;
            }
            $this->chunk($import, $map, $rows, $seen);
        });
    }

    /**
     * @param  array<string, int|null>  $map
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, bool>  $seen  emails already met in this file
     */
    protected function chunk(ContactImport $import, array $map, array $rows, array &$seen): void
    {
        $get = fn (array $r, string $f) => isset($map[$f]) ? trim((string) ($r[(int) $map[$f]] ?? '')) : '';
        $emails = collect($rows)->map(fn ($r) => mb_strtolower($get($r, 'email')))->filter()->unique()->all();
        $existing = Contact::whereIn('email', $emails)->pluck('email')->flip()->all();
        $blocked = Suppression::whereIn('email', $emails)->pluck('reason', 'email')->all();
        $batch = [];
        $now = now();
        foreach ($rows as $r) {
            $data = [];
            foreach (array_keys(self::FIELDS) as $f) {
                $data[$f] = $get($r, $f);
            }
            if (implode('', $data) === '') {
                continue; // an entirely blank row
            }
            $import->total_rows++;
            $email = mb_strtolower($data['email']);
            if ($email !== '' && (isset($existing[$email]) || isset($seen[$email]))) {
                $import->duplicate_count++;

                continue;
            }
            if ($email !== '') {
                $seen[$email] = true;
            } else {
                $import->empty_count++;
            }
            $reason = $blocked[$email] ?? null;
            if ($reason) {
                $import->suppressed_count++;
            }
            $batch[] = [
                'name' => $data['name'] ?: null, 'title' => $data['title'] ?: null, 'organization' => $data['organization'] ?: null,
                'email' => $email ?: null, 'phone' => $data['phone'] ?: null,
                'segment_id' => $this->segmentId($data['segment'] !== '' ? $data['segment'] : null, $import->default_segment_id),
                'notes' => $data['notes'] ?: null,
                'validation_status' => $email === '' ? 'empty' : 'pending', 'validation_note' => $email === '' ? 'Email kosong' : null,
                'validated_at' => $email === '' ? $now : null,
                'suppressed_at' => $reason ? $now : null, 'suppressed_reason' => $reason,
                'unsub_token' => Str::random(40), 'import_id' => $import->id, 'created_at' => $now, 'updated_at' => $now,
            ];
            $import->created_count++;
        }
        foreach (array_chunk($batch, 200) as $part) {
            Contact::insert($part);
        }
        $import->save();
    }

    protected function segmentId(?string $name, ?int $default): ?int
    {
        if ($name === null) {
            return $default;
        }
        $key = mb_strtolower($name);

        return $this->segments[$key] ??= (int) (Segment::whereRaw('lower(name) = ?', [$key])->value('id') ?? Segment::create(['name' => mb_substr($name, 0, 120)])->id);
    }
}
