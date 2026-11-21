<?php

namespace App\Support;

use App\Models\Workspace;

/**
 * The company's logo for documents the app generates (PDFs, Word files).
 *
 * Uses the workspace owner's brand logo (Settings → Brand), which defaults to the bundled
 * Sundal logo until the company uploads its own. Documents embed the image itself, so it
 * shows offline and in attachments, and only files inside public/ or storage/app/public/
 * are ever read: a logo hosted elsewhere (e.g. S3) returns null rather than a wrong logo.
 */
class BrandLogo
{
    public const DEFAULT_PATH = '/images/logos/logo-dark.png';

    private const TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    /** Local file of the logo for a light page, or null when it can't be used. */
    public static function file(?Workspace $workspace = null, ?string $preferred = null): ?string
    {
        foreach ([$preferred, self::setting($workspace)] as $value) {
            if (is_string($value) && trim($value) !== '') {
                // A value was set: use it if it resolves locally, otherwise show nothing
                // rather than someone else's logo.
                return self::resolve($value);
            }
        }

        return self::resolve(self::DEFAULT_PATH);
    }

    /** data: URI for dompdf (no remote fetching needed), or null. */
    public static function dataUri(?Workspace $workspace = null, ?string $preferred = null): ?string
    {
        $file = self::file($workspace, $preferred);
        if (!$file) {
            return null;
        }

        $type = self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))];

        return 'data:' . $type . ';base64,' . base64_encode(file_get_contents($file));
    }

    /**
     * Absolute URL of the logo for emails. Always the dark-ink logo: the email header is
     * light grey, so the white one (used in dark theme) would be invisible there.
     */
    public static function url(?Workspace $workspace = null): string
    {
        $value = trim((string) self::setting($workspace)) ?: self::DEFAULT_PATH;
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (!str_contains(ltrim($value, '/'), '/')) {
            $value = 'storage/media/' . ltrim($value, '/');
        }

        return rtrim(config('app.url'), '/') . '/' . ltrim($value, '/');
    }

    /** The owner's dark-ink logo setting for the workspace (the one for light pages). */
    private static function setting(?Workspace $workspace): ?string
    {
        $workspace ??= auth()->user()?->currentWorkspace;
        if (!$workspace) {
            return null;
        }

        return getSetting('logoDark', self::DEFAULT_PATH, $workspace->owner_id, $workspace->id);
    }

    /** Map a stored logo value (URL, /storage/media path, bare file name) to a safe local file. */
    private static function resolve(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('#^https?://[^/]+(/[^?\#]*)#i', $value, $m)) {
            $value = $m[1]; // same file if it lives on this site; checked below
        }
        $path = ltrim(strtok($value, '?#'), '/');
        if ($path === '') {
            return null;
        }
        if (!str_contains($path, '/')) {
            $path = 'storage/media/' . $path; // bare names are media-library uploads
        }

        $candidates = [public_path($path)];
        if (str_starts_with($path, 'storage/')) {
            $candidates[] = storage_path('app/public/' . substr($path, strlen('storage/')));
        }

        $roots = array_filter([realpath(public_path()), realpath(storage_path('app/public'))]);
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (!$real || !is_file($real) || !isset(self::TYPES[strtolower(pathinfo($real, PATHINFO_EXTENSION))])) {
                continue;
            }
            foreach ($roots as $root) {
                if (str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                    return $real;
                }
            }
        }

        return null;
    }
}
