Doctrine Orm Service Provider
-----------------------------
[![Build Status](https://travis-ci.org/inbep/doctrine-orm-service-provider.svg?branch=master)](https://travis-ci.org/inbep/doctrine-orm-service-provider)

Install
-------
```bash
composer install inbep/doctrine-orm-service-provider "dev-master"
```

How to use
----------
Something like this
```php
use Silex\Provider\DoctrineServiceProvider;
use Inbep\Silex\Provider\DoctrineOrmServiceProvider();
use Inbep\Silex\Provider\DoctrineCacheServiceProvider();

$app->register(new DoctrineServiceProvider(), [
    // your db config
]);
$app->register(new DoctrineCacheServiceProvider());
$app->register(new DoctrineOrmServiceProvider(), [
    'orm.proxy_dir' => '',
    'orm.proxy_namespace' => ''
]);

$app['orm']->getRepository('Namespace\Entity\User')->findAll();
```

License
-------
MIT
