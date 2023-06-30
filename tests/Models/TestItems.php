<?php

namespace Sedehi\Filterable\Test\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sedehi\Filterable\Filterable;

class TestItems extends Model
{
    use Filterable, SoftDeletes;

    public $table = 'items';

    public $filterable = [
        'title',
        'number' => [
            'operator' => '>',
        ],
        'custom' => [
            'scope' => 'CustomScopeSearch',
        ],
        'created_at' => [
            'between' => [
                'start_created',
                'end_created',
            ],
        ],
    ];

    public function scopeCustomScopeSearch($query)
    {

        return $query->whereNotNull('id');
    }
}
