<?php
declare(strict_types=1);

namespace Tests\Support;

final class DbResultFactory
{
    public static function empty(): object
    {
        $r = new \stdClass();
        $r->rows     = [];
        $r->row      = [];
        $r->num_rows = 0;
        return $r;
    }

    public static function one(array $row): object
    {
        $r = new \stdClass();
        $r->rows     = [$row];
        $r->row      = $row;
        $r->num_rows = 1;
        return $r;
    }

    public static function many(array $rows): object
    {
        $r = new \stdClass();
        $r->rows     = $rows;
        $r->row      = $rows[0] ?? [];
        $r->num_rows = count($rows);
        return $r;
    }
}
