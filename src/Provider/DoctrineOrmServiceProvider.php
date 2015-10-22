<?php
namespace Inbep\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;

/**
 * @author Sérgio Rafael Siqueira <sergio@inbep.com.br>
 */
class DoctrineOrmServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        if (!isset($app['cache'])) {
            throw new \LogicException(
                'You must register the DoctrineCacheServiceProvider to use the DoctrineOrmServiceProvider.'
            );
        }

        $app['orm.proxy_dir'] = null;
        $app['orm.proxy_namespace'] = 'Proxy';
        $app['orm.auto_generate_proxy_classes'] = true;
        $app['orm.custom_functions_string'] = [];
        $app['orm.custom_functions_numeric'] = [];
        $app['orm.custom_functions_datetime'] = [];
        $app['orm.default_options'] = [
            'connection' => 'default',
            'mappings' => []
        ];

        $app['ems.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($app['ems.options'])) {
                $app['ems.options'] = [
                    'default' => isset($app['orm.options']) ? $app['orm.options'] : []
                ];
            }

            $tmp = $app['ems.options'];
            foreach ($tmp as $name => &$options) {
                $options = array_replace($app['orm.default_options'], $options);

                if (!isset($app['ems.default'])) {
                    $app['ems.default'] = $name;
                }
            }
            $app['ems.options'] = $tmp;
        });

        $app['ems'] = $app->share(function (Application $app) {
            $app['ems.options.initializer']();

            $container = new \Pimple();
            foreach ($app['ems.options'] as $name => $options) {
                if ($app['ems.default'] === $name) {
                    $config = $app['orm.config'];
                } else {
                    $config = $app['ems.config'][$name];
                }

                $connection = $app['dbs'][$options['connection']];
                $manager = $app['dbs.event_manager'][$options['connection']];

                $container[$name] = $container->share(
                    function () use ($connection, $config, $manager) {
                        return EntityManager::create($connection, $config, $manager);
                    }
                );
            }

            return $container;
        });

        $app['ems.config'] = $app->share(function (Application $app) {
            $app['ems.options.initializer']();

            $container = new \Pimple();
            foreach ($app['ems.options'] as $name => $options) {
                $config = new Configuration();
                $config->setProxyDir($app['orm.proxy_dir']);
                $config->setProxyNamespace($app['orm.proxy_namespace']);
                $config->setAutoGenerateProxyClasses($app['orm.auto_generate_proxy_classes']);
                $config->setCustomStringFunctions($app['orm.custom_functions_string']);
                $config->setCustomNumericFunctions($app['orm.custom_functions_numeric']);
                $config->setCustomDatetimeFunctions($app['orm.custom_functions_datetime']);
                $config->setMetadataCacheImpl($app['orm.cache.factory']('metadata', $options));
                $config->setQueryCacheImpl($app['orm.cache.factory']('query', $options));
                $config->setResultCacheImpl($app['orm.cache.factory']('result', $options));
                $config->setMetadataDriverImpl($app['orm.mapping.chain']($config, $options['mappings']));
                $container[$name] = $config;
            }

            return $container;
        });

        $app['orm.cache.factory'] = $app->protect(function ($type, $options) use ($app) {
            $type = $type.'_cache_driver';

            if (!isset($options[$type])) {
                $options[$type] = 'array';
            }

            if (!is_array($options[$type])) {
                $options[$type] = [
                    'driver' => $options[$type]
                ];
            }

            switch ($options[$type]['driver']) {
                case 'array':
                    return $app['cache.array']();
                    break;
                case 'apc':
                    return $app['cache.apc']();
                    break;
                case 'redis':
                    return $app['cache.redis']($options);
                    break;
                case 'xcache':
                    return $app['cache.xcache']();
                    break;
            }

            throw new \RuntimeException();
        });

        $app['orm.mapping.chain'] = $app->protect(function (Configuration $config, array $mappings) {
            $chain = new MappingDriverChain();

            foreach ($mappings as $mapping) {
                if (!is_array($mapping)) {
                    throw new \InvalidArgumentException();
                }

                switch ($mapping['type']) {
                    case 'annotation':
                        $useSimpleAnnotationReader = isset($mapping['use_simple_annotation_reader'])
                            ? $mapping['use_simple_annotation_reader']
                            : true;

                        $driver = $config->newDefaultAnnotationDriver(
                            $mapping['path'],
                            $useSimpleAnnotationReader
                        );
                        break;
                    case 'yml':
                        $driver = new YamlDriver($mapping['path']);
                        break;
                    default:
                        throw new \InvalidArgumentException();
                        break;
                }

                $chain->addDriver($driver, $mapping['namespace']);
            }

            return $chain;
        });

        // shortcuts for the "first" ORM
        $app['orm'] = $app->share(function (Application $app) {
            $ems = $app['ems'];

            return $ems[$app['ems.default']];
        });

        $app['orm.config'] = $app->share(function (Application $app) {
            $ems = $app['ems.config'];

            return $ems[$app['ems.default']];
        });
    }

    public function boot(Application $app)
    {
    }
}
