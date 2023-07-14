# Laravel Filterable 
[![Latest Stable Version](https://poser.pugx.org/sedehi/filterable/v/stable)](https://packagist.org/packages/sedehi/filterable) 
![Packagist Downloads (custom server)](https://img.shields.io/packagist/dm/sedehi/filterable)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/sedehi/filterable/run-tests.yml?branch=v3&label=Tests)](https://github.com/sedehi/filterable/actions/workflows/run-tests.yml)


## Installation

To get the latest version, simply require the project using [Composer](https://getcomposer.org):
	
```php
composer require sedehi/filterable
```

If you are using **Laravel >=5.5** the service provider will be automatically discovered otherwise we need to add the filterable service provider to `config/app.php` under the providers key:

```php
Sedehi\Filterable\FilterableServiceProvider::class,
```
