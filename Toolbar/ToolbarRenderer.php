<?php
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2020, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Toolbar;

use Darvin\AdminBundle\Security\User\Roles;
use Darvin\AdminBundle\View\Widget\WidgetInterface;
use Darvin\ContentBundle\Entity\SlugMapItem;
use Darvin\Utils\Homepage\HomepageProviderInterface;
use Darvin\Utils\Homepage\HomepageRouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Twig\Environment;

/**
 * Toolbar renderer
 */
class ToolbarRenderer implements ToolbarRendererInterface
{
    const ROUTE_PARAM_SLUG = 'slug';

    /**
     * @var string[]
     */
    private static $routes = [
        'darvin_content_content_show',
        'darvin_content_show',
    ];

    /**
     * @var \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var \Darvin\AdminBundle\View\Widget\WidgetInterface
     */
    private $editLinkWidget;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \Darvin\Utils\Homepage\HomepageProviderInterface
     */
    private $homepageProvider;

    /**
     * @var \Darvin\Utils\Homepage\HomepageRouterInterface
     */
    private $homepageRouter;

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    private $requestStack;

    /**
     * @var \Twig\Environment
     */
    private $twig;

    /**
     * @param \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $authorizationChecker Authorization checker
     * @param \Darvin\AdminBundle\View\Widget\WidgetInterface                              $editLinkWidget       Edit link view widget
     * @param \Doctrine\ORM\EntityManagerInterface                                         $em                   Entity manager
     * @param \Darvin\Utils\Homepage\HomepageProviderInterface                             $homepageProvider     Homepage provider
     * @param \Darvin\Utils\Homepage\HomepageRouterInterface                               $homepageRouter       Homepage router
     * @param \Symfony\Component\HttpFoundation\RequestStack                               $requestStack         Request stack
     * @param \Twig\Environment                                                            $twig                 Twig
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        WidgetInterface $editLinkWidget,
        EntityManagerInterface $em,
        HomepageProviderInterface $homepageProvider,
        HomepageRouterInterface $homepageRouter,
        RequestStack $requestStack,
        Environment $twig
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->editLinkWidget = $editLinkWidget;
        $this->em = $em;
        $this->homepageProvider = $homepageProvider;
        $this->homepageRouter = $homepageRouter;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
    }

    /**
     * {@inheritDoc}
     */
    public function renderToolbar()
    {
        try {
            if (!$this->authorizationChecker->isGranted(Roles::ROLE_ADMIN)) {
                return null;
            }
        } catch (AuthenticationCredentialsNotFoundException $ex) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        $entity = $this->findEntity($request);

        if (null === $entity) {
            return null;
        }

        $editLink = $this->editLinkWidget->getContent($entity, [
            'style' => 'toolbar',
        ]);

        return $this->twig->render('DarvinAdminBundle::toolbar.html.twig', [
            'edit_link' => $editLink,
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request Request
     *
     * @return object|null
     */
    private function findEntity(Request $request)
    {
        if (!in_array($request->attributes->get('_route'), self::$routes)) {
            if ($request->getBaseUrl().$request->getPathInfo() === $this->homepageRouter->generate()) {
                return $this->homepageProvider->getHomepage();
            }

            return null;
        }
        if (!$request->attributes->has('_route_params')) {
            return null;
        }

        $routeParams = $request->attributes->get('_route_params');

        if (!is_array($routeParams) || !isset($routeParams[self::ROUTE_PARAM_SLUG])) {
            return null;
        }

        $slug = $this->findSlugMapItem($routeParams[self::ROUTE_PARAM_SLUG]);

        if (null === $slug) {
            return null;
        }

        return $this->em->getRepository($slug->getObjectClass())->find($slug->getObjectId());
    }

    /**
     * @param string $slug Slug
     *
     * @return \Darvin\ContentBundle\Entity\SlugMapItem|null
     */
    private function findSlugMapItem($slug)
    {
        return $this->em->getRepository(SlugMapItem::class)->findOneBy([
            'slug' => $slug,
        ]);
    }
}
