<?php

namespace Liip\CacheControlBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor,
    Symfony\Component\Config\FileLocator,
    Symfony\Component\HttpKernel\DependencyInjection\Extension,
    Symfony\Component\DependencyInjection\Loader\XmlFileLoader,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Reference,
    Symfony\Component\DependencyInjection\DefinitionDecorator,
    Symfony\Component\DependencyInjection\Exception\RuntimeException;

class LiipCacheControlExtension extends Extension
{
    /**
     * Loads the services based on your application configuration.
     *
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $loader =  new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $container->setParameter($this->getAlias().'.debug', $config['debug']);

        if (!empty($config['rules'])) {
            $loader->load('rule_response_listener.xml');
            foreach ($config['rules'] as $cache) {
                // domain is depreciated and will be removed in future
                $host = is_null($cache['host']) && $cache['domain'] ? $cache['domain'] : $cache['host'];
                $cache['ips'] = (empty($cache['ips'])) ? null : $cache['ips'];

                $matcher = $this->createRequestMatcher(
                    $container,
                    $cache['path'],
                    $host,
                    $cache['method'],
                    $cache['ips'],
                    $cache['attributes'],
                    $cache['controller']
                );

                unset(
                    $cache['path'],
                    $cache['method'],
                    $cache['ips'],
                    $cache['attributes'],
                    $cache['domain'],
                    $cache['host'],
                    $cache['controller']
                );

                $container->getDefinition($this->getAlias().'.response_listener')
                          ->addMethodCall('add', array($matcher, $cache));
            }
        }

        if (!empty($config['varnish'])) {

            if (!extension_loaded('curl')) {
                throw new InvalidConfigurationException('Varnish Helper requires cUrl php extension. Please install it to continue');
            }

            // domain is depreciated and will be removed in future
            $host = is_null($config['varnish']['host']) && $config['varnish']['domain'] ? $config['varnish']['domain'] : $config['varnish']['host'];
            if ($host) {
                $url = parse_url($host);
                if (!isset($url['host'])) {
                    throw new InvalidConfigurationException("$host is not a valid host configuration. Needs to be i.e. http://domain.net");
                }
                $host = $url['host'];
                if (isset($url['port'])) {
                    $host .= ':' . $url['port'];
                }
            }
            $loader->load('varnish_helper.xml');
            $container->setParameter($this->getAlias().'.varnish.ips', $config['varnish']['ips']);
            $container->setParameter($this->getAlias().'.varnish.host', $host);
            $container->setParameter($this->getAlias().'.varnish.port', $config['varnish']['port']);
            $container->setParameter($this->getAlias().'.varnish.purge_instruction', $config['varnish']['purge_instruction']);
        }

        if ($config['authorization_listener']) {
            $loader->load('authorization_request_listener.xml');
        }

        if (!empty($config['flash_message_listener']) && $config['flash_message_listener']['enabled']) {
            $loader->load('flash_message_listener.xml');

            $container->setParameter($this->getAlias().'.flash_message_listener.options', $config['flash_message_listener']);
        }
    }

    protected function createRequestMatcher(ContainerBuilder $container, $path = null, $host = null, $methods = null, $ips = null, array $attributes = array(), $controller = null)
    {
        if (null !== $controller) {
            $attributes['_controller'] = $controller;
        }

        $arguments = array($path, $host, $methods, $ips, $attributes);
        $serialized = serialize($arguments);
        $id = $this->getAlias().'.request_matcher.'.md5($serialized).sha1($serialized);

        if (!$container->hasDefinition($id)) {
            // only add arguments that are necessary
            $container
                ->setDefinition($id, new DefinitionDecorator($this->getAlias().'.request_matcher'))
                ->setArguments($arguments)
            ;
        }

        return new Reference($id);
    }
}
