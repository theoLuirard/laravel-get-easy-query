<?php

namespace theoLuirard\getEasyQuery\Traits;

use Error;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder as DatabaseEloquentBuilder;
use Illuminate\Database\Query\Builder;
use theoLuirard\getEasyQuery\Errors\NotAcceptedPropertyError;
use theoLuirard\getEasyQuery\Errors\NotProvidedPropertyError;
use theoLuirard\getEasyQuery\Exceptions\NotAcceptedFilterParametersException;
use theoLuirard\getEasyQuery\Exceptions\NotAcceptedOrderByParametersException;
use theoLuirard\getEasyQuery\Exceptions\NotAcceptedSelectParametersException;
use theoLuirard\getEasyQuery\Exceptions\NotAcceptedWithParametersException;

/*
|--------------------------------------------------------------------------
|   GetEasyQuery
|--------------------------------------------------------------------------
|
| Define trait to get a query to select what needed from paramters 
| Define a new static function getEasyQuery
|
*/

trait GetEasyQuery
{

    /**
     * Define the default operators accepted for filtering 
     */
    public static $default_accepted_operators = [
        "=",
        "!=",
        ">",
        ">=",
        "<",
        "<=",
        "<=>",
        "<>",
        "IS NULL",
        "IS NOT NULL",
        "IN",
        "NOT IN",
        "LIKE",
        "NOT LIKE"
    ];

    /**
     * Get the query applying filter, select and with clause 
     * @return  DatabaseEloquentBuilder|Builder 
     */
    public static function getEasyQuery(array $parameters = []): DatabaseEloquentBuilder|Builder
    {

        // create query 
        $query = self::on();
        /* 
            Manage select 
        */
        $requested_selects = isset($parameters["select"]) ? $parameters["select"] : null;
        self::addSelectToQuery($query, $requested_selects);

        // Add with clauses 
        $requested_with = isset($parameters["with"]) ? $parameters["with"] : null;
        self::addWithToQuery($query, $requested_with);

        // Construct where clauses 
        $requested_filters = isset($parameters["filters"]) ? $parameters["filters"] : null;
        self::addFilterToQuery($query, $requested_filters);

        // Add Order by clauses
        $requested_order_by = isset($parameters["order_by"]) ? $parameters["order_by"] : [];
        self::addOrderByToQuery($query, $requested_order_by);

        
        return $query;
    }

    /**
     * Add fitlers to the provided query 
     * @param DatabaseEloquentBuilder|Builder $query
     * @param array $requested_selects
     * @return  DatabaseEloquentBuilder|Builder 
     */
    public static function addSelectToQuery(DatabaseEloquentBuilder|Builder &$query, array|null $requested_selects = null): DatabaseEloquentBuilder|Builder
    {

        // Get accepted select
        $accepted_select = self::getAcceptedSelect();
        /* 
            Manage select 
        */
        $select = [];
        // Map accepted select values for requested values
        if (isset($requested_selects)) {
            foreach ($requested_selects as $requested_select) {
                if (in_array($requested_select, $accepted_select)) {
                    array_push($select, $requested_select);
                } else {
                    throw new NotAcceptedSelectParametersException(
                        self::class,
                        $requested_select,
                        500
                    );
                }
            };
        }
        $query = self::select(count($select) > 0 ? $select : '*');

        return $query;
    }

    /**
     * Add fitlers to the provided query 
     * @param DatabaseEloquentBuilder|Builder $query
     * @param array $requested_filters
     * @return  DatabaseEloquentBuilder|Builder 
     */
    public static function addFilterToQuery(DatabaseEloquentBuilder|Builder &$query, array|null $requested_filters = []): DatabaseEloquentBuilder|Builder
    {

        // Get accepted parameters

        $accepted_filters = self::getAcceptedFilters();

        $accepted_relation_filters = self::getAcceptedRelationFilters();

        $accepted_operators = self::getAcceptedOperators();

        /*
            Look for filters 
        */

        $extractRelationFunction = function ($string, $needle = ".") {
            $parts = explode($needle, $string);
            $last = array_pop($parts);
            $relation = implode($needle, $parts);
            $item = array(implode($needle, $parts), $last);

            return [
                "field" => $last,
                "item" => $item,
                "relation" => $relation
            ];
        };

        // Construct where close 
        if (isset($requested_filters)) {

            // Function that add a filter
            $addFilterToQuery = function ($filter, &$query) use ($accepted_filters, $accepted_operators, &$addFilterToQuery, $accepted_relation_filters, $extractRelationFunction) {
                if (in_array($filter["field"], $accepted_filters) && in_array(strtoupper($filter["type"]), $accepted_operators)) {

                    // Get parameters for where clause
                    $field = $filter["field"];

                    $qualifiedField = $query->getModel()->qualifyColumn($field);
                    $type = isset($filter["type"]) ? strtoupper($filter["type"]) : "=";
                    $value = $filter["value"] ?? null;

                    // Manage specific where clause 
                    if ($type === "IS NULL") {
                        $query->whereNull($qualifiedField);
                    } else if ($type === "IS NOT NULL") {
                        $query->whereNotNull($qualifiedField);
                    } else if ($type === "IN" && isset($value)) {
                        $query->whereIn($qualifiedField, $value);
                    } else if (isset($value)) {
                        $query->where($qualifiedField, $type, $value);
                    }
                } else if (isset($accepted_relation_filters[$filter["field"]]) && in_array(strtoupper($filter["type"]), $accepted_operators)) {
                    
                    
                    /* SPECIFIC USE CASE */
                    /*
                        HasMany relationship filters -> should use whereHas method 
                    */
                    $raw_field = $filter["field"];
                    list('relation' => $relation, "field" => $field) = $extractRelationFunction($raw_field);
                    $query->whereHas($relation, function (Builder|EloquentBuilder $query) use ($filter, $raw_field, $field, $accepted_relation_filters) {


                        $qualifiedField = $query->getModel()->qualifyColumn($field);
                        $type = isset($filter["type"]) ? strtoupper($filter["type"]) : "=";
                        $value = $filter["value"];

                        // Manage specific where clause 
                        if ($type === "IS NULL") {
                            $query->whereNull($qualifiedField);
                        } else if ($type === "IS NOT NULL") {
                            $query->whereNotNull($qualifiedField);
                        } else if ($type === "IN" && isset($value)) {
                            $query->whereIn($qualifiedField, $value);
                        } else {
                            $query->where($qualifiedField, $type, $value);
                        }
                    });


                } else {
                    throw new NotAcceptedFilterParametersException(
                        self::class,
                        $filter["field"],
                        500
                    );
                }
            };


            // Function that manage and & or where clause (using previous function)
            $addFiltersToQuery = function ($filters, &$query) use (&$addFiltersToQuery, $addFilterToQuery) {
                if (isset($filters)) {
                    foreach ($filters as $filter) {
                        if (isset($filter["or"]) && count($filter["or"]) > 0) {

                            // If "or" we encapsulate the first filter and the "or" filter in where function callback to put them into bracket in order to group them 
                            $query->where(function (Builder|DatabaseEloquentBuilder $query) use ($addFilterToQuery, $addFiltersToQuery, $filter) {
                                $addFilterToQuery($filter, $query);
                                $query->orWhere(function (Builder|DatabaseEloquentBuilder $query) use ($addFilterToQuery, $addFiltersToQuery, $filter) {
                                    $addFiltersToQuery($filter["or"], $query);
                                });
                            });
                        } else {
                            $addFilterToQuery($filter, $query);
                        }
                    }
                }
            };


            // Execute function
            $addFiltersToQuery($requested_filters, $query);
        }

        return $query;
    }

    /**
     * Add with clause to the provided query 
     * @param DatabaseEloquentBuilder|Builder &$query
     * @param array $requested_with
     * @return  DatabaseEloquentBuilder|Builder 
     */
    public static function addWithToQuery(DatabaseEloquentBuilder|Builder &$query, array|null $requested_with = null): DatabaseEloquentBuilder|Builder
    {
        // get accepted with
        $accepted_with = self::getAcceptedWith();

        /* 
            Manage with
        */
        $with = [];
        $withCount = [];
        if (isset($requested_with)) {
            // ensure requested_with is an array
            if (!is_array($requested_with)) {
                $requested_with = [$requested_with];
            }

            // Map accepted with values for requested values
            foreach ($requested_with as $r_with) {
                if (in_array($r_with, $accepted_with)) {

                    if (str_ends_with($r_with, '.count')) {
                        array_push(
                            $withCount,
                            rtrim($r_with, '.count')
                        );
                    } else {
                        array_push($with, $r_with);
                    }
                } else {
                    throw new NotAcceptedWithParametersException(
                        self::class,
                        $r_with,
                        500
                    );
                }
            }
        }
        $query->with($with);
        $query->withCount($withCount);
        return $query;
    }

    /**
     * Add order by clause to the provided query 
     * @param DatabaseEloquentBuilder|Builder &$query
     * @param array $requested_order_by
     * @return  DatabaseEloquentBuilder|Builder 
     */
    public static function addOrderByToQuery(DatabaseEloquentBuilder|Builder &$query, array|null $requested_order_by = []): DatabaseEloquentBuilder|Builder
    {

        // get accepted order by
        $accepted_order_by = self::getAcceptedOrderBy();

        /* 
            Manage order by
        */
        if (isset($requested_order_by)) {

            // ensure $requested_order_by is array 
            if (!is_array($requested_order_by)) {
                $requested_order_by = [$requested_order_by];
            }
            $order_by = [];
            // Map accepted order_by values for requested values
            foreach ($requested_order_by as $requested_o_by) {
                if (in_array($requested_o_by['field'], $accepted_order_by)) {
                    array_push($order_by, $requested_o_by);
                } else {
                    throw new NotAcceptedOrderByParametersException(
                        self::class,
                        $requested_o_by['field'],
                        500
                    );
                }
            }

            // apply the order by clauses
            if (count($order_by) > 0) {
                foreach ($order_by as $o_by) {
                    if (str_contains($o_by["field"], '.')) {
                        $parts = explode('.', $o_by["field"]);
                        $parts_length = count($parts);
                        $relation = "";
                        $aggregate_field = "";
                        foreach ($parts as $index => $part) {
                            if ($index < $parts_length - 1) {
                                $relation .= ($relation == "" ? "" : ".") . $part;
                            }
                            $aggregate_field .= ($aggregate_field == "" ? "" : "_") . $part;
                        }

                        $query->withAggregate($relation, $parts[$parts_length - 1]);
                        $field = $aggregate_field;
                    } else {
                        $field = $o_by["field"];
                    }
                    $query = $query->orderBy($field, strtolower($o_by["dir"]) == "desc" ? "desc" : "asc");
                }
            }
        }


        return $query;
    }


    /**
     * Get the accepted select array
     * 
     * @return array
     */
    public static function getAcceptedSelect(): array
    {

        // Looks if defined
        if (!isset(static::$get_easy_query_accepted_select)) {
            throw new NotProvidedPropertyError(static::class, '$accepted_select', 500);
        }
        // Looks for correcte format -> array
        if (!is_array(static::$get_easy_query_accepted_select)) {
            throw new NotAcceptedPropertyError(static::class, '$accepted_select', gettype(static::$get_easy_query_accepted_select), ['array'], 500);

        }

        return static::$get_easy_query_accepted_select;
    }

    /**
     * Get the accepted filters array
     * 
     * @return array
     */
    public static function getAcceptedFilters(): array
    {

        // Looks if defined
        if (!isset(static::$get_easy_query_accepted_filters)) {
            throw new NotProvidedPropertyError(static::class, '$accepted_filters', 500);
        }
        // Looks for correcte format -> array
        if (!is_array(static::$get_easy_query_accepted_filters)) {
            throw new NotAcceptedPropertyError(static::class, '$accepted_relation_filters', gettype(static::$get_easy_query_accepted_filters), ['array'], 500);
        }

        return static::$get_easy_query_accepted_filters;
    }

    /**
     * Get the accepted filters array (specific for relationship)
     * 
     * @return array
     */
    public static function getAcceptedRelationFilters(): array
    {

        // Looks if defined
        if (!isset(static::$get_easy_query_accepted_relation_filters)) {
            throw new NotProvidedPropertyError(static::class, '$accepted_relation_filters', 500);
        }
        // Looks for correcte format -> array
        if (!is_array(static::$get_easy_query_accepted_relation_filters)) {
            throw new NotAcceptedPropertyError(static::class, '$accepted_relation_filters', gettype(static::$get_easy_query_accepted_relation_filters), ['array'], 500);

        }

        return static::$get_easy_query_accepted_relation_filters;
    }

    /**
     * Get the accepted order by array
     * 
     * @return array
     */
    public static function getAcceptedOrderBy(): array
    {

        // Looks if defined
        if (!isset(static::$get_easy_query_accepted_order_by)) {
            try {
                return static::getAcceptedSelect();
            } catch (Error $e) {
                throw new NotProvidedPropertyError(static::class, '$accepted_order_by', 500);
            }
        }
        // Looks for correcte format -> array
        if (!is_array(static::$get_easy_query_accepted_order_by)) {
            throw new NotAcceptedPropertyError(static::class, '$accepted_order_by', gettype(static::$accepted_order_by), ['array'], 500);
        }

        return static::$get_easy_query_accepted_order_by;
    }

    /**
     * Get the accepted with array
     * 
     * @return array
     */
    public static function getAcceptedWith(): array
    {

        // Looks if defined
        if (!isset(static::$get_easy_query_accepted_with)) {
            throw new NotProvidedPropertyError(static::class, '$accepted_with', 500);
        }
        // Looks for correcte format -> array
        if (!is_array(static::$get_easy_query_accepted_with)) {
           throw new NotAcceptedPropertyError(static::class, '$accepted_with', gettype(static::$accepted_with), ['array'], 500);
        }

        return static::$get_easy_query_accepted_with;
    }

    /**
     * Get the accepted operators array
     * 
     * @return array
     */
    public static function getAcceptedOperators(): array
    {

        // Looks if defined
        if (isset(self::$accepted_operators)) {

            // Looks for correcte format -> array
            if (!is_array(static::$get_easy_query_accepted_operators)) {
                throw new NotAcceptedPropertyError(static::class, '$accepted_with', gettype(static::$accepted_operators), ['array'], 500);
            } else {
                return static::$get_easy_query_accepted_operators;
            }
        } else { // return default values 
            return static::$default_accepted_operators;
        }
    }
}
