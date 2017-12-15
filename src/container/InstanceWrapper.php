<?php
namespace Spine;

/**
 * @since  2014-01-20
 * @author Lance Rushing
 */
class InstanceWrapper
{
    /**
     * @var \ReflectionClass
     */
    public $reflectionClass;

    /**
     * @var object
     */
    public $instance;

    /**
     * @var array
     */
    public $factoryMethodArguments = [];

    /**
     * @var array <Callable>
     */
    public $injectionMethods = [];

    /**
     * @var \ReflectionFunction|\ReflectionMethod
     */
    public $factoryMethod;
}
