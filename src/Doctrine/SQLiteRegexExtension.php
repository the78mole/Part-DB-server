<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace App\Doctrine;

use App\Exceptions\InvalidRegexException;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 * This subscriber is used to add the regexp operator to the SQLite platform.
 * As a PHP callback is called for every entry to compare it is most likely much slower than using regex on MySQL.
 * But as regex is not often used, this should be fine for most use cases, also it is almost impossible to implement a better solution.
 */
#[AsDoctrineListener(Events::postConnect)]
class SQLiteRegexExtension
{
    public function postConnect(ConnectionEventArgs $eventArgs): void
    {
        $connection = $eventArgs->getConnection();

        //We only execute this on SQLite databases
        if ($connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $native_connection = $connection->getNativeConnection();

            //Ensure that the function really exists on the connection, as it is marked as experimental according to PHP documentation
            if($native_connection instanceof \PDO && method_exists($native_connection, 'sqliteCreateFunction' )) {
                $native_connection->sqliteCreateFunction('REGEXP', self::regexp(...), 2, \PDO::SQLITE_DETERMINISTIC);
                $native_connection->sqliteCreateFunction('FIELD', self::field(...), -1, \PDO::SQLITE_DETERMINISTIC);
                $native_connection->sqliteCreateFunction('FIELD2', self::field2(...), 2, \PDO::SQLITE_DETERMINISTIC);
            }
        }
    }

    /**
     * This function emulates the MySQL regexp function for SQLite
     * @param  string  $pattern
     * @param  string  $value
     * @return int
     */
    final public static function regexp(string $pattern, string $value): int
    {
        try {
            return (mb_ereg($pattern, $value)) ? 1 : 0;

        } catch (\ErrorException $e) {
            throw InvalidRegexException::fromMBRegexError($e);
        }
    }

    /**
     * Very similar to the field function, but takes the array values as a comma separated string.
     * This is needed as SQLite has a pretty low argument count limit.
     * @param  string|int|null  $value
     * @param  string  $imploded_array
     * @return int
     */
    final public static function field2(string|int|null $value, string $imploded_array): int
    {
        $array = explode(',', $imploded_array);
        return self::field($value, ...$array);
    }

    /**
     * This function emulates the MySQL field function for SQLite
     * This function returns the index (position) of the first argument in the subsequent arguments.
     * If the first argument is not found or is NULL, 0 is returned.
     * @param  string|int|null  $value
     * @param  mixed  ...$array
     * @return int
     */
    final public static function field(string|int|null $value, ...$array): int
    {
        if ($value === null) {
            return 0;
        }

        //We are loose with the types here
        //@phpstan-ignore-next-line
        $index = array_search($value, $array, false);

        if ($index === false) {
            return 0;
        }

        return $index + 1;
    }
}
