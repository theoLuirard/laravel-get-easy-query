# Get Easy Query

This package provides a trait that allows to construct query based on a strctured array (from a request for example)
## Features

- Implement a methods to filters, select, sort and apply 'with' methods Eloquent query

## Installation

To install the package, use the following command:

```bash
composer require theoLuirard/laravel-get-easy-query 
```

## Configuration

### Add the trait to your model 

Simply add the trait to your model definition 

```php

<?php

namespace App\Models;

use theoLuirard\getEasyQuery\Traits\getEasyQuery;
use Illuminate\Database\Eloquent\Model;

class MyModel extends Model
{
    use getEasyQuery;

    static array $get_easy_query_accepted_select = ["id", "name", "field_supp", "field_supp3"];

    static array $get_easy_query_accepted_filters = ["id", "name", "field_supp", "another_field"];

    static array $get_easy_query_accepted_relation_filters = [
        "relation1.id" => "relation1_table.id",
        "relation1.subrelation.name" => "subrelation_table.name"
    ];

    static array $get_easy_query_accepted_with = [
        "relation1",
        "relation1.subrelation",
        "relation2",
    ];

    static array $get_easy_query_accepted_order_by = [ // optionnal, it gonna use the $get_easy_query_accepted_select if not define
        "name",
        "field_supp"
    ]
}

```

You should define properties inside your model to limit which parameters can be filtered, selected, with closure applied. 

## Usage

You can get a EloquentQuery with filters, with closure, selected columns defined and sorted easily by passing a structured array as parameters like this   

```php

$query = MyModel::getEasyQuery([
    "select" => [
        "id",
        "name",
        "field_supp",
        "field_supp2"
    ],
    "with" => [
        "relation1",
        "relation1.subrelation2",
        "relation2"
    ]
    "filters" => [
        [
            "field"=> "field_supp3",
            "type"=> "LIKE", 
            "value"=> "my_value%"
        ],[
            "field"=> "relation1.field_1",
            "type"=> "IS NULL", 
            "or" => [
                [
                    "field"=> "relation1.field_1",
                    "type"=> ">", 
                    "value"=> 100
                ]
            ]
        ]
    ], 
    "order_by" => [
        ["field"=>"name", "dir" => "asc"],
        ["field"=>"field_supp"]
    ]

])

```

## Structure of the parameters

### Select 

Accepted_select and select array passed as parameters should be provided as an array, it indicates which of the field of the current model can be requested and will be requested

### Filters 

The filters parameters should be provided as an array of array. In the example provided in usage part, we gonna have a where close looking like this :
```sql
WHERE field_supp3 LIKE 'my_value%' AND (relation1_table_name.field_1 IS NULL OR (relation1_table_name.field_1 > 100 ))
```

The accepted filters are provided in 2 different properties : 
- For the fields of the current model : $get_easy_query_accepted_filters provided as a simple array
- For the fields of relation : $get_easy_query_accepted_relation_filters provided as an associative array, where the key is the path to the field throught the relations and the value is "table_name.field_name"

### With 

Accepted_with and select array passed as parameters should be provided as an array, it indicates which of the field of the current model can be requested and will be requested

## Other Methods 


Apply the select statement to the query (passed by reference)
```php
MyModel::addSelectToQuery($query, $requested_selects) // Return the $query with the select statement applied (filtered using accepted_select property)
```


Apply the filters (where close) to the query (passed by reference)
```php
MyModel::addFilterToQuery($query, $requested_filters) // Return the $query with the where statement applied (filtered using accepted_filters and accepted_relation_filters properties)
```

Apply the with closure (laravel with) to the query (passed by reference)
```php
MyModel::addWithToQuery($query, $requested_with) // Return the $query with the laravel with method applied  applied (filtered using accepted_with property)
```

Apply the order by close to the query (passed by reference)
```php
MyModel::addOrderByToQuery($query, $requested_order_by) // Return the $query with the order by statement applied (filtered using accepted_order_by property)
```

