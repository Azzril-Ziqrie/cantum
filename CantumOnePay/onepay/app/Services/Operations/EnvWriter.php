<?php

namespace App\Services\Operations;

/** Membaca dan menulis berkas .env tanpa merusak komentar dan urutan baris. */
class EnvWriter
{
    public function __construct(private readonly string $path, private readonly ?string $templatePath = null)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /** @return array<string, string> nilai aktif, tanda kutip dibuang */
    public function read(): array
    {
        $values = [];
        foreach ($this->lines() as $line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $match)) {
                $values[$match[1]] = $this->unquote($match[2]);
            }
        }
        return $values;
    }

    public function get(string $key): ?string
    {
        return $this->read()[$key] ?? null;
    }

    /** @param array<string, string> $values */
    public function set(array $values): void
    {
        $lines = $this->lines();
        foreach ($values as $key => $value) {
            $lines = $this->setLine($lines, $key, $value);
        }

        file_put_contents($this->path, implode("\n", $lines) . "\n", LOCK_EX);
        @chmod($this->path, 0600);
    }

    /** @return list<string> */
    private function lines(): array
    {
        $source = $this->exists() ? $this->path : $this->templatePath;
        if ($source === null || !is_file($source)) {
            return [];
        }
        return preg_split('/\R/', rtrim((string) file_get_contents($source))) ?: [];
    }

    /** Ganti baris aktif; bila tidak ada, aktifkan baris yang dikomentari; bila tidak ada juga, tambahkan di akhir. */
    private function setLine(array $lines, string $key, string $value): array
    {
        $new = $key . '=' . $this->quote($value);

        foreach ([$key . '=', '# ' . $key . '=', '#' . $key . '='] as $prefix) {
            foreach ($lines as $index => $line) {
                if (str_starts_with($line, $prefix)) {
                    $lines[$index] = $new;
                    return $lines;
                }
            }
        }

        $lines[] = $new;
        return $lines;
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+,\-]+$/', $value) === 1) {
            return $value;
        }
        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }

    private function unquote(string $raw): string
    {
        $raw = trim($raw);
        if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
            return str_replace(['\\"', '\\$', '\\\\'], ['"', '$', '\\'], substr($raw, 1, -1));
        }
        return $raw;
    }
}
