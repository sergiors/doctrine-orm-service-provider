<?php

namespace Sergiors\Silex\Provider;

use Silex\Application;
use Silex\WebTestCase;
use Silex\Provider\DoctrineServiceProvider;

class DoctrineOrmServiceProviderTest extends WebTestCase
{
    /**
     * @test
     */
    public function singleConnection()
    {
        $app = $this->createApplication();
        $app->register(new DoctrineServiceProvider());
        $app->register(new DoctrineCacheServiceProvider());
        $app->register(new DoctrineOrmServiceProvider());
        $app['orm.proxy_namespace'] = 'Proxy';
        $app['orm.proxy_dir'] = __DIR__;
        $app['orm.options'] = [
            'mappings' => [
                [
                    'type' => 'annotation',
                    'namespace' => 'Foo\Entity',
                    'path' => __DIR__,
                ],
            ],
        ];

        $orm = $app['orm'];
        $this->assertSame($app['ems']['default'], $orm);
    }

    /**
     * @test
     */
    public function multipleConnections()
    {
        $app = $this->createApplication();
        $app->register(new DoctrineServiceProvider());
        $app->register(new DoctrineCacheServiceProvider());
        $app->register(new DoctrineOrmServiceProvider());
        $app['orm.proxy_namespace'] = 'Proxy';
        $app['orm.proxy_dir'] = __DIR__;
        $app['ems.options'] = [
            'sqlite1' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
                'mappings' => [
                    [
                        'type' => 'yml',
                        'namespace' => 'Bar\Entity',
                        'path' => __DIR__,
                    ],
                ],
            ],
            'sqlite2' => ['driver' => 'pdo_sqlite', 'path' => '/'],
        ];

        $orm = $app['orm'];
        $this->assertSame($app['ems']['sqlite1'], $orm);
    }

    public function createApplication()
    {
        $app = new Application();
        $app['debug'] = true;
        $app['exception_handler']->disable();

        return $app;
    }
}
