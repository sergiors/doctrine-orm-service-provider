Doctrine Orm Service Provider
-----------------------------
[![Build Status](https://travis-ci.org/inbep/doctrine-orm-service-provider.svg?branch=master)](https://travis-ci.org/inbep/doctrine-orm-service-provider)

Install
-------
```bash
composer install inbep/doctrine-orm-service-provider
```

How to use
----------
Something like this
```php
$app->register(Inbep\Silex\Provider\DoctrineOrmServiceProvider(), [
    'orm.proxy_dir' => '',
    'orm.proxy_namespace' => ''
]);

$app['orm']->getRepository('Namespace\Entity\User')->findAll();
```

License
-------
MIT
