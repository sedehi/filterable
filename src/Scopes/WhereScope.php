<?php

namespace Sedehi\Filterable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhereScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model, $field, $operator, $value)
    {
        return $builder->where($field, $operator, $value);
    }
}
