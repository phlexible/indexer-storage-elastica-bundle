<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\IndexerStorageElasticaComponent\Container;

use Phlexible\Container\ContainerBuilder;
use Phlexible\Container\Extension\Extension;
use Phlexible\Container\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Elastica indexer storage extension
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class IndexerStorageElasticaExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $configs)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../_config'));
        $loader->load('services.yml');

        $container->setParameters(
            array(
                'indexerstorageelastica.asset.script_path' => __DIR__ . '/../_scripts',
                'indexerstorageelastica.asset.css_path'    => __DIR__ . '/../_styles',
            )
        );
    }
}
