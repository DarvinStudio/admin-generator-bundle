<?php
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2015, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Twig\Extension;

use Darvin\AdminBundle\Metadata\MetadataManager;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Breadcrumbs Twig extension
 */
class BreadcrumbsExtension extends \Twig_Extension
{
    /**
     * @var \Darvin\AdminBundle\Metadata\MetadataManager
     */
    private $metadataManager;

    /**
     * @var \Symfony\Component\PropertyAccess\PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var \Twig_Environment
     */
    private $environment;

    /**
     * @param \Darvin\AdminBundle\Metadata\MetadataManager                $metadataManager  Metadata manager
     * @param \Symfony\Component\PropertyAccess\PropertyAccessorInterface $propertyAccessor Property accessor
     */
    public function __construct(MetadataManager $metadataManager, PropertyAccessorInterface $propertyAccessor)
    {
        $this->metadataManager = $metadataManager;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('admin_breadcrumbs', array($this, 'renderBreadcrumbs'), array('is_safe' => array('html'))),
        );
    }

    /**
     * @param object $entity     Entity
     * @param bool   $renderLast Whether to render last crumb
     * @param string $template   Template
     *
     * @return string
     */
    public function renderBreadcrumbs(
        $entity = null,
        $renderLast = true,
        $template = 'DarvinAdminBundle::breadcrumbs.html.twig'
    ) {
        if (empty($entity)) {
            return '';
        }

        $crumbs = array();

        $meta = $this->metadataManager->getMetadata($entity);

        /** @var \Darvin\AdminBundle\Metadata\AssociatedMetadata $parentMeta */
        while ($parentMeta = $meta->getParent()) {
            $crumbs[] = array(
                'entity' => $entity,
                'meta'   => $meta,
            );

            $entity = $this->propertyAccessor->getValue($entity, $parentMeta->getAssociation());
            $meta = $parentMeta->getMetadata();
        }

        $crumbs[] = array(
            'entity' => $entity,
            'meta'   => $meta,
        );

        $configuration = $meta->getConfiguration();

        return $this->environment->render($template, array(
            'crumbs'       => array_reverse($crumbs),
            'entity_route' => $configuration['breadcrumbs_entity_route'],
            'render_last'  => $renderLast,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'darvin_admin_breadcrumbs_extension';
    }
}
