<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2015-2018, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\DependencyInjection;

use Darvin\AdminBundle\Entity\LogEntry;
use Darvin\AdminBundle\Security\User\Roles;
use Darvin\ConfigBundle\Entity\ParameterEntity;
use Darvin\Utils\DependencyInjection\ConfigInjector;
use Darvin\Utils\DependencyInjection\ConfigLoader;
use Darvin\Utils\DependencyInjection\ExtensionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DarvinAdminExtension extends Extension implements PrependExtensionInterface
{
    private const FIREWALL_NAME = 'admin_area';

    /**
     * @var bool
     */
    private $showErrorPages;

    /**
     * Extension constructor.
     */
    public function __construct()
    {
        $this->showErrorPages = false;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $showErrorPages = $this->showErrorPages;

        $this->mergeSectionConfigs($configs);

        (new ConfigInjector($container))->inject($this->processConfiguration(new Configuration(), $configs), $this->getAlias());

        (new ConfigLoader($container, __DIR__.'/../Resources/config'))->load([
            'ace_editor',
            'breadcrumbs',
            'ckeditor',
            'configuration',
            'crud',
            'dashboard',
            'dropzone',
            'entity_namer',
            'form',
            'locale',
            'menu',
            'metadata',
            'route',
            'search',
            'security',
            'slug_suffix',
            'twig',
            'uploader',
            'view',

            'dev/metadata'              => ['env' => 'dev'],
            'dev/translation_generator' => ['env' => 'dev'],
            'dev/view'                  => ['env' => 'dev'],

            'prod/cache'                => ['env' => 'prod'],

            'translation'               => ['bundle' => 'LexikTranslationBundle'],

            'error'                     => ['callback' => function () use ($showErrorPages) {
                return $showErrorPages;
            }],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->setParameter('darvin_admin.tmp_dir', sprintf('%s/darvin/admin', sys_get_temp_dir()));

        if (!$container->getParameter('kernel.debug')) {
            foreach ($container->getExtensionConfig('security') as $config) {
                if (isset($config['firewalls'][self::FIREWALL_NAME])) {
                    $firewallConfig = $config['firewalls'][self::FIREWALL_NAME];

                    $this->showErrorPages = isset($firewallConfig['pattern']) && '^/' !== $firewallConfig['pattern'];
                }
            }
        }

        (new ExtensionConfigurator($container, __DIR__.'/../Resources/config/app'))->configure([
            'a2lix_translation_form',
            'bazinga_js_translation',
            'fm_elfinder',
            'hwi_oauth',
            'fos_ck_editor',
            'lexik_translation',
            'liip_imagine',
            'oneup_uploader',
            'twig',
        ]);

        if ($container->hasExtension('darvin_user')) {
            $container->prependExtensionConfig('darvin_user', [
                'roles' => Roles::getRoles(),
            ]);
        }

        $sections = [
            [
                'alias'  => 'configuration',
                'entity' => ParameterEntity::class,
            ],
            [
                'alias'  => 'log',
                'entity' => LogEntry::class,
                'config' => '@DarvinAdminBundle/Resources/config/admin/log.yaml',
            ],
        ];

        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['LexikTranslationBundle'])) {
            $sections[] = [
                'entity' => 'Lexik\Bundle\TranslationBundle\Entity\Translation',
                'config' => '@DarvinAdminBundle/Resources/config/admin/translation.yaml',
            ];
        }

        $container->prependExtensionConfig($this->getAlias(), [
            'sections' => $sections,
            'menu'     => [
                'groups' => [
                    [
                        'name'     => 'modules',
                        'position' => 500,
                        'colors' => [
                            'main'    => '#ca9e26',
                            'sidebar' => '#ca9e26',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/modules_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/modules_sidebar.png',
                        ],
                    ],
                    [
                        'name'     => 'seo',
                        'position' => 500,
                        'colors'   => [
                            'main'    => '#ca9e26',
                            'sidebar' => '#ca9e26',
                        ],
                        'icons' => [
                            'main'    => 'bundles/darvinadmin/images/admin/modules_main.png',
                            'sidebar' => 'bundles/darvinadmin/images/admin/modules_sidebar.png',
                        ],
                    ],
                ],
            ],
            'form' => [
                'default_field_options' => [
                    CheckboxType::class => [
                        'required' => false,
                    ],
                    DateType::class => [
                        'widget' => 'single_text',
                        'format' => 'dd.MM.yyyy',
                    ],
                    DateTimeType::class => [
                        'widget' => 'single_text',
                        'format' => 'dd.MM.yyyy HH:mm',
                    ],
                    TimeType::class => [
                        'widget' => 'single_text',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array $configs Section configurations
     */
    private function mergeSectionConfigs(array &$configs): void
    {
        foreach ($configs as $configKey => $config) {
            if (!isset($config['sections'])) {
                continue;
            }
            foreach ($config['sections'] as $sectionKey => $section) {
                if (!isset($section['alias']) && !isset($section['entity'])) {
                    continue;
                }
                foreach ($configs as $otherConfigKey => $otherConfig) {
                    if (!isset($otherConfig['sections']) || $otherConfigKey === $configKey) {
                        continue;
                    }
                    foreach ($otherConfig['sections'] as $otherSectionKey => $otherSection) {
                        if ((isset($section['alias']) && isset($otherSection['alias']) && $otherSection['alias'] === $section['alias'])
                            || (isset($section['entity']) && isset($otherSection['entity']) && $otherSection['entity'] === $section['entity'])
                        ) {
                            $configs[$configKey]['sections'][$sectionKey] = array_merge($section, $otherSection);
                            unset($configs[$otherConfigKey]['sections'][$otherSectionKey]);
                        }
                    }
                }
            }
        }
    }
}
