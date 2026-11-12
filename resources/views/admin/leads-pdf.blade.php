<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Laporan leads</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#1b1f3b}
h1{font-size:16px;margin:0 0 2px}h2{font-size:11px;margin:14px 0 4px}
.muted{color:#666}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px 4px;text-align:left;vertical-align:top}
th{background:#eef0f7}
.sum td{border:0;padding:1px 8px 1px 0}
</style></head><body>
<h1>Laporan leads — {{ $site }}</h1>
<div class="muted">Periode: {{ $summary['range'] }} · dibuat {{ now()->format('d M Y H:i') }}</div>
<h2>Ringkasan</h2>
<table class="sum"><tr><td>Total lead</td><td><b>{{ $summary['total'] }}</b></td><td>Dari form</td><td><b>{{ $summary['form'] }}</b></td><td>Klik WhatsApp</td><td><b>{{ $summary['wa_click'] }}</b></td></tr></table>
<table style="margin-top:6px"><tr>
<td style="width:33%"><b>Per status</b><br>@foreach($summary['by_status'] as $k=>$v){{ $statuses[$k] ?? $k }}: {{ $v }}<br>@endforeach</td>
<td style="width:33%"><b>Per sumber</b><br>@foreach($summary['by_source'] as $k=>$v){{ $k }}: {{ $v }}<br>@endforeach</td>
<td><b>Paket teratas</b><br>@foreach($summary['by_tour'] as $k=>$v){{ $k }}: {{ $v }}<br>@endforeach</td>
</tr></table>
<h2>Daftar lead</h2>
<table>
<thead><tr><th>Tanggal</th><th>Jenis</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Paket</th><th>Pax</th><th>Status</th><th>Sumber</th></tr></thead>
<tbody>
@foreach($leads as $l)
<tr><td>{{ $l->created_at?->format('d/m/y H:i') }}</td><td>{{ $l->type === 'wa_click' ? 'WA' : 'Form' }}</td><td>{{ $l->name }}</td><td>{{ $l->phone }}</td><td>{{ $l->email }}</td><td>{{ $l->tour?->name ?: $l->tour_name }}</td><td>{{ $l->pax }}</td><td>{{ $statuses[$l->status] ?? $l->status }}</td><td>{{ $l->source ?: 'direct' }}</td></tr>
@endforeach
</tbody></table>
</body></html>
