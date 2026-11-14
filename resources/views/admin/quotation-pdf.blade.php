@php
    use App\Models\Quotation;
    $cur = $q['currency'] ?? 'IDR';
    $m = fn ($v) => Quotation::money((float) $v, $cur);
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->locale('id')->isoFormat('D MMMM YYYY') : null;
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $navy = $company['navy'];
@endphp
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Penawaran {{ $q['number_label'] }}</title>
<style>
@page { margin: 26px 34px 60px 34px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1b2236; line-height: 1.45; }
.navy { color: {{ $navy }}; }
.muted { color: #6b7385; }
table { width: 100%; border-collapse: collapse; }
.head td { vertical-align: top; }
.brand { font-size: 17px; font-weight: bold; color: {{ $navy }}; letter-spacing: .5px; }
.brand small { display: block; font-size: 8px; letter-spacing: 2.5px; color: #8a6d2b; font-weight: bold; margin-top: 1px; }
.rule { height: 3px; background: {{ $navy }}; margin: 10px 0 14px; }
.doc-title { font-size: 15px; font-weight: bold; color: {{ $navy }}; letter-spacing: 1.5px; }
.box { border: 1px solid #dde1ea; border-radius: 4px; padding: 8px 10px; }
.label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 1px; color: #6b7385; font-weight: bold; margin-bottom: 2px; }
.items th { background: {{ $navy }}; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: .6px; padding: 6px 7px; text-align: left; }
.items th.r { text-align: right; }
.items td { padding: 6px 7px; border-bottom: 1px solid #e6e9f0; vertical-align: top; }
.items tr:nth-child(even) td { background: #f7f8fb; }
.r { text-align: right; }
.totals td { padding: 3px 7px; }
.totals .grand td { font-size: 12px; font-weight: bold; color: #fff; background: {{ $navy }}; padding: 7px; }
.section { margin-top: 14px; }
.pre { white-space: pre-line; }
.foot { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 7.5px; color: #8b93a7; border-top: 1px solid #e6e9f0; padding-top: 5px; }
.stamp { position: fixed; top: 300px; left: 120px; font-size: 54px; color: rgba(200, 60, 60, .12); transform: rotate(-24deg); font-weight: bold; letter-spacing: 6px; }
</style></head>
<body>
@if ($old)<div class="stamp">VERSI LAMA</div>@endif

<table class="head"><tr>
    <td style="width:58%">
        @if ($company['logo'])
            <img src="{{ $company['logo'] }}" style="max-height:46px;max-width:220px" alt="">
        @else
            <div class="brand">{{ strtoupper((string) $company['name']) }}@if ($company['suffix'])<small>{{ strtoupper((string) $company['suffix']) }}</small>@endif</div>
        @endif
    </td>
    <td style="width:42%" class="r muted">
        @if ($company['logo'])<div style="font-weight:bold;color:#1b2236;font-size:10px">{{ $company['name'] }}</div>@endif
        @if ($company['address'])<div class="pre">{{ $company['address'] }}</div>@endif
        <div>{{ collect([$company['phone'], $company['email']])->filter()->implode(' · ') }}</div>
        @if ($company['web'])<div>{{ $company['web'] }}</div>@endif
    </td>
</tr></table>
<div class="rule"></div>

<table><tr>
    <td style="width:55%;vertical-align:bottom"><div class="doc-title">PENAWARAN HARGA</div><div class="muted">Quotation</div></td>
    <td style="width:45%">
        <table>
            <tr><td class="muted" style="width:45%">Nomor</td><td><b>{{ $q['number_label'] }}</b></td></tr>
            <tr><td class="muted">Tanggal</td><td>{{ $d($q['issued_on']) }}</td></tr>
            @if (! empty($q['valid_until']))<tr><td class="muted">Berlaku sampai</td><td>{{ $d($q['valid_until']) }}</td></tr>@endif
        </table>
    </td>
</tr></table>

<table style="margin-top:12px"><tr>
    <td style="width:50%;padding-right:6px;vertical-align:top">
        <div class="box">
            <div class="label">Kepada</div>
            <div style="font-weight:bold;font-size:10.5px">{{ $q['client_name'] }}</div>
            @if (! empty($q['client_company']))<div>{{ $q['client_company'] }}</div>@endif
            @if (! empty($q['client_address']))<div class="pre muted">{{ $q['client_address'] }}</div>@endif
            <div class="muted">{{ collect([$q['client_phone'] ?? null, $q['client_email'] ?? null])->filter()->implode(' · ') }}</div>
        </div>
    </td>
    <td style="width:50%;padding-left:6px;vertical-align:top">
        <div class="box">
            <div class="label">Perihal</div>
            <div style="font-weight:bold;font-size:10.5px">{{ $q['title'] }}</div>
            @if (! empty($q['travel_date']))<div>Keberangkatan: {{ $d($q['travel_date']) }}</div>@endif
            @if (! empty($q['pax']))<div>Jumlah peserta: {{ $q['pax'] }} orang</div>@endif
        </div>
    </td>
</tr></table>

<table class="items" style="margin-top:14px">
    <thead><tr><th style="width:5%">No</th><th>Deskripsi</th><th class="r" style="width:11%">Jumlah</th><th class="r" style="width:19%">Harga satuan</th><th class="r" style="width:20%">Total</th></tr></thead>
    <tbody>
    @foreach ($t['lines'] as $i => $line)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td class="pre">{{ $line['description'] }}</td>
            <td class="r">{{ $qty($line['qty']) }}{{ $line['unit'] ? ' '.$line['unit'] : '' }}</td>
            <td class="r">{{ $m($line['price']) }}</td>
            <td class="r">{{ $m($line['amount']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top:4px"><tr>
    <td style="width:52%"></td>
    <td style="width:48%">
        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="r">{{ $m($t['subtotal']) }}</td></tr>
            @if ($t['discount'] > 0)<tr><td class="muted">Diskon</td><td class="r">− {{ $m($t['discount']) }}</td></tr>@endif
            @if ($t['tax_percent'] > 0)<tr><td class="muted">{{ ($q['tax_label'] ?? '') ?: 'Pajak' }} {{ rtrim(rtrim(number_format($t['tax_percent'], 2, ',', '.'), '0'), ',') }}%</td><td class="r">{{ $m($t['tax']) }}</td></tr>@endif
            <tr class="grand"><td>TOTAL</td><td class="r">{{ $m($t['total']) }}</td></tr>
        </table>
    </td>
</tr></table>

@if (! empty($q['notes']))
    <div class="section"><div class="label">Catatan</div><div class="pre">{{ $q['notes'] }}</div></div>
@endif
@if (! empty($q['terms']))
    <div class="section"><div class="label">Syarat &amp; ketentuan</div><div class="pre">{{ $q['terms'] }}</div></div>
@endif
@if (! empty($q['payment_info']))
    <div class="section box" style="background:#f7f8fb"><div class="label">Informasi pembayaran</div><div class="pre">{{ $q['payment_info'] }}</div></div>
@endif

<table style="margin-top:26px;page-break-inside:avoid"><tr>
    <td style="width:60%"></td>
    <td style="width:40%;text-align:center">
        <div class="muted">Hormat kami,</div>
        <div>{{ $company['name'] }}</div>
        <div style="height:58px"></div>
        <div style="border-top:1px solid #1b2236;padding-top:3px;font-weight:bold">{{ ($q['signatory_name'] ?? '') ?: ' ' }}</div>
        @if (! empty($q['signatory_title']))<div class="muted">{{ $q['signatory_title'] }}</div>@endif
    </td>
</tr></table>

<div class="foot">{{ $company['name'] }} · Penawaran {{ $q['number_label'] }} · Harga dalam {{ $cur === 'USD' ? 'Dolar AS' : 'Rupiah' }}.</div>
</body></html>
