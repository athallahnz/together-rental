<?php

namespace App\Domain\LegacyImport;

use Generator;
use RuntimeException;
use SplFileObject;

final class RentalV1SqlParser
{
    /**
     * Parse a mysqldump as inert text and emit allowlisted rows.
     *
     * @param  callable(string, int, array<string, mixed>, list<string>): void  $onRow
     * @return array{tables: array<string, array{columns: list<string>, rows: int}>, ignored_tables: list<string>}
     */
    public function parse(string $path, callable $onRow): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('File SQL RentalV1 tidak dapat dibaca.');
        }

        $file = new SplFileObject($path, 'rb');
        $schemas = [];
        $ignoredTables = [];
        $createTable = null;
        $createColumns = [];
        $statement = '';

        while (! $file->eof()) {
            $line = $file->fgets();

            if ($statement !== '') {
                $statement .= $line;

                if ($this->statementComplete($statement)) {
                    $this->parseInsert($statement, $schemas, $onRow);
                    $statement = '';
                }

                continue;
            }

            if (preg_match('/^CREATE TABLE `([^`]+)`/i', $line, $matches) === 1) {
                $createTable = $matches[1];
                $createColumns = [];

                if (! in_array($createTable, RentalV1Definition::tables(), true)) {
                    $ignoredTables[] = $createTable;
                }

                continue;
            }

            if ($createTable !== null) {
                if (preg_match('/^\s+`([^`]+)`\s+/', $line, $matches) === 1) {
                    $createColumns[] = $matches[1];
                }

                if (preg_match('/^\)\s+/', $line) === 1) {
                    if (in_array($createTable, RentalV1Definition::tables(), true)) {
                        $schemas[$createTable] = [
                            'columns' => $createColumns,
                            'rows' => 0,
                        ];
                    }

                    $createTable = null;
                    $createColumns = [];
                }

                continue;
            }

            if (preg_match('/^INSERT INTO\s+`/i', $line) === 1) {
                $statement = $line;

                if ($this->statementComplete($statement)) {
                    $this->parseInsert($statement, $schemas, $onRow);
                    $statement = '';
                }
            }
        }

        if ($statement !== '') {
            throw new RuntimeException('Statement INSERT terakhir tidak ditutup dengan benar.');
        }

        if ($schemas === []) {
            throw new RuntimeException('Tidak ditemukan tabel RentalV1 yang didukung.');
        }

        return [
            'tables' => $schemas,
            'ignored_tables' => array_values(array_unique($ignoredTables)),
        ];
    }

    private function statementComplete(string $statement): bool
    {
        return str_ends_with(rtrim($statement), ';');
    }

    /**
     * @param  array<string, array{columns: list<string>, rows: int}>  $schemas
     * @param  callable(string, int, array<string, mixed>, list<string>): void  $onRow
     */
    private function parseInsert(string $statement, array &$schemas, callable $onRow): void
    {
        if (preg_match(
            '/^INSERT INTO\s+`([^`]+)`\s*(?:\((.*?)\))?\s*VALUES\s*(.*);\s*$/is',
            $statement,
            $matches,
        ) !== 1) {
            throw new RuntimeException('Format INSERT mysqldump tidak dikenali.');
        }

        $table = $matches[1];

        if (! isset($schemas[$table]) || ! in_array($table, RentalV1Definition::tables(), true)) {
            return;
        }

        $columns = trim((string) ($matches[2] ?? '')) !== ''
            ? $this->parseColumnList($matches[2])
            : $schemas[$table]['columns'];

        foreach ($this->parseValues($matches[3]) as $values) {
            if (count($columns) !== count($values)) {
                throw new RuntimeException(
                    "Jumlah kolom dan nilai tidak cocok pada tabel [{$table}] baris ".
                    ($schemas[$table]['rows'] + 1).'.',
                );
            }

            $schemas[$table]['rows']++;
            $payload = array_combine($columns, $values);

            if ($payload === false) {
                throw new RuntimeException("Payload tabel [{$table}] tidak dapat dibentuk.");
            }

            $onRow($table, $schemas[$table]['rows'], $payload, $columns);
        }
    }

    /** @return list<string> */
    private function parseColumnList(string $columns): array
    {
        preg_match_all('/`([^`]+)`/', $columns, $matches);

        return $matches[1];
    }

    /** @return Generator<int, list<mixed>> */
    private function parseValues(string $input): Generator
    {
        $length = strlen($input);
        $offset = 0;

        while ($offset < $length) {
            $this->skipWhitespaceAndCommas($input, $offset, $length);

            if ($offset >= $length) {
                break;
            }

            if ($input[$offset] !== '(') {
                throw new RuntimeException("Tuple SQL tidak valid pada offset {$offset}.");
            }

            $offset++;
            $values = [];

            while (true) {
                $this->skipWhitespace($input, $offset, $length);
                $values[] = $this->parseValue($input, $offset, $length);
                $this->skipWhitespace($input, $offset, $length);

                if ($offset >= $length) {
                    throw new RuntimeException('Tuple SQL tidak ditutup.');
                }

                if ($input[$offset] === ',') {
                    $offset++;

                    continue;
                }

                if ($input[$offset] === ')') {
                    $offset++;

                    break;
                }

                throw new RuntimeException("Pemisah nilai SQL tidak valid pada offset {$offset}.");
            }

            yield $values;
        }
    }

    private function parseValue(string $input, int &$offset, int $length): mixed
    {
        if ($offset < $length && $input[$offset] === "'") {
            return $this->parseQuotedString($input, $offset, $length);
        }

        $start = $offset;

        while ($offset < $length && $input[$offset] !== ',' && $input[$offset] !== ')') {
            $offset++;
        }

        $token = trim(substr($input, $start, $offset - $start));

        if (strcasecmp($token, 'NULL') === 0) {
            return null;
        }

        if ($token === '') {
            throw new RuntimeException("Nilai SQL kosong pada offset {$start}.");
        }

        return $this->validUtf8($token);
    }

    private function parseQuotedString(string $input, int &$offset, int $length): string
    {
        $offset++;
        $value = '';

        while ($offset < $length) {
            $character = $input[$offset++];

            if ($character === "'") {
                if ($offset < $length && $input[$offset] === "'") {
                    $value .= "'";
                    $offset++;

                    continue;
                }

                return $this->validUtf8($value);
            }

            if ($character !== '\\') {
                $value .= $character;

                continue;
            }

            if ($offset >= $length) {
                throw new RuntimeException('Escape sequence SQL tidak lengkap.');
            }

            $escaped = $input[$offset++];
            $value .= match ($escaped) {
                '0' => "\0",
                'b' => "\x08",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'Z' => "\x1a",
                default => $escaped,
            };
        }

        throw new RuntimeException('String SQL tidak ditutup.');
    }

    private function skipWhitespaceAndCommas(string $input, int &$offset, int $length): void
    {
        while ($offset < $length && (ctype_space($input[$offset]) || $input[$offset] === ',')) {
            $offset++;
        }
    }

    private function skipWhitespace(string $input, int &$offset, int $length): void
    {
        while ($offset < $length && ctype_space($input[$offset])) {
            $offset++;
        }
    }

    private function validUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
