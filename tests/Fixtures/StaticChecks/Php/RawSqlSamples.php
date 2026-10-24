<?php

namespace Tests\Fixtures\StaticChecks;

use App\Database\Identifier;
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
    DB::statement('GRANT SELECT ON TABLE sample_items TO '.Identifier::quote($name).$name);
    DB::statement('GRANT SELECT ON TABLE sample_items TO '.strtolower($name));
    DB::statement('select ? from '.Identifier::quote($name));
    DB::statement("GRANT SELECT ON TABLE sample_items TO {$name}");
}

function rawSqlBound(string $name, Builder $query): void
{
    DB::select('select id from sample_items where name = ?', [$name]);
    DB::select('select id::text from sample_items where name = :name', ['name' => $name]);
    $query->whereRaw('lower(name) = ?', [$name]);
    $query->select('id');
    DB::raw('count(*)');
    DB::statement("select 'a?b'");
    DB::statement('GRANT SELECT ON TABLE sample_items TO '.Identifier::quote($name));
    DB::select('select ? from '.Identifier::quote($name), [$name]);
}
