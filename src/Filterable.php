<?php

namespace Sedehi\Filterable;

use Carbon\Carbon;
use Exception;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Arr;

trait Filterable
{
    private $filterableQuery;

    /**
     * Apply filters to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query, array $filter = null)
    {
        if (! is_null($filter)) {
            $this->filterable = $filter;
        }
        if (is_null($this->filterable)) {
            return $query;
        }

        $dates = array_unique(array_merge(config('filterable.date_fields'), $this->getFilterableDates()));

        $this->filterableQuery = $query;
        $requestData = request()->except('page');
        if (count($requestData)) {
            foreach ($this->filterable as $filterKey => $filterValue) {
                $scope = $this->getScope($filterKey, $filterValue);
                $operator = $this->getOperator($filterKey, $filterValue);
                $value = $this->getValue($filterKey, $filterValue, $dates, $scope, $operator);
                $column = $this->getColumn($filterKey, $filterValue);

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

        return $this->filterableQuery;
    }

    /**
     * Get the filterable date fields.
     *
     * @return array
     */
    private function getFilterableDates()
    {
        $casts = collect($this->casts);
        $dates = $casts->filter(function ($value) {
            return $value === 'datetime';
        })->merge(array_flip($this->getDates() + (array) $this->dates))->keys()->toArray();

        return $dates;
    }

    /**
     * Get the value for a filter key.
     *
     * @param  string|int  $filterKey
     * @param  mixed  $filterValue
     * @param  array  $dates
     * @param  string  $scope
     * @param  string  $operator
     * @return mixed|null
     */
    private function getValue($filterKey, $filterValue, $dates, &$scope, &$operator)
    {
        $operator = strtoupper($this->getOperator($filterKey, $filterValue));
        $param = is_numeric($filterKey) ? $filterValue : $filterKey;
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

    /**
     * Get the operator for a filter key.
     *
     * @param  string|int  $key
     * @param  mixed  $value
     */
    private function getOperator($key, $value): string
    {
        if (is_array($value)) {
            return isset($value['operator']) ? $value['operator'] : '=';
        }

        return '=';
    }

    /**
     * Get the scope for a filter key.
     *
     * @param  string|int  $key
     * @param  mixed  $value
     */
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

    /**
     * Get the column for a filter key.
     *
     * @param  string|int  $key
     * @param  mixed  $value
     *
     * @throws \Exception
     */
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

    /**
     * Convert a date to the appropriate format.
     *
     * @param  string  $date
     * @param  bool  $last
     * @return bool|\Carbon\Carbon|\Hekmatinasser\Verta\Verta
     */
    private function convertDate(string $date, $last = false)
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
                $timestamp = Verta::createJalali(
                    $dateTime[$formats['y']],
                    $dateTime[$formats['m']],
                    $dateTime[$formats['d']],
                    $dateTime[$formats['h']],
                    $dateTime[$formats['i']],
                    $dateTime[$formats['s']]
                )->toCarbon();
            } else {
                $timestamp = Carbon::create(
                    $dateTime[$formats['y']],
                    $dateTime[$formats['m']],
                    $dateTime[$formats['d']],
                    $dateTime[$formats['h']],
                    $dateTime[$formats['i']],
                    $dateTime[$formats['s']]
                );
            }

            return $timestamp;
        }

        return false;
    }

    /**
     * Convert Persian or Arabic numerals to English numerals.
     */
    protected function convertToEng(string $string): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        $num = range(0, 9);
        $convertedPersianNums = str_replace($persian, $num, $string);

        return str_replace($arabic, $num, $convertedPersianNums);
    }
}
