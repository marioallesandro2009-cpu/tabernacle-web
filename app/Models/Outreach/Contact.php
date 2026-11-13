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

    protected $casts = ['validated_at' => 'datetime', 'suppressed_at' => 'datetime'];

    public const STATUSES = ['pending', 'valid', 'empty', 'invalid_format', 'no_mx'];

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

    /** Contacts that may be emailed: a valid address that has not opted out or bounced. @param Builder<Contact> $q */
    public function scopeSendable(Builder $q): void
    {
        $q->where('validation_status', 'valid')->whereNotNull('email')->whereNull('suppressed_at');
    }

    public function isSendable(): bool
    {
        return $this->validation_status === 'valid' && $this->email !== null && $this->suppressed_at === null;
    }
}
