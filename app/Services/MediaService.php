<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Stores uploads under public/uploads. Images become WebP in several sizes;
 * everything else is checked against a strict allow-list.
 */
class MediaService
{
    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public const FILE_EXT = ['pdf', 'svg'];

    public const VIDEO_EXT = ['mp4', 'webm'];

    /** Extensions that must never be stored, whatever the MIME type says. */
    public const BLOCKED = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'exe', 'sh', 'bat', 'cmd', 'js', 'mjs', 'html', 'htm', 'htaccess', 'cgi', 'pl', 'py', 'jar', 'msi', 'com', 'dll'];

    public const SIZES = [480, 960, 1600];

    public const MAX_WIDTH = 2000;

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('uploads');
    }

    /** @return array{ok: bool, error?: string} */
    public function validate(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $name = strtolower($file->getClientOriginalName());
        // blocks "shell.php.jpg" style names too
        foreach (explode('.', $name) as $part) {
            if (in_array($part, self::BLOCKED, true)) {
                return ['ok' => false, 'error' => 'Tipe file ini tidak diizinkan.'];
            }
        }
        $allowed = [...self::IMAGE_EXT, ...self::FILE_EXT, ...self::VIDEO_EXT];
        if (! in_array($ext, $allowed, true)) {
            return ['ok' => false, 'error' => 'Ekstensi .'.$ext.' tidak diizinkan. Boleh: '.implode(', ', $allowed).'.'];
        }
        $mime = (string) $file->getMimeType();
        $okMime = match (true) {
            in_array($ext, self::IMAGE_EXT, true) => str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml',
            $ext === 'svg' => in_array($mime, ['image/svg+xml', 'text/xml', 'text/plain', 'application/xml'], true),
            $ext === 'pdf' => $mime === 'application/pdf',
            default => str_starts_with($mime, 'video/') || $mime === 'application/octet-stream',
        };
        if (! $okMime) {
            return ['ok' => false, 'error' => 'Isi file tidak cocok dengan ekstensinya.'];
        }
        $limitMb = in_array($ext, self::VIDEO_EXT, true) ? 100 : ($ext === 'pdf' ? 30 : 12);
        if ($file->getSize() > $limitMb * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Ukuran file maksimal '.$limitMb.' MB.'];
        }
        if ($ext === 'svg') {
            $svg = (string) file_get_contents($file->getRealPath());
            if (preg_match('/<script|on\w+\s*=|javascript:|<foreignObject|<iframe|<embed/i', $svg)) {
                return ['ok' => false, 'error' => 'SVG berisi skrip dan ditolak demi keamanan.'];
            }
        }

        return ['ok' => true];
    }

    public function store(UploadedFile $file, string $folder = 'umum', ?string $alt = null): Media
    {
        return $this->import($file->getRealPath(), $file->getClientOriginalName(), $folder, $alt, $file->getClientOriginalExtension());
    }

    /** Import a file that already exists on disk (used by uploads and by the seeder). */
    public function import(string $absPath, string $originalName, string $folder = 'umum', ?string $alt = null, ?string $ext = null): Media
    {
        $ext = strtolower($ext ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $stem = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';
        $dir = 'media/'.date('Y/m');
        $base = $stem.'-'.Str::lower(Str::random(6));

        if (in_array($ext, self::IMAGE_EXT, true)) {
            return $this->storeImage($absPath, $originalName, $folder, $alt, $dir, $base);
        }
        $kind = in_array($ext, self::VIDEO_EXT, true) ? 'video' : 'file';
        $path = $dir.'/'.$base.'.'.$ext;
        $this->disk()->put($path, (string) file_get_contents($absPath));

        return Media::create([
            'folder' => $folder, 'kind' => $kind, 'path' => $path, 'variants' => null, 'name' => $originalName,
            'mime' => $this->mimeFor($ext), 'size' => filesize($absPath) ?: 0, 'alt' => $alt,
        ]);
    }

    protected function storeImage(string $absPath, string $originalName, string $folder, ?string $alt, string $dir, string $base): Media
    {
        $manager = new ImageManager(new Driver);
        $img = $manager->read($absPath);
        try {
            $img->orient();
        } catch (\Throwable) {
            // not every format carries EXIF orientation
        }
        if ($img->width() > self::MAX_WIDTH) {
            $img->scaleDown(width: self::MAX_WIDTH);
        }
        $w = $img->width();
        $h = $img->height();
        $main = $dir.'/'.$base.'.webp';
        $this->disk()->put($main, (string) $img->toWebp(82));

        $variants = [];
        foreach (self::SIZES as $size) {
            if ($size >= $w) {
                continue;
            }
            $copy = $manager->read($absPath);
            try {
                $copy->orient();
            } catch (\Throwable) {
            }
            $copy->scaleDown(width: $size);
            $p = $dir.'/'.$base.'-'.$size.'.webp';
            $this->disk()->put($p, (string) $copy->toWebp(80));
            $variants[$size] = $p;
        }
        $variants[$w] = $main;

        return Media::create([
            'folder' => $folder, 'kind' => 'image', 'path' => $main, 'variants' => $variants, 'name' => $originalName,
            'mime' => 'image/webp', 'size' => (int) $this->disk()->size($main), 'width' => $w, 'height' => $h, 'alt' => $alt,
        ]);
    }

    public function delete(Media $media): void
    {
        foreach ($media->allPaths() as $p) {
            $this->disk()->delete($p);
        }
        $media->delete();
    }

    protected function mimeFor(string $ext): string
    {
        return ['pdf' => 'application/pdf', 'svg' => 'image/svg+xml', 'mp4' => 'video/mp4', 'webm' => 'video/webm'][$ext] ?? 'application/octet-stream';
    }
}
