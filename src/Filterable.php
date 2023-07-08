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

    private $filterableQuery;

    public function scopeFilter($query, array $filter = null)
    {
        if (! is_null($filter)) {
            $this->filterable = $filter;
        }
        if (is_null($this->filterable)) {
            return $query;
        }
        $this->filterableQuery = $query;
        if (count(request()->except('page'))) {
            $this->applyTrashedFilter();
            foreach ($this->filterable as $key => $value) {
                if (is_numeric($key) && (request()->has($value) && ! is_null(request($value)))) {
                    $this->applyClauseEqual($value);
                } elseif (is_array($value)) {
                    if (isset($value['operator']) && (request()->has($key) && ! is_null(request($key)))) {
                        $this->applyClauseOperator($key, $value);
                    }
                    if (isset($value['scope']) && (request()->has($key) && ! is_null(request($key)))) {
                        $this->applyClauseScope($value);
                    }
                    if (isset($value['between'])) {
                        $this->applyClauseBetween($key, $value);
                    }
                }
                $this->column = null;
                $this->value = null;
            }
        }
    }

    private function applyTrashedFilter()
    {
        if (request()->has('trashed') && ! is_null(request('trashed'))) {
            if (in_array(SoftDeletes::class, class_uses($this))) {
                if (in_array(request('trashed'), ['with', 'only'])) {
                    $this->filterableQuery->{request('trashed').'Trashed'}();
                }
            }
        }
    }

    private function applyClauseEqual($value)
    {
        $scopeClass = $this->scope('where');
        $this->filterableQuery->tap(function ($query) use ($value, $scopeClass) {
            (new $scopeClass)->apply($query, $query->getModel(), $value, '=', request()->get($value));
        });
    }

    private function scope($name)
    {
        return config('filterable.scopes.'.$name);
    }

    private function applyClauseOperator($key, $value)
    {
        $scopeClass = $this->scope('where');
        $this->filterableQuery->tap(function ($query) use ($value, $key, $scopeClass) {
            $operator = strtoupper($value['operator']);
            $value = request()->get($key);
            if ($operator === 'LIKE') {
                $value = '%'.$value.'%';
            }
            (new $scopeClass)->apply($query, $query->getModel(), $key, $operator, $value);
        });
    }

    private function applyClauseScope($value)
    {
        if (is_array($value['scope'])) {
            foreach ($value['scope'] as $scope) {
                $this->filterableQuery->{$scope}();
            }
        } else {
            $this->filterableQuery->{$value['scope']}();
        }
    }

    private function applyClauseBetween($key, $value)
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
            $this->applyClause();
        }
    }

    private function applyClause()
    {
        switch ($this->clause) {
            case 'where':
                $this->filterableQuery->{$this->clause}($this->column, $this->operator, $this->value);
                break;
            case 'whereBetween':
                if (count((array) $this->value) == 2) {
                    $this->filterableQuery->{$this->clause}($this->column, $this->value);
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
