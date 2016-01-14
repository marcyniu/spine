<?php

namespace Spine;

use ReflectionClass;
use ReflectionException;
use ReflectionObject;

/**
 * @since  2014-01-20
 * @author Lance Rushing
 */
class Container
{
    /**
     * @var array
     */
    protected $objects = [];

    /**
     * @var array <ContainerFactory>
     */
    private $typeFactories = [];

    /**
     * Used to detect circular __construct() dependencies
     *
     * @var array
     */
    private $resolvingClasses = [];

    /**
     * Array of [<ReflectionMethod>, <instance>]
     *
     * Keeps track of any inject*() methods that need to be called after object construction
     *
     * @var array
     */
    private $injectionMethods = [];

    /**
     *
     */
    public function __construct()
    {
        // register itself. Note: be careful with depending on the container, only factory like classes should ever need it....
        $this->register($this);
    }

    /**
     * Will invoke the given $callable with all of it's arguments resolved
     *
     * @param $callable
     *
     * @return mixed
     */
    public function callFunction($callable)
    {
        $reflectionFunction = new \ReflectionFunction($callable);
        $args               = $this->resolveArguments($reflectionFunction);
        $this->invokeInjectMethods();
        return $reflectionFunction->invokeArgs($args);
    }

    /**
     * @param mixed  $class Either a string containing the name of the class to reflect, or an object.
     * @param string $methodName
     *
     * @return mixed
     */
    public function callMethod($class, $methodName)
    {
        $reflectionClass  = new ReflectionClass($class);
        $reflectionMethod = $reflectionClass->getMethod($methodName);
        $args             = $this->resolveArguments($reflectionMethod);
        $this->invokeInjectMethods();
        return $reflectionMethod->invokeArgs($class, $args);
    }

    /**
     * Registers and Object -- fluent
     *
     * @param      $object
     * @param bool $registerParentClasses
     *
     * @return Container
     */
    public function register($object, $registerParentClasses = true)
    {
        $reflectionObject = new ReflectionObject($object);

        $this->registerType($object, $reflectionObject, $registerParentClasses);

        return $this;
    }

    /**
     * @param ReflectionClass $reflectionClass class/interface the object extends/implements
     * @param object          $object
     * @param bool            $registerParentClasses
     *
     * @return $this
     */
    public function registerType($object, ReflectionClass $reflectionClass, $registerParentClasses)
    {

        $this->objects[$reflectionClass->getName()] = $object;

        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($object, $reflectionClass);
            $this->registerInterfaces($object, $reflectionClass);
        }
        return $this;
    }

    /**
     * @param string   $type
     * @param callable $callableFactory
     * @param bool     $registerParents Will also register the factory for the parent class of $type
     *
     * @return $this
     */
    public function registerTypeFactory($type, $callableFactory, $registerParents = false)
    {

        $containerFactory           = new ContainerFactory();
        $containerFactory->callable = $callableFactory;

        $reflectionClass = new ReflectionClass($type);

        $this->typeFactories[$reflectionClass->getName()] = $containerFactory;

        if ($registerParents) {
            $this->registerFactoryForParents($containerFactory, $reflectionClass);
            $this->registerFactoryForInterfaces($containerFactory, $reflectionClass);
        }

        return $this;
    }

    /**
     * @param string $className
     *
     * @throws ContainerException
     * @return mixed
     */
    public function resolve($className)
    {
        $object = $this->resolveClassName($className);
        $this->invokeInjectMethods();
        return $object;
    }

    /**
     * @param ContainerFactory $containerFactory
     *
     * @return object
     * @throws ContainerException
     */
    private function callFactory(ContainerFactory $containerFactory)
    {

        if (!is_object($containerFactory->instance)) {

            $instance = $this->callFunction($containerFactory->callable);
            if (!is_object($instance)) {
                $reflectionFunction = new \ReflectionFunction($containerFactory->callable);
                $error              = sprintf("%s (%u:%u)", $reflectionFunction->getFileName(),
                    $reflectionFunction->getStartLine(), $reflectionFunction->getEndLine());
                throw new ContainerException("TypeFactory did not return an object. $error");
            }
            $containerFactory->instance = $instance;
        }

        return $containerFactory->instance;
    }

    /**
     * Will create an instance of the given class.
     * Note: All constructor arguments must have 'resolvable' type hinting
     *
     * @param $reflectionClass
     *
     * @return mixed
     */
    private function createInstance(ReflectionClass $reflectionClass)
    {
        $constructorReflectMethod = $reflectionClass->getConstructor();

        if ($constructorReflectMethod === null) { // no constructor.
            $instance = $reflectionClass->newInstance();
        } else {
            $args     = $this->resolveArguments($constructorReflectMethod);
            $instance = $reflectionClass->newInstanceArgs($args);
        }
        return $instance;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    private function getSignature(\ReflectionFunctionAbstract $reflectionMethod)
    {
        $params = $reflectionMethod->getParameters();

        $signature = array();
        /** @var $reflectionParameter \ReflectionParameter */
        foreach ($params as $reflectionParameter) {

            try {
                $class = $reflectionParameter->getClass();
            } catch (ReflectionException $e) {
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf(
                    "Method '%s:%s()' has unknown type hint for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name,
                    $reflectionParameter->name,
                    $reflectionMethod->getFileName(),
                    $reflectionMethod->getEndLine()
                );
                throw new ContainerException($msg, 999, $e);
            }

            if (is_null($class)) { // no class for argument
                if ($reflectionParameter->isOptional()) { // has default value
                    continue; // skip adding this parameter.. will break for mixed signatures.
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf(
                    "Method '%s:%s()' has no type hint  and no default value for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name,
                    $reflectionParameter->name,
                    $reflectionMethod->getFileName(),
                    $reflectionMethod->getEndLine()
                );
                throw new ContainerException($msg);

            }

            $signature[$reflectionParameter->getName()] = $class instanceof ReflectionClass ? ltrim(
                $class->getName(),
                '\\'
            ) : null;
        }
        return $signature;
    }

    /**
     * Will invoke the injection methods
     */
    private function invokeInjectMethods()
    {

        /** @var $injectMethod \ReflectionMethod */
        /** @var mixed $instance */
        while ($injectMethodAndInstance = array_shift($this->injectionMethods)) {
            list($injectMethod, $instance) = $injectMethodAndInstance;
            $args = $this->resolveArguments($injectMethod);
            $injectMethod->invokeArgs($instance, $args);
        }
    }

    /**
     * Instantiates the given className
     *
     * @param $className
     *
     * @throws ContainerException
     * @return object
     */
    private function make($className)
    {

        $this->resolvingClasses[$className] = 1;

        $reflectionClass = new ReflectionClass($className);
        $key             = $reflectionClass->name;

        // Confirm it can be created.
        if (!$reflectionClass->isInstantiable() && !isset($this->typeFactories[$key])) {
            throw new ContainerException("The type " . $reflectionClass->name . " is not instantiable");
        }

        if (isset($this->typeFactories[$key])) {
            $instance = $this->callFactory($this->typeFactories[$key]);
        } else {
            $instance = $this->createInstance($reflectionClass);
        }

        unset($this->resolvingClasses[$className]);

        $this->registerType($instance, $reflectionClass, true);
        $this->registerInjectMethods($reflectionClass, $instance);

        return $instance;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    private function resolveArguments(\ReflectionFunctionAbstract $reflectionMethod)
    {

        $signature = $this->getSignature($reflectionMethod);
        $args      = array();
        foreach ($signature as $name => $type) {

            if (isset($this->resolvingClasses[$type])) {

                if ($reflectionMethod instanceof \ReflectionFunction ) {
                    /** @var \ReflectionFunction $reflectionMethod */
                    $msg = sprintf('Circular Reference Detected. Function defined in "%s:%s" requires type "%s".', $reflectionMethod->getFileName(), $reflectionMethod->getEndLine(), $type );
                } else {
                    /** @var \ReflectionMethod $reflectionMethod */
                    $msg = sprintf('Circular Reference Detected. Method "%s::%s" defined in "%s:%s requires type "%s".', $reflectionMethod->class,$reflectionMethod->getName(), $reflectionMethod->getFileName(), $reflectionMethod->getEndLine(), $type);
                }

                throw new ContainerException($msg);
            }
            $args[$name] = $this->resolveClassName($type);
        }
        return $args;

    }

    private function resolveClassName($className)
    {
        $className = trim($className, "\\");

        if (!class_exists($className) && !interface_exists($className)) {
            throw new ContainerException("Class/Interface '$className' does not exist");
        }

        $key = $className; // @note, this used to make lower case here, b/c auto loader might be case sensitive, but maybe not anymore
        if (!isset($this->objects[$key])) {
            $this->make($className);
        }

        return $this->objects[$key];

    }

    /**
     * Will register any methods, who's name starts with 'inject'
     *
     * @param ReflectionClass $reflectionClass
     * @param                 $instance
     */
    private function registerInjectMethods(ReflectionClass $reflectionClass, $instance)
    {

        // find methods matching /^inject/
        $injectMethods = (array_filter(
            $reflectionClass->getMethods(),
            function (\ReflectionMethod $reflectionMethod) {
                return (preg_match("/^inject/", $reflectionMethod->getName()));
            }
        ));

        // Invoke the methods
        /** @var $injectMethod \ReflectionMethod */
        foreach ($injectMethods as $injectMethod) {
            $this->injectionMethods[] = [$injectMethod, $instance];
        }
    }

    /**
     * @param                 $object
     * @param ReflectionClass $reflectionClass
     */
    private function registerParents($object, ReflectionClass $reflectionClass)
    {
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $this->objects[$parentClass->getName()] = $object;
            $this->registerParents($object, $parentClass);
        }
    }

    /**
     * @param                 $object
     * @param ReflectionClass $reflectionClass
     */
    private function registerInterfaces($object, ReflectionClass $reflectionClass)
    {
        $interfaceNames = $reflectionClass->getInterfaceNames();
        foreach ($interfaceNames as $interfaceName) {
            $this->objects[$interfaceName] = $object;
        }
    }

    /**
     * @param ContainerFactory $containerFactory
     * @param ReflectionClass  $reflectionClass
     */
    private function registerFactoryForParents(ContainerFactory $containerFactory, ReflectionClass $reflectionClass)
    {
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $this->typeFactories[$parentClass->getName()] = $containerFactory;
            // recurse
            $this->registerFactoryForParents($containerFactory, $parentClass);
        }
    }

    /**
     * @param ContainerFactory $containerFactory
     * @param ReflectionClass  $reflectionClass
     */
    private function registerFactoryForInterfaces(ContainerFactory $containerFactory, ReflectionClass $reflectionClass)
    {
        $interfaceNames = $reflectionClass->getInterfaceNames();
        foreach ($interfaceNames as $interfaceName) {
            $this->typeFactories[$interfaceName] = $containerFactory;
        }
    }
}

class ContainerFactory
{
    public $instance;
    public $callable;
}
