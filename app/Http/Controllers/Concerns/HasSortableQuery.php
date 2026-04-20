<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasSortableQuery
{
    /**
     * Apply a sort/direction from the request to an Eloquent query builder.
     * Only columns in the whitelist are honored; anything else silently
     * falls through to the provided default ORDER BY.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string>  $columns  Map of request-param name => DB column expression
     * @param  string|array<int, array{0: string, 1: string}>  $default  Default order applied when no sort or invalid sort
     */
    protected function applySort(Builder $query, Request $request, array $columns, string|array $default = 'id,desc'): Builder
    {
        $sort = $request->input('sort');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort && array_key_exists($sort, $columns)) {
            $query->orderBy($columns[$sort], $direction);

            return $query;
        }

        // Fall back to default
        if (is_string($default)) {
            [$col, $dir] = array_pad(explode(',', $default), 2, 'asc');
            $query->orderBy($col, $dir);
        } else {
            foreach ($default as [$col, $dir]) {
                $query->orderBy($col, $dir);
            }
        }

        return $query;
    }
}
