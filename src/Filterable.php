<?php

namespace Sedehi\Filterable;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

trait Filterable
{
    private $operator = '=';

    private $clause = 'where';

    private $append = false;

    private $column;

    private $value;

    public function scopeFilter($query, array $filter = null)
    {
        if (! is_null($filter)) {
            $this->filterable = $filter;
        }
        if (is_null($this->filterable)) {
            return $query;
        }
        if (count(request()->except('page'))) {
            $this->applyTrashedFilter($query);
            foreach ($this->filterable as $key => $value) {
                if (is_numeric($key) && (request()->has($value) && ! is_null(request($value)))) {
                    $this->applyClauseEqual($query, $value);
                } elseif (is_array($value)) {
                    if (isset($value['operator']) && (request()->has($key) && ! is_null(request($key)))) {
                        $this->applyClauseOperator($query, $key, $value);
                    }
                    if (isset($value['scope']) && (request()->has($key) && ! is_null(request($key)))) {
                        $this->applyClauseScope($query, $value);
                    }
                    if (isset($value['between'])) {
                        $this->applyClauseBetween($query, $key, $value);
                    }
                }
                $this->column = null;
                $this->value = null;
            }
        }
    }

    private function applyTrashedFilter($query)
    {
        if (request()->has('trashed') && ! is_null(request('trashed'))) {
            if (in_array(SoftDeletes::class, class_uses($this))) {
                if (in_array(request('trashed'), ['with', 'only'])) {
                    $query->{request('trashed').'Trashed'}();
                }
            }
        }
    }

    private function applyClauseEqual($query, $value)
    {
        $scopeClass = $this->scope('where');
        $query->tap(function ($query) use ($value, $scopeClass) {
            (new $scopeClass)->apply($query, $query->getModel(), $value, '=', request()->get($value));
        });
    }

    private function scope($name)
    {
        return config('filterable.scopes.'.$name);
    }

    private function applyClauseOperator($query, $key, $value)
    {
        $scopeClass = $this->scope('where');
        $query->tap(function ($query) use ($value, $key, $scopeClass) {
            $operator = strtoupper($value['operator']);
            $value = request()->get($key);
            if ($operator === 'LIKE') {
                $value = '%'.$value.'%';
            }
            (new $scopeClass)->apply($query, $query->getModel(), $key, $operator, $value);
        });
    }

    private function applyClauseScope($query, $value)
    {
        if (is_array($value['scope'])) {
            foreach ($value['scope'] as $scope) {
                $query->{$scope}();
            }
        } else {
            $query->{$value['scope']}();
        }
    }

    private function applyClauseBetween($query, $key, $value)
    {
        if (is_array($value['between'])) {
            $this->clause = 'whereBetween';
            $this->column = $key;
            if ((request()->has($value['between'][0]) && ! is_null(request($value['between'][0]))) && (request()->has($value['between'][1]) && ! is_null(request($value['between'][1])))) {
                $this->setPropertiesByType('both', $key, $value);
            } elseif ((request()->has($value['between'][0]) && ! is_null(request($value['between'][0]))) && (! request()->has($value['between'][1]) || is_null(request($value['between'][1])))) {
                $this->setPropertiesByType('first', $key, $value);
            } elseif ((! request()->has($value['between'][0]) || is_null(request($value['between'][0]))) && (request()->has($value['between'][1]) && ! is_null(request($value['between'][1])))) {
                $this->setPropertiesByType('second', $key, $value);
            }
            $this->applyClause($query);
        }
    }

    private function applyClause($query)
    {
        switch ($this->clause) {
            case 'where':
                $query->{$this->clause}($this->column, $this->operator, $this->value);
                break;
            case 'whereBetween':
                if (count((array) $this->value) == 2) {
                    $query->{$this->clause}($this->column, $this->value);
                }
                break;
        }
    }

    private function setPropertiesByType($type, $key, $value)
    {
        $casts = collect($this->casts);
        $dates = $casts->filter(function ($value) {
            return $value === 'datetime';
        })->merge(array_flip($this->getDates() + (array) $this->dates))->keys()->toArray();
        $dates = array_unique(array_merge(config('filterable.date_fields'), $dates));
        switch ($type) {
            case 'both':
                if (in_array($key, $dates)) {
                    $this->value = [
                        $this->convertDate(request($value['between'][0])),
                        $this->convertDate(request($value['between'][1]), true),
                    ];
                } else {
                    $this->value = [
                        request($value['between'][0]),
                        request($value['between'][1]),
                    ];
                }
                break;
            case 'first':
                $this->clause = 'where';
                $this->operator = '>=';
                if (in_array($key, $dates)) {
                    $this->value = $this->convertDate(request($value['between'][0]));
                } else {
                    $this->value = request($value['between'][0]);
                }
                break;
            case 'second':
                $this->clause = 'where';
                $this->operator = '<=';
                if (in_array($key, $dates)) {
                    $this->value = $this->convertDate(request($value['between'][1]), true);
                } else {
                    $this->value = request($value['between'][1]);
                }
                break;
        }
    }

    private function convertDate($date, $last = false)
    {
        $date = CalendarUtils::convertNumbers($date, true);
        $dateTime = [];
        $dateTime[3] = ($last) ? '23' : '0';
        $dateTime[4] = ($last) ? '59' : '0';
        $dateTime[5] = ($last) ? '59' : '0';
        $dateTime = array_merge(explode(config('filterable.date_divider'), $date), $dateTime);
        $formats = ['d' => 0, 'm' => 1, 'y' => 2, 'h' => 3, 'i' => 4, 's' => 5];
        if (count($dateTime) == 6) {
            if (! is_null(config('filterable.date_format'))) {
                $formats = array_flip(explode(config('filterable.date_divider'), config('filterable.date_format')));
            }
            if (config('filterable.date_type') == 'jalali') {
                $timestamp = (new Jalalian($dateTime[$formats['y']], $dateTime[$formats['m']], $dateTime[$formats['d']], $dateTime[$formats['h']], $dateTime[$formats['i']], $dateTime[$formats['s']]))->getTimestamp();
            } else {
                $timestamp = Carbon::create($dateTime[$formats['y']], $dateTime[$formats['m']], $dateTime[$formats['d']], $dateTime[$formats['h']], $dateTime[$formats['i']], $dateTime[$formats['s']])
                    ->getTimestamp();
            }

            return date($this->getDateFormat(), $timestamp);
        }

        return false;
    }
}
