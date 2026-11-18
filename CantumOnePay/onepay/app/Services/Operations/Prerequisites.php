<?php

namespace App\Services\Operations;

/** Prasyarat server untuk menjalankan OnePay. Mengembalikan daftar masalah (kosong = siap). */
class Prerequisites
{
    public const MIN_PHP = '8.2.0';

    public const EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'json', 'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'];

    private const WRITABLE_FOLDERS = ['storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'];

    public function __construct(private readonly string $basePath)
    {
    }

    /** @return list<string> */
    public function problems(): array
    {
        return array_values(array_filter([
            $this->phpVersionProblem(),
            ...$this->extensionProblems(),
            ...$this->writableProblems(),
        ]));
    }

    private function phpVersionProblem(): ?string
    {
        return version_compare(PHP_VERSION, self::MIN_PHP, '>=')
            ? null
            : 'PHP ' . PHP_VERSION . ' terlalu lama; butuh PHP ' . self::MIN_PHP . ' atau lebih baru (ubah di hPanel, menu PHP Configuration).';
    }

    /** @return list<string> */
    private function extensionProblems(): array
    {
        $missing = array_filter(self::EXTENSIONS, fn (string $extension) => !extension_loaded($extension));
        return array_map(fn (string $extension) => "Ekstensi PHP $extension belum aktif.", array_values($missing));
    }

    /** @return list<string> */
    private function writableProblems(): array
    {
        $problems = [];
        foreach (self::WRITABLE_FOLDERS as $relative) {
            $path = $this->basePath . '/' . $relative;
            if (!is_dir($path) || !is_writable($path)) {
                $problems[] = "Folder $relative tidak ada atau tidak bisa ditulis (atur izin 775).";
            }
        }
        return $problems;
    }
}
