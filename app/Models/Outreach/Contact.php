<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Contact extends Model
{
    protected $table = 'outreach_contacts';

    protected $guarded = ['id'];

    protected $casts = ['validated_at' => 'datetime', 'suppressed_at' => 'datetime', 'verified_at' => 'datetime', 'is_role' => 'boolean'];

    public const STATUSES = ['pending', 'valid', 'empty', 'invalid_format', 'disposable', 'no_mx'];

    protected static function booted(): void
    {
        static::creating(function (Contact $c) {
            $c->unsub_token ??= Str::random(40);
        });
    }

    public function setEmailAttribute(?string $value): void
    {
        $value = $value === null ? '' : mb_strtolower(trim($value));
        $this->attributes['email'] = $value === '' ? null : $value;
    }

    /** @return BelongsTo<Segment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    /**
     * Contacts that may be emailed: a valid address that has not opted out or bounced and, when a
     * verification service checked it, came back "valid" (or "catch_all" if the campaign allows it).
     *
     * @param  Builder<Contact>  $q
     */
    public function scopeSendable(Builder $q, bool $allowCatchAll = false): void
    {
        $q->where('validation_status', 'valid')->whereNotNull('email')->whereNull('suppressed_at')
            ->where(fn ($w) => $w->whereNull('verify_status')->orWhereIn('verify_status', $allowCatchAll ? ['valid', 'catch_all'] : ['valid']));
    }

    public function isSendable(bool $allowCatchAll = false): bool
    {
        return $this->validation_status === 'valid' && $this->email !== null && $this->suppressed_at === null
            && ($this->verify_status === null || $this->verify_status === 'valid' || ($allowCatchAll && $this->verify_status === 'catch_all'));
    }
}
