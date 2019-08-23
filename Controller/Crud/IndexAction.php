<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2018-2019, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Controller\Crud;

use Darvin\AdminBundle\Event\Crud\Controller\ControllerEvent;
use Darvin\AdminBundle\Event\Crud\Controller\CrudControllerEvents;
use Darvin\AdminBundle\Metadata\SortCriteriaDetectorInterface;
use Darvin\AdminBundle\Route\AdminRouterInterface;
use Darvin\AdminBundle\Security\Permissions\Permission;
use Darvin\AdminBundle\View\Factory\Index\IndexViewFactoryInterface;
use Darvin\AdminBundle\View\Widget\Widget\BatchDeleteWidget;
use Darvin\ContentBundle\Filterer\FiltererInterface;
use Darvin\ContentBundle\ORM\SortEntityJoinerInterface;
use Darvin\ContentBundle\Translatable\TranslationJoinerInterface;
use Darvin\Utils\CustomObject\CustomObjectException;
use Darvin\Utils\CustomObject\CustomObjectLoaderInterface;
use Darvin\Utils\User\UserQueryBuilderFiltererInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD controller index action
 */
class IndexAction extends AbstractAction
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var \Darvin\Utils\CustomObject\CustomObjectLoaderInterface
     */
    private $customObjectLoader;

    /**
     * @var \Darvin\ContentBundle\Filterer\FiltererInterface
     */
    private $filterer;

    /**
     * @var \Darvin\AdminBundle\View\Factory\Index\IndexViewFactoryInterface
     */
    private $indexViewFactory;

    /**
     * @var \Darvin\AdminBundle\Controller\Crud\NewAction
     */
    private $newAction;

    /**
     * @var \Knp\Component\Pager\PaginatorInterface
     */
    private $paginator;

    /**
     * @var \Darvin\AdminBundle\Metadata\SortCriteriaDetectorInterface
     */
    private $sortCriteriaDetector;

    /**
     * @var \Darvin\ContentBundle\ORM\SortEntityJoinerInterface
     */
    private $sortEntityJoiner;

    /**
     * @var \Darvin\ContentBundle\Translatable\TranslationJoinerInterface
     */
    private $translationJoiner;

    /**
     * @var \Darvin\Utils\User\UserQueryBuilderFiltererInterface
     */
    private $userQueryBuilderFilterer;

    /**
     * @param \Psr\Container\ContainerInterface                                $container                DI container
     * @param \Darvin\Utils\CustomObject\CustomObjectLoaderInterface           $customObjectLoader       Custom object loader
     * @param \Darvin\ContentBundle\Filterer\FiltererInterface                 $filterer                 Filterer
     * @param \Darvin\AdminBundle\View\Factory\Index\IndexViewFactoryInterface $indexViewFactory         Index view factory
     * @param \Darvin\AdminBundle\Controller\Crud\NewAction                    $newAction                CRUD controller new action
     * @param \Knp\Component\Pager\PaginatorInterface                          $paginator                Paginator
     * @param \Darvin\AdminBundle\Metadata\SortCriteriaDetectorInterface       $sortCriteriaDetector     Sort criteria detector
     * @param \Darvin\ContentBundle\ORM\SortEntityJoinerInterface              $sortEntityJoiner         Sort entity joiner
     * @param \Darvin\ContentBundle\Translatable\TranslationJoinerInterface    $translationJoiner        Translation joiner
     * @param \Darvin\Utils\User\UserQueryBuilderFiltererInterface             $userQueryBuilderFilterer User query builder filterer
     */
    public function __construct(
        ContainerInterface $container,
        CustomObjectLoaderInterface $customObjectLoader,
        FiltererInterface $filterer,
        IndexViewFactoryInterface $indexViewFactory,
        NewAction $newAction,
        PaginatorInterface $paginator,
        SortCriteriaDetectorInterface $sortCriteriaDetector,
        SortEntityJoinerInterface $sortEntityJoiner,
        TranslationJoinerInterface $translationJoiner,
        UserQueryBuilderFiltererInterface $userQueryBuilderFilterer
    ) {
        $this->container = $container;
        $this->customObjectLoader = $customObjectLoader;
        $this->filterer = $filterer;
        $this->indexViewFactory = $indexViewFactory;
        $this->newAction = $newAction;
        $this->paginator = $paginator;
        $this->sortCriteriaDetector = $sortCriteriaDetector;
        $this->sortEntityJoiner = $sortEntityJoiner;
        $this->translationJoiner = $translationJoiner;
        $this->userQueryBuilderFilterer = $userQueryBuilderFilterer;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(): Response
    {
        $this->checkPermission(Permission::VIEW);

        $request = $this->requestStack->getCurrentRequest();

        list($parentEntity, $association, $associationParam, $parentEntityId) = $this->getParentEntityDefinition($request);

        $this->eventDispatcher->dispatch(CrudControllerEvents::STARTED, new ControllerEvent($this->getMeta(), $this->userManager->getCurrentUser(), __FUNCTION__));

        $filterForm = $this->adminFormFactory->createFilterForm($this->getMeta(), $associationParam, $parentEntityId);

        if (null !== $filterForm) {
            $filterForm->handleRequest($request);
        }

        $qb = $this->createQueryBuilder($request->getLocale(), !empty($filterForm) ? $filterForm->getData() : null);

        if ($this->userQueryBuilderFilterer->isFilterable($qb)) {
            $this->userQueryBuilderFilterer->filter($qb);
        }
        if ($this->getMeta()->hasParent()) {
            $qb->andWhere(sprintf('o.%s = :%1$s', $association))->setParameter($association, $parentEntityId);
        }

        $batchDeleteForm  = null;
        $newForm          = null;
        $sortCriteria     = $this->sortCriteriaDetector->detectSortCriteria($this->getEntityClass());
        $pagination       = null;
        $paginatorOptions = [
            'allowPageNumberExceed' => true,
            'wrap-queries'          => true,
        ];

        $config = $this->getConfig();

        if (!empty($sortCriteria)) {
            if ((count($sortCriteria) > 1 && !$request->query->has('sort')) || !$config['pagination']['enabled']) {
                foreach ($sortCriteria as $sort => $order) {
                    foreach (explode('+', $sort) as $part) {
                        if (false === strpos($part, '.')) {
                            $part = sprintf('o.%s', $part);
                        }

                        $qb->addOrderBy($part, $order);
                    }
                }
            } else {
                $sortField = array_keys($sortCriteria)[0];

                if (false === strpos($sortField, '.')) {
                    $sortField = sprintf('o.%s', $sortField);
                }

                $paginatorOptions['defaultSortFieldName'] = $sortField;
                $paginatorOptions['defaultSortDirection'] = reset($sortCriteria);
            }
        }
        if ($config['pagination']['enabled']) {
            foreach (explode('+', $request->query->get('sort', '')) as $part) {
                $this->sortEntityJoiner->joinEntity($qb, $part, $request->getLocale());
            }

            $page = $request->query->get('page', 1);

            /** @var \Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination $pagination */
            $pagination = $this->paginator->paginate($qb, $page, $config['pagination']['items'], $paginatorOptions);

            $entities = $pagination->getItems();

            if (empty($entities) && $page > 1) {
                $pagination = $this->paginator->paginate($qb, $pagination->getPageCount(), $config['pagination']['items'], $paginatorOptions);

                $entities = $pagination->getItems();
            }

            $entityCount = $pagination->getTotalItemCount();
        } else {
            $entities = $qb->getQuery()->getResult();

            $entityCount = count($entities);
        }
        if (isset($config['sorter'])) {
            $entities = $this->container->get($config['sorter']['id'])->{$config['sorter']['method']}($entities);
        }
        try {
            $this->customObjectLoader->loadCustomObjects($entities);
        } catch (CustomObjectException $ex) {
        }
        if (!empty($entities)
            && $this->authorizationChecker->isGranted(Permission::CREATE_DELETE, $this->getEntityClass())
            && $this->adminRouter->exists($this->getEntityClass(), AdminRouterInterface::TYPE_BATCH_DELETE)
            && isset($config['view']['index']['action_widgets'][BatchDeleteWidget::ALIAS])
        ) {
            $batchDeleteForm = $this->adminFormFactory->createBatchDeleteForm($this->getEntityClass(), $entities)->createView();
        }
        if ($config['index_view_new_form']) {
            $newAction = $this->newAction;

            $newForm = $newAction(true)->getContent();
        }

        return new Response($this->renderTemplate([
            'association_param' => $associationParam,
            'batch_delete_form' => $batchDeleteForm,
            'entity_count'      => $entityCount,
            'filter_form'       => !empty($filterForm) ? $filterForm->createView() : null,
            'meta'              => $this->getMeta(),
            'new_form'          => $newForm,
            'pagination'        => $pagination,
            'parent_entity'     => $parentEntity,
            'parent_entity_id'  => $parentEntityId,
            'view'              => $this->indexViewFactory->createView($entities, $this->getMeta()),
        ], $request->isXmlHttpRequest()));
    }

    /**
     * @param string     $locale         Locale
     * @param array|null $filterFormData Filter form data
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createQueryBuilder(string $locale, ?array $filterFormData): QueryBuilder
    {
        $qb     = $this->em->getRepository($this->getEntityClass())->createQueryBuilder('o');
        $config = $this->getConfig();

        foreach ($config['joins'] as $alias => $join) {
            if (false === strpos($join, '.')) {
                $join = 'o.'.$join;
            }

            $qb->addSelect($alias)->leftJoin($join, $alias);
        }
        if ($this->translationJoiner->isTranslatable($this->getEntityClass())) {
            $this->translationJoiner->joinTranslation($qb, true, $locale);
        }
        if (empty($filterFormData)) {
            return $qb;
        }

        $filtererOptions = [
            'non_strict_comparison_fields' => [],
        ];

        $getNonStrictComparisonFields = function (array $fields) use (&$filtererOptions) {
            foreach ($fields as $field => $attr) {
                if (!$attr['compare_strict']) {
                    $filtererOptions['non_strict_comparison_fields'][] = $field;
                }
            }
        };

        $getNonStrictComparisonFields($config['form']['filter']['fields']);
        array_map($getNonStrictComparisonFields, $config['form']['filter']['field_groups']);

        $this->filterer->filter($qb, $filterFormData, $filtererOptions);

        return $qb;
    }
}
