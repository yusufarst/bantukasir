<?php

namespace Tests\Fixtures\StaticChecks;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

// Negative samples of the raw-SQL rule (TR-VAL-07). Never executed.

function rawSqlFromInput(string $name, Builder $query): void
{
    DB::select("select id from sample_items where name = '$name'");
    DB::statement('select id from sample_items where name = '.$name);
    DB::raw($name);
    new Expression($name);
    $query->whereRaw('name = ?');
    $query->orderByRaw($name);
    DB::raw('lower(name) = ?');
}

function rawSqlBound(string $name, Builder $query): void
{
    DB::select('select id from sample_items where name = ?', [$name]);
    DB::select('select id::text from sample_items where name = :name', ['name' => $name]);
    $query->whereRaw('lower(name) = ?', [$name]);
    $query->select('id');
    DB::raw('count(*)');
    DB::statement("select 'a?b'");
}
