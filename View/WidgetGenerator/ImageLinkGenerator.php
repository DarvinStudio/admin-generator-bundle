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

use Darvin\AdminBundle\Configuration\Configuration;
use Darvin\ImageBundle\Entity\Image\AbstractImage;
use Darvin\ImageBundle\UrlBuilder\Filter\ResizeFilter;
use Darvin\ImageBundle\UrlBuilder\UrlBuilderInterface;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Image link view widget generator
 */
class ImageLinkGenerator extends AbstractWidgetGenerator
{
    /**
     * @var \Darvin\ImageBundle\UrlBuilder\UrlBuilderInterface
     */
    private $imageUrlBuilder;

    /**
     * @param \Darvin\ImageBundle\UrlBuilder\UrlBuilderInterface $imageUrlBuilder Image URL builder
     */
    public function setImageUrlBuilder(UrlBuilderInterface $imageUrlBuilder)
    {
        $this->imageUrlBuilder = $imageUrlBuilder;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateWidget($entity, array $options, $property)
    {
        $image = isset($options['property']) ? $this->getPropertyValue($entity, $options['property']) : $entity;

        if (!is_object($image)) {
            throw new WidgetGeneratorException(sprintf('Image must be object, "%s" provided.', gettype($image)));
        }
        if (!$image instanceof AbstractImage) {
            $message = sprintf(
                'Image object "%s" must be instance of "%s".',
                ClassUtils::getClass($image),
                AbstractImage::ABSTRACT_IMAGE_CLASS
            );

            throw new WidgetGeneratorException($message);
        }
        if (!$this->imageUrlBuilder->fileExists($image)) {
            return '';
        }

        return $this->render($options, array(
            'filtered_url' => $this->imageUrlBuilder->buildUrlToFilter(
                $image,
                ResizeFilter::NAME,
                $options['filter_params']
            ),
            'name'         => $image->getName(),
            'original_url' => $this->imageUrlBuilder->buildUrlToOriginal($image),
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefault('filter_params', array(
                'size_name' => Configuration::IMAGE_SIZE_ADMIN,
                'outbound'  => true,
            ))
            ->setDefined('property')
            ->setAllowedTypes('filter_params', 'array')
            ->setAllowedTypes('property', 'string');
    }
}
