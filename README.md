# Laravel Filterable [![Latest Stable Version](https://poser.pugx.org/sedehi/filterable/v/stable)](https://packagist.org/packages/sedehi/filterable) [![Total Downloads](https://poser.pugx.org/sedehi/filterable/downloads)](https://packagist.org/packages/sedehi/filterable) 


## Installation

To get the latest version, simply require the project using [Composer](https://getcomposer.org):
	
```php
composer require sedehi/filterable
```

If you are using **Laravel >=5.5** the service provider will be automatically discovered otherwise we need to add the filterable service provider to `config/app.php` under the providers key:

```php
Sedehi\Filterable\FilterableServiceProvider::class,
```
