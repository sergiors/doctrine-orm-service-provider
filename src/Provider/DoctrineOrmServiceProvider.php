<?php

namespace Sergiors\Pimple\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;

/**
 * @author Sérgio Rafael Siqueira <sergio@inbep.com.br>
 */
class DoctrineOrmServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        if (!isset($container['dbs'])) {
            throw new \LogicException(
                'You must register the DoctrineServiceProvider to use the DoctrineOrmServiceProvider.'
            );
        }

        if (!isset($container['caches'])) {
            throw new \LogicException(
                'You must register the DoctrineCacheServiceProvider to use the DoctrineOrmServiceProvider.'
            );
        }

        $container['ems.options.initializer'] = $container->protect(function () use ($container) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($container['ems.options'])) {
                $container['ems.options'] = [
                    'default' => $container['orm.options'] ?? [],
                ];
            }

            $container['ems.options'] = array_map(function ($options) use ($container) {
                return array_replace($container['orm.default_options'], $options);
            }, $container['ems.options']);

            if (!isset($container['ems.default'])) {
                $container['ems.default'] = array_keys(
                    array_slice($container['ems.options'], 0, 1)
                )[0];
            }
        });

        $container['ems'] = function (Container $container) {
            $container['ems.options.initializer']();

            $ems = new Container();
            foreach ($container['ems.options'] as $name => $options) {
                $config = $container['ems.default'] === $name
                    ? $container['orm.config']
                    : $container['ems.config'][$name];

                $connection = $container['dbs'][$options['connection']];
                $manager = $container['dbs.event_manager'][$options['connection']];

                if ($targetEntities = $options['resolve_target_entities'] ?? []) {
                    $manager->addEventSubscriber(
                        $container['orm.resolve_target_entity']($targetEntities)
                    );
                }

                $ems[$name] = function () use ($connection, $config, $manager) {
                    return EntityManager::create($connection, $config, $manager);
                };
            }

            return $ems;
        };

        $container['ems.config'] = function (Container $container) {
            $container['ems.options.initializer']();

            $configs = new Container();
            foreach ($container['ems.options'] as $name => $options) {
                $config = new Configuration();
                $config->setProxyDir($container['orm.proxy_dir']);
                $config->setProxyNamespace($container['orm.proxy_namespace']);
                $config->setAutoGenerateProxyClasses($container['orm.auto_generate_proxy_classes']);
                $config->setCustomStringFunctions($container['orm.custom_functions_string']);
                $config->setCustomNumericFunctions($container['orm.custom_functions_numeric']);
                $config->setCustomDatetimeFunctions($container['orm.custom_functions_datetime']);
                $config->setMetadataCacheImpl($container['orm.cache.factory']('metadata', $options));
                $config->setQueryCacheImpl($container['orm.cache.factory']('query', $options));
                $config->setResultCacheImpl($container['orm.cache.factory']('result', $options));
                $config->setMetadataDriverImpl($container['orm.mapping.chain']($config, $options['mappings']));
                
                $configs[$name] = $config;
            }

            return $configs;
        };

        $container['orm.cache.factory'] = $container->protect(function ($type, $options) use ($container) {
            $type = $type . '_cache_driver';

            $options[$type] = $options[$type] ?? 'array';

            if (!is_array($options[$type])) {
                $options[$type] = [
                    'driver' => $options[$type],
                ];
            }

            $driver = $options[$type]['driver'];
            $namespace = $options[$type]['namespace'] ?? null;

            $cache = $container['cache_factory']($driver, $options);
            $cache->setNamespace($namespace);

            return $cache;
        });

        $container['orm.mapping.chain'] = $container->protect(function (Configuration $config, array $mappings) {
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

        $container['orm.resolve_target_entity'] = $container->protect(function (array $targetEntities) {
            $rtel = new ResolveTargetEntityListener();

            foreach ($targetEntities as $originalEntity => $newEntity) {
               $rtel->addResolveTargetEntity($originalEntity, $newEntity, []);
            }

            return $rtel;
        });

        $container['orm.proxy_dir'] = null;
        $container['orm.proxy_namespace'] = 'Proxy';
        $container['orm.auto_generate_proxy_classes'] = true;
        $container['orm.custom_functions_string'] = [];
        $container['orm.custom_functions_numeric'] = [];
        $container['orm.custom_functions_datetime'] = [];
        $container['orm.default_options'] = [
            'connection' => 'default',
            'mappings' => [],
        ];

        // shortcuts for the "first" ORM
        $container['orm'] = function (Container $container) {
            $ems = $container['ems'];

            return $ems[$container['ems.default']];
        };

        $container['orm.config'] = function (Container $container) {
            $ems = $container['ems.config'];

            return $ems[$container['ems.default']];
        };
    }
}
