<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2017, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\View\Widget\Widget;

use Darvin\AdminBundle\Security\Permissions\Permission;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * File upload link view widget
 */
class FileUploadLinkWidget extends AbstractWidget
{
    /**
     * @var \Vich\UploaderBundle\Storage\StorageInterface
     */
    private $uploadStorage;

    /**
     * @param \Vich\UploaderBundle\Storage\StorageInterface $uploadStorage Upload storage
     */
    public function setUploadStorage(StorageInterface $uploadStorage)
    {
        $this->uploadStorage = $uploadStorage;
    }

    /**
     * {@inheritdoc}
     */
    protected function createContent($entity, array $options): ?string
    {
        $property = $options['property'];

        $url = $this->uploadStorage->resolveUri(
            $entity,
            !empty($options['file_property']) ? $options['file_property'] : $property.'File'
        );

        return $url
            ? $this->render($options, [
                'filename' => $this->getPropertyValue($entity, $property),
                'url'      => $url,
            ])
            : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefault('file_property', null)
            ->setAllowedTypes('file_property', [
                'string',
                'null',
            ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRequiredPermissions(): iterable
    {
        yield Permission::VIEW;
    }
}
