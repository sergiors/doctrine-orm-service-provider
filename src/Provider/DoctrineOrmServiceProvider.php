<?php

namespace Sergiors\Silex\Provider;

use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;

/**
 * @author Sérgio Rafael Siqueira <sergio@inbep.com.br>
 */
class DoctrineOrmServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        if (!isset($app['dbs'])) {
            throw new \LogicException(
                'You must register the DoctrineServiceProvider to use the DoctrineOrmServiceProvider.'
            );
        }

        if (!isset($app['caches'])) {
            throw new \LogicException(
                'You must register the DoctrineCacheServiceProvider to use the DoctrineOrmServiceProvider.'
            );
        }

        $app['ems.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($app['ems.options'])) {
                $app['ems.options'] = [
                    'default' => $app['orm.options'] ?? [],
                ];
            }

            $app['ems.options'] = array_map(function ($options) use ($app) {
                return array_replace($app['orm.default_options'], $options);
            }, $app['ems.options']);

            if (!isset($app['ems.default'])) {
                $app['ems.default'] = array_keys(
                    array_slice($app['ems.options'], 0, 1)
                )[0];
            }
        });

        $app['ems'] = function (Container $app) {
            $app['ems.options.initializer']();

            $container = new Container();
            foreach ($app['ems.options'] as $name => $options) {
                $config = $app['ems.default'] === $name
                    ? $app['orm.config']
                    : $app['ems.config'][$name];

                $connection = $app['dbs'][$options['connection']];
                $manager = $app['dbs.event_manager'][$options['connection']];

                $container[$name] = function () use ($connection, $config, $manager) {
                    return EntityManager::create($connection, $config, $manager);
                };
            }

            return $container;
        };

        $app['ems.config'] = function (Container $app) {
            $app['ems.options.initializer']();

            $container = new Container();
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
        };

        $app['orm.cache.factory'] = $app->protect(function ($type, $options) use ($app) {
            $type = $type.'_cache_driver';

            $options[$type] = $options[$type] ?? 'array';

            if (!is_array($options[$type])) {
                $options[$type] = [
                    'driver' => $options[$type],
                ];
            }

            $driver = $options[$type]['driver'];
            $namespace = $options[$type]['namespace'] ?? null;

            $cache = $app['cache_factory']($driver, $options);
            $cache->setNamespace($namespace);

            return $cache;
        });

        $app['orm.mapping.chain'] = $app->protect(function (Configuration $config, array $mappings) {
            $chain = new MappingDriverChain();

            foreach ($mappings as $mapping) {
                if (!is_array($mapping)) {
                    throw new \InvalidArgumentException();
                }

                $path = $mapping['path'];
                $namespace = $mapping['namespace'];

                switch ($mapping['type']) {
                    case 'annotation':
                        $annotationDriver = $config->newDefaultAnnotationDriver(
                            $path,
                            $mapping['use_simple_annotation_reader'] ?? true
                        );

                        $chain->addDriver($annotationDriver, $namespace);
                        break;
                    case 'yml':
                        $chain->addDriver(new YamlDriver($path), $namespace);
                        break;
                    case 'xml':
                        $chain->addDriver(new XmlDriver($path), $namespace);
                        break;
                    default:
                        throw new \InvalidArgumentException();
                        break;
                }
            }

            return $chain;
        });

        $app['orm.proxy_dir'] = null;
        $app['orm.proxy_namespace'] = 'Proxy';
        $app['orm.auto_generate_proxy_classes'] = true;
        $app['orm.custom_functions_string'] = [];
        $app['orm.custom_functions_numeric'] = [];
        $app['orm.custom_functions_datetime'] = [];
        $app['orm.default_options'] = [
            'connection' => 'default',
            'mappings' => [],
        ];

        // shortcuts for the "first" ORM
        $app['orm'] = function (Container $app) {
            $ems = $app['ems'];

            return $ems[$app['ems.default']];
        };

        $app['orm.config'] = function (Container $app) {
            $ems = $app['ems.config'];

            return $ems[$app['ems.default']];
        };
    }
}
