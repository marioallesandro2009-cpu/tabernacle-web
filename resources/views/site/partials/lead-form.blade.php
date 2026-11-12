@php
    $leadTours = collect(\App\Support\SiteData::tourCards())->map(fn ($c) => ['id' => $c['id'], 'label' => trim($c['duration'].' '.$c['name'])])->values()->all();
    $leadProps = [
        'endpoint' => url('/leads'), 'csrf' => csrf_token(), 'tourId' => $tourId ?? null, 'tours' => $leadTours, 'today' => now()->toDateString(),
        'labels' => [
            'name' => t('lead.name'), 'phone' => t('lead.phone'), 'email' => t('lead.email'), 'tour' => t('lead.tour'), 'tourAny' => t('lead.tour_any'),
            'pax' => t('lead.pax'), 'date' => t('lead.date'), 'message' => t('lead.message'), 'send' => t('lead.send'), 'sending' => t('lead.sending'),
            'success' => t('lead.success'), 'error' => t('lead.error'), 'phoneInvalid' => t('lead.phone_invalid'), 'throttle' => t('lead.throttle'),
        ],
    ];
@endphp
<div class="lead-form-island" data-island="lead-form" data-props="{{ json_encode($leadProps, JSON_UNESCAPED_UNICODE) }}">
  <noscript><p><a class="btn btn-amber" href="{{ wa_link(t('wa.plan')) }}" target="_blank" rel="noopener">{{ t('tour.book_wa') }}</a></p></noscript>
</div>
