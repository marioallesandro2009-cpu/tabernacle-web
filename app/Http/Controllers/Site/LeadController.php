<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Jobs\SendLeadNotification;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Tour;
use App\Support\Analytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    /** Indonesian mobile numbers, accepted as 08..., +628..., 628... or 8... and stored as 628... */
    public static function normalizePhone(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0')) {
            $d = '62'.substr($d, 1);
        } elseif (str_starts_with($d, '8')) {
            $d = '62'.$d;
        }

        return preg_match('/^628[1-9][0-9]{7,11}$/', $d) ? $d : null;
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'tour_id' => ['nullable', 'integer', Rule::exists('tours', 'id')],
            'pax' => ['nullable', 'integer', 'min:1', 'max:500'],
            'plan_date' => ['nullable', 'date', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:2000'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'], 'utm_medium' => ['nullable', 'string', 'max:100'], 'utm_campaign' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string'],
        ]);

        // honeypot: a person never fills this hidden field. Pretend it worked so bots learn nothing.
        if (! empty($data['website'])) {
            return response()->json(['ok' => true, 'message' => t('lead.success')]);
        }
        $phone = self::normalizePhone($data['phone']);
        if (! $phone) {
            return response()->json(['message' => t('lead.phone_invalid'), 'errors' => ['phone' => [t('lead.phone_invalid')]]], 422);
        }

        $tour = ! empty($data['tour_id']) ? Tour::find($data['tour_id']) : null;
        $host = ! empty($data['referrer']) ? strtolower((string) parse_url($data['referrer'], PHP_URL_HOST)) : Analytics::referrerHost($request);
        if ($host === strtolower($request->getHost())) {
            $host = null;
        }

        $lead = Lead::create([
            'type' => 'form', 'name' => strip_tags($data['name']), 'phone' => $phone, 'email' => $data['email'] ?? null,
            'tour_id' => $tour?->id, 'tour_name' => $tour ? trim($tour->duration.' '.$tour->name) : null,
            'pax' => $data['pax'] ?? null, 'plan_date' => $data['plan_date'] ?? null,
            'message' => isset($data['message']) ? strip_tags($data['message']) : null, 'status' => 'baru',
            'page_url' => $data['page_url'] ?? $request->headers->get('referer'), 'referrer' => $data['referrer'] ?? null,
            'source' => Analytics::source($host, $data['utm_source'] ?? null),
            'utm_source' => $data['utm_source'] ?? null, 'utm_medium' => $data['utm_medium'] ?? null, 'utm_campaign' => $data['utm_campaign'] ?? null,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
        LeadEvent::create(['lead_id' => $lead->id, 'event' => 'created', 'to_value' => 'baru']);

        if (setting('lead_notify_enabled', true)) {
            SendLeadNotification::dispatch($lead->id);
        }

        return response()->json(['ok' => true, 'message' => t('lead.success')]);
    }

    /** A tap on any WhatsApp button, recorded as a lead of type wa_click. */
    public function waClick(Request $request): Response
    {
        if (auth()->check() || Analytics::isBot($request->userAgent())) {
            return response()->noContent();
        }
        $data = $request->validate([
            'tour_id' => ['nullable', 'integer'], 'page_url' => ['nullable', 'string', 'max:500'], 'label' => ['nullable', 'string', 'max:120'],
            'referrer' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'], 'utm_medium' => ['nullable', 'string', 'max:100'], 'utm_campaign' => ['nullable', 'string', 'max:100'],
        ]);
        $tour = ! empty($data['tour_id']) ? Tour::find($data['tour_id']) : null;
        $host = ! empty($data['referrer']) ? strtolower((string) parse_url($data['referrer'], PHP_URL_HOST)) : Analytics::referrerHost($request);
        if ($host === strtolower($request->getHost())) {
            $host = null;
        }
        Lead::create([
            'type' => 'wa_click', 'tour_id' => $tour?->id, 'tour_name' => $tour ? trim($tour->duration.' '.$tour->name) : ($data['label'] ?? null),
            'status' => 'baru', 'page_url' => $data['page_url'] ?? null, 'referrer' => $data['referrer'] ?? null,
            'source' => Analytics::source($host, $data['utm_source'] ?? null),
            'utm_source' => $data['utm_source'] ?? null, 'utm_medium' => $data['utm_medium'] ?? null, 'utm_campaign' => $data['utm_campaign'] ?? null,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->noContent();
    }
}
