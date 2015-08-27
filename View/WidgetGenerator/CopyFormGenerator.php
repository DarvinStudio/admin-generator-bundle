<?php
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2015, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\View\WidgetGenerator;

use Darvin\AdminBundle\Form\AdminFormFactory;
use Darvin\AdminBundle\Security\Permissions\Permission;
use Darvin\Utils\Mapping\MetadataFactoryInterface;

/**
 * Copy form view widget generator
 */
class CopyFormGenerator extends AbstractWidgetGenerator
{
    const ALIAS = 'copy_form';

    /**
     * @var \Darvin\AdminBundle\Form\AdminFormFactory
     */
    private $adminFormFactory;

    /**
     * @var \Darvin\Utils\Mapping\MetadataFactoryInterface
     */
    private $mappingMetadataFactory;

    /**
     * @param \Darvin\AdminBundle\Form\AdminFormFactory $adminFormFactory Admin form factory
     */
    public function setAdminFormFactory(AdminFormFactory $adminFormFactory)
    {
        $this->adminFormFactory = $adminFormFactory;
    }

    /**
     * @param \Darvin\Utils\Mapping\MetadataFactoryInterface $mappingMetadataFactory Mapping metadata factory
     */
    public function setMappingMetadataFactory(MetadataFactoryInterface $mappingMetadataFactory)
    {
        $this->mappingMetadataFactory = $mappingMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($entity, array $options = array())
    {
        if (!$this->isGranted(Permission::CREATE_DELETE, $entity)) {
            return '';
        }

        $mappingMeta = $this->mappingMetadataFactory->getMetadataByObject($entity);

        if (!isset($mappingMeta['clonable'])) {
            return '';
        }

        return $this->render($options, array(
            'form'               => $this->adminFormFactory->createCopyForm($entity)->createView(),
            'translation_prefix' => $this->metadataManager->getByEntity($entity)->getBaseTranslationPrefix(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return self::ALIAS;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultTemplate()
    {
        return 'DarvinAdminBundle:widget:copy_form.html.twig';
    }
}
