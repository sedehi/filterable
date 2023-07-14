<?php

namespace Sedehi\Filterable;

use Carbon\Carbon;
use Exception;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Arr;

trait Filterable
{
    private $filterableQuery;

    public function scopeFilter($query, array $filter = null)
    {
        if (! is_null($filter)) {
            $this->filterable = $filter;
        }
        if (is_null($this->filterable)) {
            return $query;
        }

        $casts = collect($this->casts);
        $dates = $casts->filter(function ($value) {
            return $value === 'datetime';
        })->merge(array_flip($this->getDates() + (array) $this->dates))->keys()->toArray();
        $dates = array_unique(array_merge(config('filterable.date_fields'), $dates));

        $this->filterableQuery = $query;
        if (count(request()->except('page'))) {
            foreach ($this->filterable as $filterkey => $filterValue) {
                $scope = $this->getScope($filterkey, $filterValue);
                $operator = $this->getOperator($filterkey, $filterValue);
                $value = $this->getValue($filterkey, $filterValue, $dates, $scope, $operator);
                $column = $this->getColumn($filterkey, $filterValue);

                if ($value === null) {
                    continue;
                }
                if (class_exists($scope)) {
                    $this->filterableQuery->tap(function ($query) use ($value, $scope, $operator, $column) {
                        (new $scope)->apply($query, $query->getModel(), $column, $operator, $value);
                    });
                } elseif ($scope) {
                    if (in_array($scope, ['wherebetween', 'wherein'])) {
                        $this->filterableQuery->tap(function ($query) use ($value, $scope, $column) {
                            $query->{$scope}($column, $value);
                        });
                    } else {
                        $this->filterableQuery->tap(function ($query) use ($value, $scope, $operator, $column) {
                            $query->{$scope}($column, $operator, $value);
                        });
                    }

                }
            }
        }
    }

    private function getValue($filterkey, $filterValue, $dates, &$scope, &$operator)
    {
        $operator = strtoupper($this->getOperator($filterkey, $filterValue));
        $param = is_numeric($filterkey) ? $filterValue : $filterkey;
        if ($scope == 'wherebetween') {
            $params = Arr::flatten($filterValue);
            $firstParam = head($params);
            $secondParam = end($params);
            if (request()->filled($firstParam) && request()->filled($secondParam)) {
                if (in_array($param, $dates)) {
                    return [
                        $this->convertDate(request($firstParam)),
                        $this->convertDate(request($secondParam), true),
                    ];
                } else {
                    return [
                        request($firstParam),
                        request($secondParam),
                    ];
                }
            } elseif (request()->filled($firstParam) && request()->isNotFilled($firstParam)) {
                $scope = 'where';
                $operator = '>=';
                if (in_array($param, $dates)) {
                    return $this->convertDate(request($firstParam));
                } else {
                    return request($firstParam);
                }
            } elseif (request()->isNotFilled($firstParam) && request()->filled($secondParam)) {
                $scope = 'where';
                $operator = '<=';
                if (in_array($param, $dates)) {
                    return $this->convertDate(request($secondParam), true);
                } else {
                    return request($secondParam);
                }
            }
        }
        if (request()->filled($param)) {
            $value = request()->get($param);
            if ($operator === 'LIKE') {
                $value = '%'.$value.'%';
            }

            return $value;
        }

        return null;
    }

    private function getOperator($key, $value): string
    {
        if (is_array($value)) {
            return isset($value['operator']) ? $value['operator'] : '=';
        }

        return '=';
    }

    private function getScope($key, $value): ?string
    {

        if (is_numeric($key)) {
            return 'where';
        } elseif (is_array($value)) {
            $query = Arr::except($value, ['scope', 'operator', 'column']);
            if (isset($value['scope'])) {
                return $value['scope'];
            } elseif ($query) {
                return 'where'.key($query);
            } else {
                return 'where';
            }
        }

        return null;
    }

    private function getColumn($key, $value): string
    {
        if (is_numeric($key)) {
            return $value;
        } elseif (is_array($value)) {
            if (isset($value['column'])) {
                return $value['column'];
            }

            return $key;
        }

        throw new Exception('column not set');
    }

    private function convertDate($date, $last = false)
    {
        $date = $this->convertToEng($date);
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
                $timestamp = Verta::createJalali($dateTime[$formats['y']], $dateTime[$formats['m']], $dateTime[$formats['d']], $dateTime[$formats['h']], $dateTime[$formats['i']], $dateTime[$formats['s']])->toCarbon();
            } else {
                $timestamp = Carbon::create($dateTime[$formats['y']], $dateTime[$formats['m']], $dateTime[$formats['d']], $dateTime[$formats['h']], $dateTime[$formats['i']], $dateTime[$formats['s']]);
            }

            return $timestamp;
        }

        return false;
    }

    protected function convertToEng(string $string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        $num = range(0, 9);
        $convertedPersianNums = str_replace($persian, $num, $string);

        return str_replace($arabic, $num, $convertedPersianNums);
    }
}
