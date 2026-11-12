<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $u = $request->user();
        $setup = null;
        if (! $u->hasTwoFactor() && $request->session()->has('2fa.setup')) {
            $secret = (string) $request->session()->get('2fa.setup');
            $uri = (new Google2FA)->getQRCodeUrl((string) config('app.name'), $u->email, $secret);
            $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));
            $setup = ['secret' => $secret, 'qr' => $writer->writeString($uri)];
        }

        return Inertia::render('Admin/Profile', [
            'profile' => ['name' => $u->name, 'email' => $u->email, 'avatar_id' => $u->avatar_id, 'avatar' => $u->avatar_id ? media_url($u->avatar_id, 160) : null],
            'twoFactor' => ['enabled' => $u->hasTwoFactor(), 'setup' => $setup],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $u = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($u->id)],
            'avatar_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        $u->update($data);

        return back()->with('success', 'Profil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed', 'different:current_password'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.']);
        }
        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password diubah.');
    }

    public function startTwoFactor(Request $request): RedirectResponse
    {
        $request->session()->put('2fa.setup', (new Google2FA)->generateSecretKey());

        return back();
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = (string) $request->session()->get('2fa.setup');
        if ($secret === '' || ! (new Google2FA)->verifyKey($secret, preg_replace('/\D/', '', $request->input('code')) ?: '000000')) {
            throw ValidationException::withMessages(['code' => 'Kode salah. Pastikan jam ponsel Anda akurat.']);
        }
        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('2fa.setup');

        return back()->with('success', 'Verifikasi dua langkah aktif.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password salah.']);
        }
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        return back()->with('success', 'Verifikasi dua langkah dimatikan.');
    }
}
