<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2018, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Controller\Crud;

use Darvin\AdminBundle\Event\Crud\Controller\ControllerEvent;
use Darvin\AdminBundle\Event\Crud\Controller\CrudControllerEvents;
use Darvin\AdminBundle\Event\Crud\CreatedEvent;
use Darvin\AdminBundle\Event\Crud\CrudEvents;
use Darvin\AdminBundle\Form\AdminFormFactoryInterface;
use Darvin\AdminBundle\Route\AdminRouterInterface;
use Darvin\AdminBundle\Security\Permissions\Permission;
use Darvin\ContentBundle\Translatable\TranslationJoinerInterface;
use Darvin\ContentBundle\Translatable\TranslationsInitializerInterface;
use Darvin\Utils\Flash\FlashNotifierInterface;
use Darvin\Utils\HttpFoundation\AjaxResponse;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * CRUD controller new action
 */
class NewAction extends AbstractAction
{
    /**
     * @var \Symfony\Component\PropertyAccess\PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var \Darvin\ContentBundle\Translatable\TranslationsInitializerInterface
     */
    private $translationsInitializer;

    /**
     * @var \Darvin\ContentBundle\Translatable\TranslationJoinerInterface
     */
    private $translationJoiner;

    /**
     * @var string[]
     */
    private $locales;

    /**
     * @param \Symfony\Component\PropertyAccess\PropertyAccessorInterface         $propertyAccessor        Property accessor
     * @param \Darvin\ContentBundle\Translatable\TranslationsInitializerInterface $translationsInitializer Translations initializer
     * @param \Darvin\ContentBundle\Translatable\TranslationJoinerInterface       $translationJoiner       Translation joiner
     * @param string[]                                                            $locales                 Locales
     */
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        TranslationsInitializerInterface $translationsInitializer,
        TranslationJoinerInterface $translationJoiner,
        array $locales
    ) {
        $this->propertyAccessor = $propertyAccessor;
        $this->translationsInitializer = $translationsInitializer;
        $this->translationJoiner = $translationJoiner;
        $this->locales = $locales;
    }

    /**
     * @param bool $widget Is widget
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(bool $widget = false): Response
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('widget')) {
            $widget = true;
        }

        $this->checkPermission(Permission::CREATE_DELETE);

        list($parentEntity, $association) = $this->getParentEntityDefinition($request);

        $this->eventDispatcher->dispatch(CrudControllerEvents::STARTED, new ControllerEvent($this->getMeta(), $this->userManager->getCurrentUser(), __FUNCTION__));

        $entityClass = $this->getEntityClass();

        $entity = new $entityClass();

        if ($this->getMeta()->hasParent()) {
            $this->propertyAccessor->setValue($entity, $association, $parentEntity);
        }
        if ($this->translationJoiner->isTranslatable($entityClass)) {
            $this->translationsInitializer->initializeTranslations($entity, $this->locales);
        }

        $this->handleFilterForm($entity, $request);

        $form = $this->adminFormFactory->createEntityForm(
            $this->getMeta(),
            $entity,
            $this->getName(),
            $this->adminRouter->generate($entity, $entityClass, AdminRouterInterface::TYPE_NEW, [
                'widget' => $widget,
            ]),
            $widget ? [AdminFormFactoryInterface::SUBMIT_INDEX] : $this->getSubmitButtons()
        )->handleRequest($request);

        if (!$form->isSubmitted()) {
            return new Response($this->renderNewTemplate($form, $parentEntity, $widget, $request->isXmlHttpRequest()));
        }
        if (!$form->isValid()) {
            if (!$request->isXmlHttpRequest()) {
                $this->flashNotifier->formError();
            }

            $html = $this->renderNewTemplate($form, $parentEntity, $widget, $request->isXmlHttpRequest());

            if ($request->isXmlHttpRequest()) {
                return new AjaxResponse($html, false, FlashNotifierInterface::MESSAGE_FORM_ERROR);
            }

            return new Response($html);
        }

        $this->em->persist($entity);
        $this->em->flush();

        $this->eventDispatcher->dispatch(CrudEvents::CREATED, new CreatedEvent($this->getMeta(), $this->userManager->getCurrentUser(), $entity));

        $message     = sprintf('%saction.new.success', $this->getMeta()->getBaseTranslationPrefix());
        $redirectUrl = $this->successRedirect($form, $entity);

        if ($request->isXmlHttpRequest()) {
            return new AjaxResponse(null, true, $message, [], $redirectUrl);
        }

        $this->flashNotifier->success($message);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param object                                    $entity  Entity
     * @param \Symfony\Component\HttpFoundation\Request $request Request
     */
    private function handleFilterForm($entity, Request $request): void
    {
        if (!$request->isMethod('get')) {
            return;
        }

        $meta = $this->getMeta();

        if (!$meta->isFilterFormEnabled()) {
            return;
        }

        $config = $this->getConfig();

        $fields = $config['form']['new']['fields'];

        foreach ($config['form']['new']['field_groups'] as $fieldGroup) {
            $fields = array_replace($fields, $fieldGroup);
        }
        foreach ($fields as $field => $options) {
            if (!$meta->isAssociation($field) || ClassMetadata::MANY_TO_ONE !== $meta->getMappings()[$field]['type']) {
                unset($fields[$field]);
            }
        }

        $this->adminFormFactory->createFilterForm($meta, null, null, [
            'action'     => null,
            'data'       => $entity,
            'data_class' => $meta->getEntityClass(),
            'fields'     => array_keys($fields),
        ])->handleRequest($request);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface $form         Entity form
     * @param object|null                           $parentEntity Parent entity
     * @param bool                                  $widget       Is widget
     * @param bool                                  $partial      Whether to render partial
     *
     * @return string
     */
    private function renderNewTemplate(FormInterface $form, $parentEntity, bool $widget, bool $partial = false): string
    {
        if ($widget) {
            $partial = true;
        }

        return $this->renderTemplate([
            'form'          => $form->createView(),
            'is_widget'     => $widget,
            'meta'          => $this->getMeta(),
            'parent_entity' => $parentEntity,
        ], $partial);
    }
}