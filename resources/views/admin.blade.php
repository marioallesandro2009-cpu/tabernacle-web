<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="admin-base" content="{{ admin_path() }}">
    <title inertia>{{ config('app.name') }} Admin</title>
    @vite(['resources/js/admin.js', 'resources/css/admin.css'])
    @inertiaHead
</head>
<body class="bg-stone-100 text-stone-900 antialiased">
    @inertia
</body>
</html>
