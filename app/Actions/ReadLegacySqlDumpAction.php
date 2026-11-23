<?php

namespace App\Actions;

use Closure;
use Generator;
use InvalidArgumentException;

class ReadLegacySqlDumpAction
{
    private const MAX_BYTES = 67108864;

    /**
     * Read literal INSERT values from a TablePlus MySQL export. Never execute its SQL.
     *
     * @param  (Closure(int): void)|null  $onAutoIncrement
     * @return Generator<int, array<string, string|null>>
     */
    public function execute(string $path, string $table, ?Closure $onAutoIncrement = null): Generator
    {
        if (! in_array($table, ['users', 'orders', 'screens'], true)) {
            throw new InvalidArgumentException('Unsupported dump table.');
        }

        if (! is_file($path) || ! is_readable($path) || filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException("The {$table} dump must be a readable local file of at most 64 MiB.");
        }

        $contents = file_get_contents($path, length: self::MAX_BYTES + 1);

        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read the {$table} dump.");
        }

        if (str_starts_with($contents, "\x1f\x8b")) {
            set_error_handler(static fn (int $severity, string $message): bool => true, E_WARNING);

            try {
                $contents = gzdecode($contents, self::MAX_BYTES + 1);
            } finally {
                restore_error_handler();
            }
        } elseif (str_ends_with($path, '.gz')) {
            throw new InvalidArgumentException("The {$table} dump is not a valid gzip file.");
        }

        if ($contents === false || strlen($contents) > self::MAX_BYTES || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidArgumentException("The {$table} dump is damaged, exceeds 64 MiB, or is not UTF-8.");
        }

        $columns = null;

        foreach ($this->statements($contents, $table) as $statement) {
            if (preg_match('/\A(?:CREATE TABLE|DROP TABLE IF EXISTS) `'.$table.'`(?:\s|\z)/', $statement)) {
                if ($onAutoIncrement !== null && ($nextId = $this->autoIncrement($statement)) !== null) {
                    $onAutoIncrement($nextId);
                }

                continue;
            }

            if (! preg_match('/\AINSERT INTO `'.$table.'`\s*\(([^)]+)\)\s+VALUES\s*/', $statement, $matches)) {
                throw new InvalidArgumentException("Unsupported SQL statement in the {$table} dump.");
            }

            if (! preg_match('/\A\s*`\w+`(?:\s*,\s*`\w+`)*\s*\z/', $matches[1])) {
                throw new InvalidArgumentException("Invalid column list in the {$table} dump.");
            }

            preg_match_all('/`(\w+)`/', $matches[1], $columnMatches);
            $insertColumns = $columnMatches[1];

            if (count(array_unique($insertColumns)) !== count($insertColumns) || ($columns !== null && $columns !== $insertColumns)) {
                throw new InvalidArgumentException("Duplicate or changing columns in the {$table} dump.");
            }

            $columns = $insertColumns;
            $offset = strlen($matches[0]);
            $length = strlen($statement);

            do {
                $this->consume($statement, $offset, '(', $table);
                $values = [];

                foreach ($columns as $index => $column) {
                    if ($index > 0) {
                        $this->consume($statement, $offset, ',', $table);
                    }

                    $values[$column] = $this->value($statement, $offset, $table);
                }

                $this->consume($statement, $offset, ')', $table);
                yield $values;
                $this->skipWhitespace($statement, $offset);

                if ($offset === $length) {
                    break;
                }

                $this->consume($statement, $offset, ',', $table);
            } while ($offset < $length);

            if (($statement[$offset - 1] ?? '') === ',') {
                throw new InvalidArgumentException("Incomplete INSERT in the {$table} dump.");
            }
        }
    }

    private function autoIncrement(string $statement): ?int
    {
        // Quoted defaults, identifiers and comments can contain parentheses or option names.
        $unquoted = preg_replace(<<<'REGEX'
            ~
                '(?: \\. | '' | [^'\\]++ )*'
              | "(?: \\. | "" | [^"\\]++ )*"
              | `(?: \\. | `` | [^`\\]++ )*`
            ~sx
            REGEX, ' ', $statement);
        $options = strrchr($unquoted ?? '', ')');
        if ($options === false || ! preg_match('/\bAUTO_INCREMENT\s*=\s*(\d+)\b/i', $options, $match)) {
            return null;
        }

        $nextId = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($nextId === false) {
            throw new InvalidArgumentException('Invalid AUTO_INCREMENT in the dump.');
        }

        return $nextId;
    }

    /** @return Generator<int, string> */
    private function statements(string $sql, string $table): Generator
    {
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $sql[$offset];
            $next = $sql[$offset + 1] ?? '';

            if ($quote !== null) {
                $buffer .= $character;

                if ($character === '\\') {
                    $buffer .= $next;
                    $offset++;
                } elseif ($character === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $offset++;
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            if ($character === '/' && $next === '*') {
                $end = strpos($sql, '*/', $offset + 2);

                if ($end === false) {
                    throw new InvalidArgumentException("Incomplete comment in the {$table} dump.");
                }

                $comment = substr($sql, $offset, $end - $offset);

                if (str_starts_with($comment, '/*!') && ! preg_match('/\A\/\*!\d*\s+SET\s/i', $comment)) {
                    throw new InvalidArgumentException("Unsupported executable comment in the {$table} dump.");
                }

                if (stripos($comment, 'NO_BACKSLASH_ESCAPES') !== false) {
                    throw new InvalidArgumentException('Dumps with NO_BACKSLASH_ESCAPES are not supported.');
                }

                $offset = $end + 1;
                $buffer .= ' ';

                continue;
            }

            if (($character === '-' && $next === '-' && ctype_space($sql[$offset + 2] ?? ' ')) || $character === '#') {
                $offset = strpos($sql, "\n", $offset) ?: $length;
                $buffer .= ' ';

                continue;
            }

            if (in_array($character, ["'", '`', '"'], true)) {
                $quote = $character;
            }

            if ($character === ';') {
                if (trim($buffer) !== '') {
                    yield trim($buffer);
                }

                $buffer = '';
            } else {
                $buffer .= $character;
            }
        }

        if ($quote !== null || trim($buffer) !== '') {
            throw new InvalidArgumentException("Incomplete SQL statement in the {$table} dump.");
        }
    }

    private function value(string $statement, int &$offset, string $table): ?string
    {
        $this->skipWhitespace($statement, $offset);

        if (($statement[$offset] ?? '') === "'") {
            $offset++;
            $value = '';
            $length = strlen($statement);

            while ($offset < $length) {
                $character = $statement[$offset++];

                if ($character === '\\') {
                    $escaped = $statement[$offset++] ?? '';
                    $value .= match ($escaped) {
                        '0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a",
                        '%', '_' => '\\'.$escaped,
                        default => $escaped,
                    };
                } elseif ($character === "'") {
                    if (($statement[$offset] ?? '') !== "'") {
                        return $value;
                    }

                    $value .= "'";
                    $offset++;
                } else {
                    $value .= $character;
                }
            }
        } elseif (preg_match('/\G(NULL|-?\d+(?:\.\d+)?)(?=\s*[,\)])/A', $statement, $matches, 0, $offset)) {
            $offset += strlen($matches[0]);

            return $matches[1] === 'NULL' ? null : $matches[1];
        }

        throw new InvalidArgumentException("Invalid SQL literal in the {$table} dump.");
    }

    private function consume(string $statement, int &$offset, string $expected, string $table): void
    {
        $this->skipWhitespace($statement, $offset);

        if (($statement[$offset] ?? '') !== $expected) {
            throw new InvalidArgumentException("Invalid INSERT structure in the {$table} dump.");
        }

        $offset++;
    }

    private function skipWhitespace(string $statement, int &$offset): void
    {
        while (isset($statement[$offset]) && ctype_space($statement[$offset])) {
            $offset++;
        }
    }
}
