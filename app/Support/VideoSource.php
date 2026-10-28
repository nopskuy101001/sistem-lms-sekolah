<?php

namespace App\Support;

class VideoSource
{
    public static function youtubeId(?string $value): ?string
    {
        $value = trim($value ?? '');

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value)) {
            return $value;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\.|^m\./', '', $host);
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        $candidate = null;
        if ($host === 'youtu.be') {
            $candidate = explode('/', $path)[0];
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if ($path === 'watch') {
                $candidate = $query['v'] ?? null;
            } elseif (preg_match('~^(embed|shorts|live)/([a-zA-Z0-9_-]{11})(?:/|$)~', $path, $matches)) {
                $candidate = $matches[2];
            }
        }

        return is_string($candidate) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $candidate) ? $candidate : null;
    }

    public static function isMp4Url(?string $value): bool
    {
        $parts = parse_url(trim($value ?? ''));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && (bool) preg_match('/\.mp4$/i', $parts['path'] ?? '');
    }
}
