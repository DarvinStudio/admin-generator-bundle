<?php
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2015, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\DependencyInjection;

use Darvin\AdminBundle\Entity\LogEntry;
use Darvin\ConfigBundle\Entity\ParameterEntity;
use Darvin\Utils\DependencyInjection\ConfigInjector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DarvinAdminExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        (new ConfigInjector())->inject($this->processConfiguration(new Configuration(), $configs), $container, $this->getAlias());

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        foreach ([
            'asset/provider',
            'breadcrumbs',
            'cache',
            'ckeditor',
            'configuration',
            'crud',
            'dashboard',
            'dropzone',
            'entity_namer',
            'form',
            'image',
            'menu',
            'metadata',
            'route',
            'search',
            'security',
            'slug_suffix',
            'twig',
            'uploader',
            'view',
        ] as $resource) {
            $loader->load($resource.'.yml');
        }

        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['AsseticBundle']) && 'dev' === $container->getParameter('kernel.environment')) {
            $loader->load('asset/compiler.yml');
        }
        if (isset($bundles['LexikTranslationBundle'])) {
            $loader->load('translation.yml');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        $sections = [
            [
                'alias'  => 'configuration',
                'entity' => ParameterEntity::PARAMETER_ENTITY_CLASS,
            ],
//            [
//                'alias'  => 'image',
//                'entity' => AbstractImage::ABSTRACT_IMAGE_CLASS,
//                'config' => __DIR__.'/../Resources/config/admin/image.yml',
//            ],
            [
                'alias'  => 'log',
                'entity' => LogEntry::LOG_ENTRY_CLASS,
                'config' => __DIR__.'/../Resources/config/admin/log.yml',
            ],
        ];

        if (isset($bundles['LexikTranslationBundle'])) {
            $sections[] = [
                'entity' => 'Lexik\Bundle\TranslationBundle\Entity\Translation',
                'config' => __DIR__.'/../Resources/config/admin/translation.yml',
            ];
        }

        $container->prependExtensionConfig('darvin_admin', [
            'sections' => $sections,
            'menu'     => [
                'groups' => [
                    'modules' => [
                        'colors' => [
                            'main'    => '#ff9a16',
                            'sidebar' => '#ffe86d',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/modules_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/modules_sidebar.png',
                        ],
                    ],
                    'pages' => [
                        'position' => 1,
                        'colors'   => [
                            'main'    => '#649ea6',
                            'sidebar' => '#9befe2',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/pages_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/pages_sidebar.png',
                        ],
                    ],
                    'portfolio' => [
                        'colors' => [
                            'main'    => '#0086c4',
                            'sidebar' => '#00a1b9',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/portfolio_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/portfolio_sidebar.png',
                        ],
                    ],
                    'prices' => [
                        'colors' => [
                            'main'    => '#389fa8',
                            'sidebar' => '#279f00',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/prices_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/prices_sidebar.png',
                        ],
                    ],
                    'publications' => [
                        'position' => 2,
                        'colors'   => [
                            'main'    => '#ff4d25',
                            'sidebar' => '#ff7e75',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/publications_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/publications_sidebar.png',
                        ],
                    ],
                    'seo_results' => [
                        'colors' => [
                            'main'    => '#d49d00',
                            'sidebar' => '#f4b800',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/seo_results_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/seo_results_sidebar.png',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
