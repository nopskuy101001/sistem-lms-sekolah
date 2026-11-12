<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Lateness
{
    public static function seconds(?CarbonInterface $deadline, ?CarbonInterface $completedAt): int
    {
        if (! $deadline || ! $completedAt) {
            return 0;
        }

        return max(0, $completedAt->getTimestamp() - $deadline->getTimestamp());
    }

    public static function label(?int $seconds): ?string
    {
        if (! $seconds || $seconds < 1) {
            return null;
        }

        $remaining = $seconds;
        $units = [
            'hari' => 86400,
            'jam' => 3600,
            'menit' => 60,
            'detik' => 1,
        ];
        $parts = [];

        foreach ($units as $label => $size) {
            $value = intdiv($remaining, $size);
            if ($value > 0) {
                $parts[] = $value.' '.$label;
                $remaining %= $size;
            }
            if (count($parts) === 2) {
                break;
            }
        }

        return implode(' ', $parts);
    }
}
