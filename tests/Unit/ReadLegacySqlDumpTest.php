<?php

use App\Actions\ReadLegacySqlDumpAction;

beforeEach(function () {
    $this->dumpPath = tempnam(sys_get_temp_dir(), 'legacy-sql-');
});

afterEach(function () {
    unlink($this->dumpPath);
});

it('reads gzip literal rows with SQL escapes and preserves decimals', function () {
    $sql = <<<'SQL'
-- Export comment
/*!40101 SET NAMES utf8mb4 */;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (`id` bigint);
INSERT INTO `users` (`id`, `name`, `amount`, `optional`) VALUES
(7, 'O\'Brien; -- text, (x) \\ path\nnext', 123456.70, NULL),
(8, 'é😀 and ''quotes'' /* text */', -0.25, 'NULL');
INSERT INTO `users` (`id`, `name`, `amount`, `optional`) VALUES
(9, 'tab\tend\r\n', 0, '\0\b\Z\%\_');
SQL;
    file_put_contents($this->dumpPath, gzencode($sql));

    $rows = iterator_to_array((new ReadLegacySqlDumpAction)->execute($this->dumpPath, 'users'));

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe(['id' => '7', 'name' => "O'Brien; -- text, (x) \\ path\nnext", 'amount' => '123456.70', 'optional' => null])
        ->and($rows[1]['name'])->toBe("é😀 and 'quotes' /* text */")
        ->and($rows[1]['optional'])->toBe('NULL')
        ->and($rows[2]['optional'])->toBe("\0\x08\x1a\\%\\_");
});

it('reads actual line breaks inside quoted values', function () {
    file_put_contents($this->dumpPath, "INSERT INTO `users` (`id`, `name`) VALUES (1, 'one\ntwo');\n");

    expect(iterator_to_array((new ReadLegacySqlDumpAction)->execute($this->dumpPath, 'users'))[0]['name'])->toBe("one\ntwo");
});

it('rejects incomplete or unsupported SQL instead of executing or skipping it', function (string $sql) {
    file_put_contents($this->dumpPath, $sql);

    expect(fn () => iterator_to_array((new ReadLegacySqlDumpAction)->execute($this->dumpPath, 'users')))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'expression' => ['INSERT INTO `users` (`id`) VALUES (SLEEP(1));'],
    'other table' => ['INSERT INTO `orders` (`id`) VALUES (1);'],
    'update' => ['UPDATE `users` SET `id` = 1;'],
    'other DDL' => ['DROP TABLE IF EXISTS `orders`;'],
    'unterminated statement' => ['INSERT INTO `users` (`id`) VALUES (1)'],
    'unterminated string' => ["INSERT INTO `users` (`id`) VALUES ('one);"],
    'too many values' => ['INSERT INTO `users` (`id`) VALUES (1, 2);'],
    'too few values' => ['INSERT INTO `users` (`id`, `name`) VALUES (1);'],
    'trailing comma' => ['INSERT INTO `users` (`id`) VALUES (1),;'],
    'duplicate columns' => ['INSERT INTO `users` (`id`, `id`) VALUES (1, 2);'],
    'changing columns' => ['INSERT INTO `users` (`id`) VALUES (1); INSERT INTO `users` (`name`) VALUES (2);'],
    'unterminated comment' => ['/* missing end'],
    'unsupported sql mode' => ["/*!40101 SET SQL_MODE='NO_BACKSLASH_ESCAPES' */;"],
    'lowercase unsupported mode' => ["/*!40101 SET SQL_MODE='no_backslash_escapes' */;"],
    'hidden insert' => ['/*!40101 INSERT INTO `users` (`id`) VALUES (1) */;'],
]);

it('rejects a damaged gzip stream', function () {
    $compressed = gzencode('INSERT INTO `users` (`id`) VALUES (1);');
    file_put_contents($this->dumpPath, substr($compressed, 0, -8));

    expect(fn () => iterator_to_array((new ReadLegacySqlDumpAction)->execute($this->dumpPath, 'users')))
        ->toThrow(InvalidArgumentException::class);
});
