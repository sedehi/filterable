# Laravel Filterable 
[![Latest Stable Version](https://poser.pugx.org/sedehi/filterable/v/stable)](https://packagist.org/packages/sedehi/filterable) 
![Packagist Downloads (custom server)](https://img.shields.io/packagist/dm/sedehi/filterable)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/sedehi/filterable/run-tests.yml?branch=v3&label=Tests)](https://github.com/sedehi/filterable/actions/workflows/run-tests.yml)

## Introduction
The `Sedehi/Filterable` package is designed for performing searches on models based on query strings found in requests. It allows you to easily filter model data according to the query parameters provided in HTTP requests.


## Installation

To get the latest version, simply require the project using [Composer](https://getcomposer.org):
	
```php
composer require sedehi/filterable
```

If you are using **Laravel >=5.5** the service provider will be automatically discovered otherwise we need to add the filterable service provider to `config/app.php` under the providers key:

```php
Sedehi\Filterable\FilterableServiceProvider::class,
```

## Usage

The first step in using the Sedehi/Filterable package in your Laravel application is to add the `Sedehi\Filterable\Filterable` trait to any model on which you want to perform filtering.

Below is an example of a model with the Filterable trait:

```php
<?php

namespace App\Models;

use Sedehi\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Filterable;

    public $filterable = [
        'title',
    ];

    // Your model's other properties and methods go here...
}
```

## Applying Filtering in Queries

Finally, to apply filtering in any query where you need to execute filterable, add the `filter` scope as follows:

```php
Product::filter()->where('active', 1)->get();
```


## Defining Filterable Fields

To specify which fields can be filtered and the type of search operation available on those fields, you need to define an array named `$filterable` within your model. In this array, you list the names of the fields that should be filterable and specify the filter operation for each field.

In the example above, we have defined that the 'title' field can be filtered.

You can add more fields to the `$filterable` array as needed for your model.

Here's a guide on how to filter data using the filterable feature:


```php
$filterable = [
    'title',
];

$request = ['title' => 'some text']
```
SQL Output:
```sql
SELECT * from product where `title` = 'some text'
```
