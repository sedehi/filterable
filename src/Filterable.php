<?php
/**
 * Created by PhpStorm.
 * User: Navid Sedehi
 * Email : Navid.sedehi@gmail.com
 * Date: 9/30/16
 * Time: 1:13 AM
 */

namespace Sedehi\Filterable;

trait Filterable
{
    
    private $operator = '=';
    private $clause   = 'where';
    private $append   = false;
    private $column;
    private $value;

    public function scopeFilter($query, array $filter = null){

        if(!is_null($filter)) {
            $this->filterable = $filter;
        }
        if(count(request()->except('page'))) {

            // check for trashed flag in request and if exists update the query
            if (request()->has('trashed') && !is_null(request('trashed'))) {
                if (in_array('Illuminate\Database\Eloquent\SoftDeletes',class_uses($this))) {
                    if (in_array(request('trashed'),['with','only'])) {
                        $query->{request('trashed').'Trashed'}();
                    }
                }
            }
            
            foreach($this->filterable as $key => $value) {
                if(is_numeric($key) && (request()->has($value) && !is_null(request($value)))) {
                    $this->clauseEqual($query, $value);
                }elseif(is_array($value)) {
                    if(isset($value['operator']) && (request()->has($key) && !is_null(request($key)))) {
                        $this->clauseOperator($query, $key, $value);
                    }
                    if(isset($value['scope']) && (request()->has($key) && !is_null(request($key)))) {
                        $this->clauseScope($query, $value);
                    }
                    if(isset($value['between'])) {
                        $this->clauseBetween($query, $key, $value);
                    }
                }
            }
        }
    }

    private function mktime(){

        if(config('filterable.date_type') === 'gregorian') {
            return 'mktime';
        }else {
            if(!function_exists('jmktime')) {
                throw new \Exception('jmktime functions are unavailable');
            }

            return 'jmktime';
        }
    }

    private function convertDate($date, $last = false){

        $mktimeFunction = $this->mktime();
        $dateTime       = [];
        $dateTime[3]    = ($last) ? '23' : '0';
        $dateTime[4]    = ($last) ? '59' : '0';
        $dateTime[5]    = ($last) ? '59' : '0';
        $dateTime       = array_merge(explode(config('filterable.date_divider'), $date), $dateTime);
        $formats        = ['d' => 0, 'm' => 1, 'y' => 2, 'h' => 3, 'i' => 4, 's' => 5];
        if(count($dateTime) == 6) {
            if(!is_null(config('filterable.date_format'))) {
                $formats = array_flip(explode(config('filterable.date_divider'), config('filterable.date_format')));
            }
            $timestamp = $mktimeFunction($dateTime[$formats['h']], $dateTime[$formats['i']], $dateTime[$formats['s']], $dateTime[$formats['m']], $dateTime[$formats['d']], $dateTime[$formats['y']]);

            return date($this->getDateFormat(), $timestamp);
        }

        return false;
    }

    private function clause($query){

        switch($this->clause) {
            case 'where':
                $query->{$this->clause}($this->column, $this->operator, $this->value);
                break;
            case 'whereBetween':
                if(count($this->value) == 2) {
                    $query->{$this->clause}($this->column, $this->value);
                }
                break;
        }
    }

    private function clauseEqual($query, $value){

        $this->column = $value;
        $this->value  = request()->get($value);
        $this->clause($query);
    }

    private function clauseOperator($query, $key, $value){

        $this->column   = $key;
        $this->value    = request()->get($key);
        $this->operator = strtoupper($value['operator']);
        if($this->operator === 'LIKE') {
            $this->value = '%'.$this->value.'%';
        }
        $this->clause($query);
    }

    private function clauseScope($query, $value){

        if(is_array($value['scope'])) {
            foreach($value['scope'] as $scope) {
                $query->{$scope}();
            }
        }else {
            $query->{$value['scope']}();
        }
    }

    private function clauseBetween($query, $key, $value){

        $dates        = array_unique(array_merge(config('filterable.date_fields'), $this->dates));
        $betweenValue = [];
        if(is_array($value['between'])) {
            $this->clause = 'whereBetween';
            $this->column = $key;
            foreach($value['between'] as $vBetween) {
                if(request()->has($vBetween) && !is_null(request($vBetween))) {
                    if(in_array($key, $dates)) {
                        $betweenValue[] = $this->convertDate(request()->get($vBetween), (last($value['between']) == $vBetween) ? true : false);
                    }else {
                        $betweenValue[] = request()->get($vBetween);
                    }
                }
            }
            $this->value = $betweenValue;
            $this->clause($query);
        }
    }

}
