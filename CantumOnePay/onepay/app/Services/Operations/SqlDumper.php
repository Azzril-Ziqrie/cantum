<?php

namespace App\Services\Operations;

use Illuminate\Database\Connection;
use PDO;

/**
 * Dump database murni lewat PDO, untuk hosting yang tidak punya mysqldump atau melarang proc_open.
 * Mendukung MySQL (produksi) dan SQLite (tes). Hasilnya SQL biasa yang bisa dimasukkan kembali dengan klien MySQL.
 */
class SqlDumper
{
    private const ROWS_PER_INSERT = 200;

    private readonly PDO $pdo;
    private readonly string $driver;

    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->getPdo();
        $this->driver = $connection->getDriverName();
    }

    /** @param resource $handle */
    public function dump($handle): void
    {
        fwrite($handle, '-- Cantum OnePay dump ' . date('Y-m-d H:i:s') . "\n" . $this->header());
        foreach ($this->tables() as $table) {
            $this->dumpStructure($handle, $table);
            $this->dumpRows($handle, $table);
        }
        fwrite($handle, $this->footer());
    }

    private function header(): string
    {
        return $this->driver === 'mysql'
            ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n\n"
            : "PRAGMA foreign_keys=OFF;\n\n";
    }

    private function footer(): string
    {
        return $this->driver === 'mysql' ? "\nSET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n" : "\nPRAGMA foreign_keys=ON;\n";
    }

    /** @return list<string> */
    private function tables(): array
    {
        $sql = $this->driver === 'mysql'
            ? "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"
            : "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name";

        return array_map(fn (array $row) => (string) reset($row), $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    }

    private function dumpStructure($handle, string $table): void
    {
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        foreach ($this->createStatements($table) as $statement) {
            fwrite($handle, $statement . ";\n");
        }
        fwrite($handle, "\n");
    }

    /** @return list<string> */
    private function createStatements(string $table): array
    {
        if ($this->driver === 'mysql') {
            $row = $this->pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            return [(string) $row['Create Table']];
        }

        $statement = $this->pdo->prepare("SELECT sql FROM sqlite_master WHERE tbl_name = ? AND sql IS NOT NULL ORDER BY type = 'table' DESC");
        $statement->execute([$table]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function dumpRows($handle, string $table): void
    {
        $rows = $this->pdo->query("SELECT * FROM `$table`");
        $batch = [];
        $columns = null;

        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            $columns ??= '`' . implode('`, `', array_keys($row)) . '`';
            $batch[] = '(' . implode(', ', array_map($this->literal(...), $row)) . ')';

            if (count($batch) === self::ROWS_PER_INSERT) {
                $this->writeInsert($handle, $table, $columns, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->writeInsert($handle, $table, $columns, $batch);
        }
        fwrite($handle, "\n");
    }

    private function writeInsert($handle, string $table, string $columns, array $batch): void
    {
        fwrite($handle, "INSERT INTO `$table` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    private function literal(mixed $value): string
    {
        return $value === null ? 'NULL' : $this->pdo->quote((string) $value);
    }
}
