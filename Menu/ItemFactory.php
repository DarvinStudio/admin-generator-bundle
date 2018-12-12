<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2015, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Menu;

use Darvin\AdminBundle\Metadata\AdminMetadataManagerInterface;
use Darvin\AdminBundle\Metadata\Metadata;
use Darvin\AdminBundle\Route\AdminRouterInterface;
use Darvin\AdminBundle\Security\Permissions\Permission;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Menu item factory
 */
class ItemFactory implements ItemFactoryInterface
{
    /**
     * @var \Darvin\AdminBundle\Route\AdminRouterInterface
     */
    private $adminRouter;

    /**
     * @var \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var \Darvin\AdminBundle\Metadata\AdminMetadataManagerInterface
     */
    private $metadataManager;

    /**
     * @param \Darvin\AdminBundle\Route\AdminRouterInterface                               $adminRouter          Admin router
     * @param \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $authorizationChecker Authorization checker
     * @param \Darvin\AdminBundle\Metadata\AdminMetadataManagerInterface                   $metadataManager      Metadata manager
     */
    public function __construct(
        AdminRouterInterface $adminRouter,
        AuthorizationCheckerInterface $authorizationChecker,
        AdminMetadataManagerInterface $metadataManager
    ) {
        $this->adminRouter = $adminRouter;
        $this->authorizationChecker = $authorizationChecker;
        $this->metadataManager = $metadataManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(): iterable
    {
        foreach ($this->metadataManager->getAllMetadata() as $meta) {
            if (!$meta->hasParent() && !$meta->getConfiguration()['menu']['skip']) {
                yield $this->createItemFromMetadata($meta);
            }
        }
    }

    /**
     * @param \Darvin\AdminBundle\Metadata\Metadata $meta Metadata
     *
     * @return \Darvin\AdminBundle\Menu\Item
     */
    private function createItemFromMetadata(Metadata $meta): Item
    {
        $config      = $meta->getConfiguration();
        $entityClass = $meta->getEntityClass();

        $item = new Item($meta->getEntityName());
        $item
            ->setIndexTitle($meta->getBaseTranslationPrefix().'action.index.link')
            ->setNewTitle($meta->getBaseTranslationPrefix().'action.new.link')
            ->setDescription($meta->getBaseTranslationPrefix().'menu.description')
            ->setMainColor($config['menu']['colors']['main'])
            ->setSidebarColor($config['menu']['colors']['sidebar'])
            ->setMainIcon($config['menu']['icons']['main'])
            ->setSidebarIcon($config['menu']['icons']['sidebar'])
            ->setPosition($config['menu']['position'])
            ->setAssociatedObject($entityClass)
            ->setParentName($config['menu']['group']);

        if ($this->authorizationChecker->isGranted(Permission::VIEW, $entityClass)
            && $this->adminRouter->exists($entityClass, AdminRouterInterface::TYPE_INDEX)
        ) {
            $item->setIndexUrl($this->adminRouter->generate(null, $entityClass, AdminRouterInterface::TYPE_INDEX, [], UrlGeneratorInterface::ABSOLUTE_PATH, false));
        }
        if ($this->authorizationChecker->isGranted(Permission::CREATE_DELETE, $entityClass)
            && $this->adminRouter->exists($entityClass, AdminRouterInterface::TYPE_NEW)
        ) {
            $item->setNewUrl($this->adminRouter->generate(null, $entityClass, AdminRouterInterface::TYPE_NEW, [], UrlGeneratorInterface::ABSOLUTE_PATH, false));
        }

        return $item;
    }
}
