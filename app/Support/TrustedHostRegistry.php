<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

class TrustedHostRegistry
{
    /**
     * Return exact Symfony trusted-host patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        $patterns = array_map(
            static fn (string $host): string => '^'.preg_quote($host).'$',
            $this->hosts()
        );

        // Symfony treats an empty pattern list as unrestricted, so a broken
        // configuration must resolve to an impossible host instead.
        return $patterns !== [] ? $patterns : ['(?!)'];
    }

    /**
     * Return configured, canonical, and runtime-registered hosts.
     *
     * @return array<int, string>
     */
    public function hosts(): array
    {
        $configured = config('reverse_proxy.trusted_hosts', []);
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);

        $hosts = [
            ...(is_array($configured) ? $configured : []),
            ...(is_string($canonical) ? [$canonical] : []),
            ...$this->registeredHosts(),
        ];

        $normalized = [];

        foreach ($hosts as $host) {
            try {
                $normalized[] = $this->normalize((string) $host);
            } catch (InvalidArgumentException) {
                // Invalid configuration must never broaden the allowlist.
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /** @return array<int, string> */
    public function registeredHosts(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            if (! flock($handle, LOCK_SH)) {
                return [];
            }

            $contents = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $decoded = json_decode($contents ?: '', true);
        $hosts = is_array($decoded) ? ($decoded['hosts'] ?? []) : [];

        if (! is_array($hosts)) {
            return [];
        }

        $normalized = [];

        foreach ($hosts as $host) {
            try {
                $normalized[] = $this->normalize((string) $host);
            } catch (InvalidArgumentException) {
                // Ignore corrupt entries without trusting them.
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    public function register(string $host): string
    {
        $normalized = $this->normalize($host);
        $hosts = $this->registeredHosts();
        $hosts[] = $normalized;

        $this->write(array_values(array_unique($hosts)));

        return $normalized;
    }

    public function remove(string $host): string
    {
        $normalized = $this->normalize($host);
        $hosts = array_values(array_filter(
            $this->registeredHosts(),
            static fn (string $registered): bool => $registered !== $normalized
        ));

        $this->write($hosts);

        return $normalized;
    }

    public function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        if ($value === '') {
            throw new InvalidArgumentException('Hostname tidak boleh kosong.');
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                throw new InvalidArgumentException('URL tidak memiliki hostname yang valid.');
            }

            $value = $host;
        } elseif (preg_match('/^\[([0-9a-f:]+)](?::\d+)?$/i', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^([^:]+):\d+$/', $value, $matches)) {
            $value = $matches[1];
        }

        $value = rtrim($value, '.');

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return $value;
        }

        if (str_contains($value, '*') || str_contains($value, '/') || str_contains($value, '\\')) {
            throw new InvalidArgumentException('Gunakan hostname exact tanpa wildcard atau path.');
        }

        if (strlen($value) > 253 || ! preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',
            $value
        )) {
            throw new InvalidArgumentException('Hostname tidak valid.');
        }

        return $value;
    }

    /** @param array<int, string> $hosts */
    private function write(array $hosts): void
    {
        sort($hosts, SORT_STRING);

        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori registry trusted host tidak dapat dibuat.');
        }

        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('Registry trusted host tidak dapat dibuka.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Registry trusted host tidak dapat dikunci.');
            }

            $json = json_encode(
                ['hosts' => array_values(array_unique($hosts))],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ).PHP_EOL;

            rewind($handle);

            if (! ftruncate($handle, 0) || fwrite($handle, $json) === false || ! fflush($handle)) {
                throw new RuntimeException('Registry trusted host tidak dapat ditulis.');
            }

            flock($handle, LOCK_UN);
            @chmod($path, 0640);
        } finally {
            fclose($handle);
        }
    }

    private function path(): string
    {
        $path = config('reverse_proxy.host_registry');

        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('Lokasi registry trusted host belum dikonfigurasi.');
        }

        return $path;
    }
}
