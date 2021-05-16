<?php

namespace Laminas\Form\Annotation;

use ArrayObject;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Form\Element;
use Laminas\Form\Exception;
use Laminas\Form\Factory;
use Laminas\Form\FieldsetInterface;
use Laminas\Form\FormFactoryAwareInterface;
use Laminas\Form\FormInterface;
use Laminas\Stdlib\ArrayUtils;
use ReflectionClass;
use ReflectionProperty;
use Reflector;

use function class_exists;
use function get_class;
use function is_array;
use function is_object;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function var_export;

/**
 * Parses the properties of a class for annotations in order to create a form
 * and input filter definition.
 */
class AnnotationBuilder implements EventManagerAwareInterface, FormFactoryAwareInterface
{
    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var Factory
     */
    protected $formFactory;

    /**
     * @var object
     */
    protected $entity;

    /**
     * @var bool
     */
    protected $preserveDefinedOrder = false;

    /**
     * Initialize the annotation registry
     */
    public function __construct()
    {
        // doctrine/annotations 1.x does not require autoloading by default
        AnnotationRegistry::registerLoader('class_exists');
    }

    /**
     * Set form factory to use when building form from annotations
     *
     * @param  Factory $formFactory
     * @return $this
     */
    public function setFormFactory(Factory $formFactory)
    {
        $this->formFactory = $formFactory;
        return $this;
    }

    /**
     * Set event manager instance
     *
     * @param  EventManagerInterface $events
     * @return $this
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers([
            __CLASS__,
            get_class($this),
        ]);
        (new ElementAnnotationsListener())->attach($events);
        (new FormAnnotationsListener())->attach($events);
        $this->events = $events;
        return $this;
    }

    /**
     * Retrieve form factory
     *
     * Lazy-loads the default form factory if none is currently set.
     *
     * @return Factory
     */
    public function getFormFactory()
    {
        if ($this->formFactory) {
            return $this->formFactory;
        }

        $this->formFactory = new Factory();
        return $this->formFactory;
    }

    /**
     * Get event manager
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (null === $this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Creates and returns a form specification for use with a factory
     *
     * @param  string|object $entity Either an instance or a valid class name for an entity
     * @param  bool $useAttributes true if PHP8 attributes should be parsed instead of PHPDoc annotations
     * @throws Exception\InvalidArgumentException if $entity is not an object or class name
     * @throws Exception\InvalidArgumentException if $useAttributes is true for PHP <= 8.0
     * @return ArrayObject
     */
    public function getFormSpecification($entity, bool $useAttributes = false)
    {
        if ($useAttributes && PHP_MAJOR_VERSION < 8) {
            throw new Exception\InvalidArgumentException(sprintf(
                'PHP 8.0 or newer is required when using PHP attributes. You are running PHP %s.',
                PHP_VERSION
            ));
        }

        if (! is_object($entity)) {
            if ((is_string($entity) && (! class_exists($entity))) // non-existent class
                || (! is_string($entity)) // not an object or string
            ) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s expects an object or valid class name; received "%s"',
                    __METHOD__,
                    var_export($entity, 1)
                ));
            }
        }

        $this->entity = $entity;
        list ($formSpec, $filterSpec) = $useAttributes
            ? $this->getFormSpecificationFromAttributes($entity)
            : $this->getFormSpecificationFromAnnotations($entity);

        if (! isset($formSpec['input_filter'])) {
            $formSpec['input_filter'] = $filterSpec;
        } elseif (is_array($formSpec['input_filter'])) {
            $formSpec['input_filter'] = ArrayUtils::merge($filterSpec->getArrayCopy(), $formSpec['input_filter']);
        }

        return $formSpec;
    }

    /**
     * Creates and returns a form specification for use with a factory
     *
     * Parses the object provided, and processes annotations for the class and
     * all properties. Information from annotations is then used to create
     * specifications for a form, its elements, and its input filter.
     *
     * @param  string|object $entity Either an instance or a valid class name for an entity
     * @return array
     */
    protected function getFormSpecificationFromAnnotations($entity)
    {
        $formSpec          = new ArrayObject();
        $filterSpec        = new ArrayObject();

        $reflection = new ReflectionClass($entity);
        $reader = new AnnotationReader();

        $annotations = new AnnotationCollection($reader->getClassAnnotations($reflection));
        $this->configureForm($annotations, $reflection, $formSpec, $filterSpec);

        foreach ($reflection->getProperties() as $property) {
            $annotations = new AnnotationCollection($reader->getPropertyAnnotations($property));
            $this->configureElement($annotations, $property, $formSpec, $filterSpec);
        }

        return [$formSpec, $filterSpec];
    }

    /**
     * Creates and returns a form specification for use with a factory
     *
     * Parses the attributes of the entity class by using the PHP8 reflection API.
     *
     * @param  string|object $entity Either an instance or a valid class name for an entity
     * @return array
     */
    public function getFormSpecificationFromAttributes($entity)
    {
        $formSpec          = new ArrayObject();
        $filterSpec        = new ArrayObject();

        $reflection = new ReflectionClass($entity);
        $annotations = new AnnotationCollection();
        foreach ($reflection->getAttributes() as $attribute) {
            $annotations[] = $attribute->newInstance();
        }

        $this->configureForm($annotations, $reflection, $formSpec, $filterSpec);

        foreach ($reflection->getProperties() as $property) {
            $annotations = new AnnotationCollection();
            foreach ($property->getAttributes() as $attribute) {
                $annotations[] = $attribute->newInstance();
            }

            $this->configureElement($annotations, $property, $formSpec, $filterSpec);
        }

        return [$formSpec, $filterSpec];
    }

    /**
     * Create a form from an object.
     *
     * @param  string|object $entity
     * @param  bool $useAttributes true if PHP8 attributes should be parsed instead of PHPDoc annotations
     * @return FormInterface
     */
    public function createForm($entity, bool $useAttributes = false)
    {
        $formSpec    = ArrayUtils::iteratorToArray($this->getFormSpecification($entity, $useAttributes));
        $formFactory = $this->getFormFactory();
        return $formFactory->createForm($formSpec);
    }

    /**
     * Get the entity used to construct the form.
     *
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Configure the form specification from annotations
     *
     * @param  AnnotationCollection $annotations
     * @param  ReflectionClass $reflection
     * @param  ArrayObject $formSpec
     * @param  ArrayObject $filterSpec
     * @return void
     * @triggers discoverName
     * @triggers configureForm
     */
    protected function configureForm($annotations, $reflection, $formSpec, $filterSpec)
    {
        $name                   = $this->discoverName($annotations, $reflection);
        $formSpec['name']       = $name;
        $formSpec['attributes'] = [];
        $formSpec['elements']   = [];
        $formSpec['fieldsets']  = [];

        $events = $this->getEventManager();
        foreach ($annotations as $annotation) {
            $events->trigger(__FUNCTION__, $this, [
                'annotation' => $annotation,
                'name'       => $name,
                'formSpec'   => $formSpec,
                'filterSpec' => $filterSpec,
            ]);
        }
    }

    /**
     * Configure an element from annotations
     *
     * @param  AnnotationCollection $annotations
     * @param  ReflectionProperty $reflection
     * @param  ArrayObject $formSpec
     * @param  ArrayObject $filterSpec
     * @return void
     * @triggers checkForExclude
     * @triggers discoverName
     * @triggers configureElement
     */
    protected function configureElement($annotations, $reflection, $formSpec, $filterSpec)
    {
        // If the element is marked as exclude, return early
        if ($this->checkForExclude($annotations)) {
            return;
        }

        $events = $this->getEventManager();
        $name   = $this->discoverName($annotations, $reflection);

        $elementSpec = new ArrayObject([
            'flags' => [],
            'spec'  => [
                'name' => $name,
            ],
        ]);
        $inputSpec = new ArrayObject([
            'name' => $name,
        ]);

        $params = [
            'name'        => $name,
            'elementSpec' => $elementSpec,
            'inputSpec'   => $inputSpec,
            'formSpec'    => $formSpec,
            'filterSpec'  => $filterSpec,
        ];
        foreach ($annotations as $annotation) {
            $params['annotation'] = $annotation;
            $events->trigger(__FUNCTION__, $this, $params);
        }

        // Since "type" is a reserved name in the filter specification,
        // we need to add the specification without the name as the key.
        // In all other cases, though, the name is fine.
        if ($params['inputSpec']->count() > 1) {
            if ($name === 'type') {
                $filterSpec[] = $params['inputSpec'];
            } else {
                $filterSpec[$name] = $params['inputSpec'];
            }
        }

        $elementSpec = $params['elementSpec'];
        $type        = isset($elementSpec['spec']['type'])
            ? $elementSpec['spec']['type']
            : Element::class;

        // Compose as a fieldset or an element, based on specification type.
        // If preserve defined order is true, all elements are composed as elements to keep their ordering
        if (! $this->preserveDefinedOrder() && is_subclass_of($type, FieldsetInterface::class)) {
            if (! isset($formSpec['fieldsets'])) {
                $formSpec['fieldsets'] = [];
            }
            $formSpec['fieldsets'][] = $elementSpec;
        } else {
            if (! isset($formSpec['elements'])) {
                $formSpec['elements'] = [];
            }
            $formSpec['elements'][] = $elementSpec;
        }
    }

    /**
     * @param bool $preserveDefinedOrder
     * @return $this
     */
    public function setPreserveDefinedOrder($preserveDefinedOrder)
    {
        $this->preserveDefinedOrder = (bool) $preserveDefinedOrder;
        return $this;
    }

    /**
     * @return bool
     */
    public function preserveDefinedOrder()
    {
        return $this->preserveDefinedOrder;
    }

    /**
     * Discover the name of the given form or element
     *
     * @param  AnnotationCollection $annotations
     * @param  Reflector $reflection
     * @return string
     */
    protected function discoverName($annotations, $reflection)
    {
        $event = new Event();
        $event->setName(__FUNCTION__);
        $event->setTarget($this);
        $event->setParams([
            'annotations' => $annotations,
            'reflection'  => $reflection,
        ]);

        // @codingStandardsIgnoreStart
        $results = $this->getEventManager()->triggerEventUntil(
            static function ($r) {
            return is_string($r) && ! empty($r);
            },
            $event
        );
        // @codingStandardsIgnoreEnd

        return $results->last();
    }

    /**
     * Determine if an element is marked to exclude from the definitions
     *
     * @param  AnnotationCollection $annotations
     * @return true|false
     */
    protected function checkForExclude($annotations)
    {
        $event = new Event();
        $event->setName(__FUNCTION__);
        $event->setTarget($this);
        $event->setParams(['annotations' => $annotations]);

        // @codingStandardsIgnoreStart
        $results = $this->getEventManager()->triggerEventUntil(
            static function ($r) {
            return true === $r;
            },
            $event
        );
        // @codingStandardsIgnoreEnd

        return (bool) $results->last();
    }
}
