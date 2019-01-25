<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2016-2019, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Controller;

use Darvin\AdminBundle\CKEditor\CKEditorWidgetInterface;
use Darvin\ContentBundle\Widget\WidgetException;
use Darvin\ContentBundle\Widget\WidgetPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * CKEditor controller
 */
class CKEditorController extends AbstractController
{
    /**
     * @param string $widgetName Widget name
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function pluginAction(string $widgetName): Response
    {
        try {
            $widget = $this->getWidgetPool()->getWidget($widgetName);
        } catch (WidgetException $ex) {
            throw $this->createNotFoundException($ex->getMessage());
        }
        if (!$widget instanceof CKEditorWidgetInterface) {
            throw $this->createNotFoundException(
                sprintf('Widget class "%s" must be instance of "%s".', get_class($widget), CKEditorWidgetInterface::class)
            );
        }

        $response = $this->render('@DarvinAdmin/ck_editor/plugin.js.twig', [
            'icon'   => $this->getWidgetIcon($widget),
            'widget' => $widget,
        ]);
        $response->headers->set('Content-Type', 'application/javascript');

        if (!$this->container->getParameter('kernel.debug')) {
            $response->setMaxAge(365 * 24 * 60 * 60);
        }

        return $response;
    }

    /**
     * @param \Darvin\AdminBundle\CKEditor\CKEditorWidgetInterface $widget Widget
     *
     * @return string|null
     */
    private function getWidgetIcon(CKEditorWidgetInterface $widget): ?string
    {
        $options = $widget->getResolvedOptions();

        if (!isset($options['icon']) || empty($options['icon'])) {
            return null;
        }

        $content = @file_get_contents($options['icon']);

        if (!$content) {
            return null;
        }

        $info = finfo_open(FILEINFO_MIME_TYPE);
        $mime = @finfo_buffer($info, $content);

        if (!$mime) {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode($content));
    }

    /**
     * @return \Darvin\ContentBundle\Widget\WidgetPoolInterface
     */
    private function getWidgetPool(): WidgetPoolInterface
    {
        return $this->get('darvin_content.widget.pool');
    }
}
