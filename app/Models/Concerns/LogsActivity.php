<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/** Records who created, changed or deleted a record, and what changed. */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => $m->logActivity('created', []));
        static::updated(function ($m) {
            $changes = collect($m->getChanges())->except(['updated_at', 'views', 'remember_token', 'password', 'last_login_at', 'two_factor_secret'])
                ->map(fn ($new, $key) => ['from' => static::summary($m->getOriginal($key)), 'to' => static::summary($new)])->all();
            if ($changes) {
                $m->logActivity('updated', $changes);
            }
        });
        static::deleted(fn ($m) => $m->logActivity('deleted', []));
    }

    protected static function summary(mixed $v): mixed
    {
        if (is_array($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $v = (string) $v;

        return mb_strlen($v) > 160 ? mb_substr($v, 0, 160).'…' : $v;
    }

    /** @param array<string, mixed> $changes */
    public function logActivity(string $action, array $changes): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests() && ! auth()->check()) {
            return;
        }
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => class_basename($this),
                'subject_id' => $this->getKey(),
                'subject_label' => $this->activityLabel(),
                'changes' => $changes ?: null,
                ...ActivityLog::client(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // logging must never break a save
        }
    }

    public function activityLabel(): string
    {
        foreach (['name', 'title', 'key', 'email', 'label', 'from_path'] as $f) {
            $v = $this->getAttribute($f);
            if ($v) {
                return mb_substr(is_array($v) ? tr($v) : (string) $v, 0, 120);
            }
        }

        return '#'.$this->getKey();
    }
}
