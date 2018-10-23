<?php declare(strict_types=1);
/**
 * @author    Igor Nikolaev <igor.sv.n@gmail.com>
 * @copyright Copyright (c) 2015, Darvin Studio
 * @link      https://www.darvin-studio.ru
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Darvin\AdminBundle\Metadata;

use Darvin\AdminBundle\Event\Metadata\MetadataEvent;
use Darvin\AdminBundle\Event\Metadata\MetadataEvents;
use Darvin\Utils\ORM\EntityResolverInterface;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Metadata manager
 */
class MetadataManager
{
    const CACHE_ID = 'darvinAdminMetadata';

    /**
     * @var \Doctrine\Common\Cache\Cache
     */
    protected $cache;

    /**
     * @var \Darvin\Utils\ORM\EntityResolverInterface
     */
    protected $entityResolver;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var \Darvin\AdminBundle\Metadata\MetadataPool
     */
    protected $metadataPool;

    /**
     * @var bool
     */
    protected $cacheDisabled;

    /**
     * @var array
     */
    protected $checkedIfHasMetadataClasses;

    /**
     * @var bool
     */
    protected $initialized;

    /**
     * @var \Darvin\AdminBundle\Metadata\Metadata[]
     */
    protected $metadata;

    /**
     * @param \Doctrine\Common\Cache\Cache                                $cache           Cache
     * @param \Darvin\Utils\ORM\EntityResolverInterface                   $entityResolver  Entity resolver
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher Event dispatcher
     * @param \Darvin\AdminBundle\Metadata\MetadataPool                   $metadataPool    Metadata pool
     * @param bool                                                        $cacheDisabled   Is cache disabled
     */
    public function __construct(
        Cache $cache,
        EntityResolverInterface $entityResolver,
        EventDispatcherInterface $eventDispatcher,
        MetadataPool $metadataPool,
        $cacheDisabled
    ) {
        $this->cache = $cache;
        $this->entityResolver = $entityResolver;
        $this->eventDispatcher = $eventDispatcher;
        $this->metadataPool = $metadataPool;
        $this->cacheDisabled = $cacheDisabled;

        $this->checkedIfHasMetadataClasses = $this->metadata = [];
        $this->initialized = false;
    }

    /**
     * @param object|string $entity Entity
     *
     * @return bool
     */
    public function hasMetadata($entity): bool
    {
        $class = $this->entityResolver->resolve(is_object($entity) ? get_class($entity) : $entity);

        if (!isset($this->checkedIfHasMetadataClasses[$class])) {
            $this->checkedIfHasMetadataClasses[$class] = true;

            try {
                $this->getMetadata($class);
            } catch (MetadataException $ex) {
                $this->checkedIfHasMetadataClasses[$class] = false;
            }
        }

        return $this->checkedIfHasMetadataClasses[$class];
    }

    /**
     * @param object|string $entity Entity
     *
     * @return array
     */
    public function getConfiguration($entity): array
    {
        return $this->getMetadata($entity)->getConfiguration();
    }

    /**
     * @param object|string $entity Entity
     *
     * @return \Darvin\AdminBundle\Metadata\Metadata
     * @throws \Darvin\AdminBundle\Metadata\MetadataException
     */
    public function getMetadata($entity): Metadata
    {
        $this->init();

        $class = $this->entityResolver->resolve(is_object($entity) ? get_class($entity) : $entity);

        if (!isset($this->metadata[$class])) {
            $child = $class;

            while ($parent = get_parent_class($child)) {
                if (isset($this->metadata[$parent])) {
                    $this->metadata[$class] = $this->metadata[$parent];

                    return $this->metadata[$parent];
                }

                $child = $parent;
            }

            throw new MetadataException(sprintf('Unable to get metadata for class "%s".', $class));
        }

        return $this->metadata[$class];
    }

    /**
     * @return \Darvin\AdminBundle\Metadata\Metadata[]
     */
    public function getAllMetadata(): array
    {
        $this->init();

        return $this->metadata;
    }

    final protected function init()
    {
        if ($this->initialized) {
            return;
        }
        if (!$this->initFromCache()) {
            $this->initAndCache();
        }

        $this->buildTree(array_keys($this->metadata));

        foreach ($this->metadata as $meta) {
            $this->eventDispatcher->dispatch(MetadataEvents::LOADED, new MetadataEvent($meta));
        }

        $this->initialized = true;
    }

    /**
     * @throws \Darvin\AdminBundle\Metadata\MetadataException
     */
    final protected function initAndCache()
    {
        $this->metadata = $this->metadataPool->getAllMetadata();

        if ($this->cacheDisabled) {
            return;
        }

        $serialized = serialize($this->metadata);

        if (!$this->cache->save(self::CACHE_ID, $serialized)) {
            throw new MetadataException('Unable to cache metadata.');
        }
    }

    /**
     * @return bool
     */
    final protected function initFromCache(): bool
    {
        if ($this->cacheDisabled) {
            return false;
        }

        $cached = $this->cache->fetch(self::CACHE_ID);

        if (false === $cached) {
            return false;
        }

        $unserialized = @unserialize($cached);

        if (!is_array($unserialized)) {
            return false;
        }

        $this->metadata = $unserialized;

        return true;
    }

    /**
     * @param array $parents Parent entity classes
     *
     * @throws \Darvin\AdminBundle\Metadata\MetadataException
     */
    final protected function buildTree(array $parents)
    {
        foreach ($parents as $parent) {
            $parent = $this->entityResolver->resolve($parent);

            $parentMeta = $this->metadata[$parent];

            $parentConfig = $parentMeta->getConfiguration();

            foreach ($parentConfig['children'] as $key => $child) {
                $child = $this->entityResolver->resolve($child);

                if (!isset($this->metadata[$child])) {
                    unset($parentConfig['children'][$key]);

                    continue;
                }

                $associated = false;
                $childMeta  = $this->metadata[$child];

                foreach ($childMeta->getMappings() as $property => $mapping) {
                    if (!$childMeta->isAssociation($property)
                        || ($mapping['targetEntity'] !== $parent && !in_array($mapping['targetEntity'], class_parents($parent)))
                    ) {
                        continue;
                    }

                    $childMeta->setParent(new AssociatedMetadata($property, $parentMeta));
                    $parentMeta->addChild(new AssociatedMetadata($property, $childMeta));

                    $associated = true;
                }
                if (!$associated) {
                    throw new MetadataException(
                        sprintf('Entity "%s" is not associated with entity "%s".', $child, $parent)
                    );
                }
            }

            $this->buildTree($parentConfig['children']);
        }
    }
}
