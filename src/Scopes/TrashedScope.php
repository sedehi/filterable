<?php

namespace Sedehi\Filterable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrashedScope implements Scope
{
    public function apply(Builder $query, Model $model, $column, $operator, $value)
    {
        if ($value == 'with') {
            return $query->withTrashed();
        } elseif ($value == 'only') {
            return $query->onlyTrashed();
        }

        return $query;
    }
}
