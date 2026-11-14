<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** The "Terjemahkan ke Inggris" buttons in the admin forms: Indonesian in, an English draft out. */
class TranslateController extends Controller
{
    public const MAX_CHARS = 30000;

    public function store(Request $request, Translator $translator): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.text' => ['present', 'nullable', 'string'],
            'items.*.html' => ['boolean'],
        ]);
        $total = collect($data['items'])->sum(fn ($i) => mb_strlen(strip_tags((string) ($i['text'] ?? ''))));
        if ($total > self::MAX_CHARS) {
            return response()->json(['message' => 'Teksnya terlalu panjang untuk sekali terjemah ('.number_format($total, 0, ',', '.').' karakter, maksimal '.number_format(self::MAX_CHARS, 0, ',', '.').').'], 422);
        }
        try {
            $out = array_map(fn ($i) => $translator->translate((string) ($i['text'] ?? ''), (bool) ($i['html'] ?? false)), $data['items']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['items' => $out]);
    }
}
