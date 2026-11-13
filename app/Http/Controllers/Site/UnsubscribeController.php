<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Suppression;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page behind the unsubscribe link in every outreach email. Opening the link only shows a
 * confirmation (mail scanners open links on their own); the POST does the unsubscribing, and
 * mail clients use the same POST for their one-click "Unsubscribe" button.
 */
class UnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        $contact = $this->contact($token);

        return view('site.unsubscribe', ['state' => $contact->suppressed_at ? 'done' : 'ask', 'masked' => $this->mask((string) $contact->email), 'token' => $token]);
    }

    public function confirm(Request $request, string $token): View
    {
        $contact = $this->contact($token);
        if (! $contact->suppressed_at && $contact->email) {
            Suppression::add($contact->email, 'unsubscribed', 'Berhenti lewat tautan di email');
        }

        return view('site.unsubscribe', ['state' => 'done', 'masked' => $this->mask((string) $contact->email), 'token' => $token]);
    }

    protected function contact(string $token): Contact
    {
        return Contact::where('unsub_token', $token)->firstOrFail();
    }

    /** a***@domain.com, so the page does not reveal the address to someone who finds the link. */
    protected function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $name === '' ? '' : mb_substr($name, 0, 1).str_repeat('*', max(2, min(6, mb_strlen($name) - 1))).'@'.$domain;
    }
}
