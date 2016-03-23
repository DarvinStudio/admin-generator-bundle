<?php
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2016, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\View\WidgetGenerator;

/**
 * Empty view widget generator
 */
class EmptyWidgetGenerator implements WidgetGeneratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function generate($entity, array $options = array(), $property = null)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'empty_widget';
    }
}