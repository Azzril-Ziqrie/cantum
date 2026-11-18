<?php

namespace App\Services\Operations;

use Carbon\CarbonImmutable;

/** Penanda "scheduler hidup": dicatat tiap menit oleh `schedule:run` dan dibaca oleh onepay:doctor. */
class Heartbeat
{
    public function beat(): void
    {
        $path = $this->path();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, (string) time(), LOCK_EX);
    }

    public function lastBeat(): ?CarbonImmutable
    {
        $path = $this->path();
        $timestamp = is_file($path) ? (int) trim((string) file_get_contents($path)) : 0;
        return $timestamp > 0 ? CarbonImmutable::createFromTimestamp($timestamp) : null;
    }

    private function path(): string
    {
        return (string) config('onepay.heartbeat_path');
    }
}
