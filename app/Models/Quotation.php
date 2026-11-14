<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** A price quotation (penawaran harga) sent to a client. */
class Quotation extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array', 'issued_on' => 'date', 'valid_until' => 'date', 'travel_date' => 'date', 'sent_at' => 'datetime',
        'discount' => 'float', 'tax_percent' => 'float', 'revision' => 'integer', 'pax' => 'integer',
    ];

    public const STATUSES = ['draft' => 'Draf', 'sent' => 'Terkirim', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];

    public const CURRENCIES = ['IDR' => 'Rupiah (Rp)', 'USD' => 'Dolar AS (US$)'];

    /** The fields that make up the document (kept in a revision snapshot). */
    public const DOCUMENT_FIELDS = [
        'number', 'revision', 'tour_id', 'currency', 'issued_on', 'valid_until', 'client_name', 'client_company', 'client_email', 'client_phone',
        'client_address', 'title', 'travel_date', 'pax', 'items', 'discount', 'tax_percent', 'tax_label', 'notes', 'terms', 'payment_info',
        'signatory_name', 'signatory_title',
    ];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuotationRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuotationRevision::class)->orderByDesc('revision');
    }

    /** "TW/QUO/2026/11/001", plus " Rev. 2" once it has been revised. */
    public function displayNumber(): string
    {
        return $this->number.($this->revision > 0 ? ' Rev. '.$this->revision : '');
    }

    /** The next free number for the month of $on: PREFIX/YYYY/MM/001, 002, … */
    public static function nextNumber(?Carbon $on = null): string
    {
        $on ??= now()->timezone('Asia/Jakarta');
        $prefix = trim((string) setting('quote_prefix', 'TW/QUO'), '/ ') ?: 'TW/QUO';
        $base = $prefix.'/'.$on->format('Y').'/'.$on->format('m').'/';
        $last = static::where('number', 'like', $base.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($base)))->max() ?? 0;
        $n = $last + 1;
        while (static::where('number', $base.str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $base.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Subtotal, discount, tax and total from the items.
     *
     * @param  array<string, mixed>|null  $data  a snapshot instead of this model
     * @return array{lines: array<int, array{description: string, qty: float, unit: string, price: float, amount: float}>, subtotal: float, discount: float, after_discount: float, tax_percent: float, tax: float, total: float}
     */
    public function totals(?array $data = null): array
    {
        return self::compute($data['items'] ?? $this->items ?? [], (float) ($data['discount'] ?? $this->discount), (float) ($data['tax_percent'] ?? $this->tax_percent), $data['currency'] ?? $this->currency);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{lines: array<int, array{description: string, qty: float, unit: string, price: float, amount: float}>, subtotal: float, discount: float, after_discount: float, tax_percent: float, tax: float, total: float}
     */
    public static function compute(array $items, float $discount, float $taxPercent, string $currency = 'IDR'): array
    {
        $round = fn (float $v) => $currency === 'IDR' ? round($v) : round($v, 2);
        $lines = [];
        foreach ($items as $it) {
            $qty = (float) ($it['qty'] ?? 0);
            $price = (float) ($it['price'] ?? 0);
            $lines[] = ['description' => (string) ($it['description'] ?? ''), 'qty' => $qty, 'unit' => (string) ($it['unit'] ?? ''), 'price' => $price, 'amount' => $round($qty * $price)];
        }
        $subtotal = $round(array_sum(array_column($lines, 'amount')));
        $discount = min($subtotal, max(0, $round($discount)));
        $after = $subtotal - $discount;
        $tax = $round($after * $taxPercent / 100);

        return ['lines' => $lines, 'subtotal' => $subtotal, 'discount' => $discount, 'after_discount' => $after, 'tax_percent' => $taxPercent, 'tax' => $tax, 'total' => $after + $tax];
    }

    /** Rp 12.500.000 or US$ 1,250.00 */
    public static function money(float $v, string $currency = 'IDR'): string
    {
        $sign = $v < 0 ? '− ' : '';
        $v = abs($v);

        return $sign.($currency === 'USD' ? 'US$ '.number_format($v, 2, '.', ',') : 'Rp '.number_format($v, 0, ',', '.'));
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $out = [];
        foreach (self::DOCUMENT_FIELDS as $f) {
            $v = $this->getAttribute($f);
            $out[$f] = $v instanceof Carbon ? $v->toDateString() : $v;
        }

        return $out;
    }
}
