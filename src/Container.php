<?php

namespace Spine;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;

/**
 * @since  2014-01-20
 * @author Lance Rushing
 */
class Container
{
    const PARENTS_REGISTER_IF_NOT_REGISTERED = "PARENTS_REGISTER_IF_NOT_REGISTERED";
    const PARENTS_REGISTER_ALWAYS = "PARENTS_REGISTER_ALWAYS";
    const PARENTS_REGISTER_NEVER = "PARENTS_REGISTER_NEVER";

    /**
     * @var array <InstanceWrapper>
     */
    private $instanceWrappers = [];

    /**
     * Used to detect circular __construct() dependencies
     *
     * @var array
     */
    private $resolvingClasses = [];

    /**
     * Array of [$this->$levelDepth][ [<instance>, <ReflectionMethod>], ... ]
     *
     * Track inject*() methods that need to be called after object construction
     *
     * @var array
     */
    private $pendingInjectionMethods = [];

    /**
     * Tracks the "depth" of public calls
     *
     * Used to resolve $this->pendingInjectionMethods
     *
     * @var int
     */
    private $levelDepth = -1;


    public function __construct()
    {
        // register itself. Note: be careful depending on the container, only factory like classes should ever need it....
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
        $this->increaseDepth();
        $reflectionFunction = new \ReflectionFunction($callable);
        $args               = $this->resolveArguments($reflectionFunction);
        $obj                = $reflectionFunction->invokeArgs($args);
        $this->invokePendingInjectFactories();
        return $obj;
    }

    /**
     * @param mixed  $class Either a class name, or an object.
     * @param string $methodName
     *
     * @return mixed
     */
    public function callMethod($class, $methodName)
    {
        $this->increaseDepth();
        $reflectionClass  = new ReflectionClass($class);
        $reflectionMethod = $reflectionClass->getMethod($methodName);

        $args = $this->resolveArguments($reflectionMethod);

        $object = $reflectionMethod->invokeArgs($class, $args);
        $this->invokePendingInjectFactories();
        return $object;
    }

    /**
     * Register an Object -- fluent
     *
     * @param      $object
     * @param bool $registerParents
     *
     * @return Container
     */
    public function register($object, $registerParents = true)
    {
        $reflectionObject = new ReflectionObject($object);

        $this->createAndRegisterInstanceWrapper($object, $reflectionObject,
            $registerParents ? self::PARENTS_REGISTER_ALWAYS : self::PARENTS_REGISTER_NEVER);

        return $this;
    }

    /**
     * @param string $className class/interface Name
     * @param object $object
     * @param bool   $registerParents
     *
     * @return $this
     */
    public function registerType($className, $object, $registerParents = false)
    {
        $reflectionClass = new ReflectionClass($className);
        $this->createAndRegisterInstanceWrapper($object, $reflectionClass,
            $registerParents ? self::PARENTS_REGISTER_ALWAYS : self::PARENTS_REGISTER_NEVER);
        return $this;

    }


    /**
     * @param string   $className
     * @param callable $callableFactory
     * @param bool     $registerParents Will also register the factory for the parent class of $type
     *
     * @return $this
     */
    public function registerTypeFactory($className, $callableFactory, $registerParents = false)
    {
        if (!is_bool($registerParents)) {
            throw new \InvalidArgumentException(sprintf('$registerParents must be bool, but got %s value: %s',
                gettype($registerParents), $registerParents));
        }

        $reflectionClass = new ReflectionClass($className);
        $this->createAndRegisterInstanceWrapper(null, $reflectionClass,
            $registerParents ? self::PARENTS_REGISTER_ALWAYS : self::PARENTS_REGISTER_NEVER,
            new ReflectionFunction($callableFactory));

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
        $this->increaseDepth();
        $reflectionClass = new ReflectionClass($className);

        $this->buildInstanceWrapperForClass($reflectionClass);

        $object = $this->resolveReflectionClass($reflectionClass);
        $this->invokePendingInjectFactories();
        return $object;
    }


    /**
     * @param object                     $object
     * @param ReflectionClass            $reflectionClass class/interface the object extends/implements
     * @param string                     $registerParents one the self::REGISTER_PARENTS constants
     * @param ReflectionFunctionAbstract $factoryReflectionFunction
     * @return InstanceWrapper $instanceWrapper
     */
    private function createAndRegisterInstanceWrapper($object, ReflectionClass $reflectionClass, $registerParents, ReflectionFunctionAbstract $factoryReflectionFunction = null)
    {
        $instanceWrapper                  = new InstanceWrapper();
        $instanceWrapper->instance        = $object;
        $instanceWrapper->reflectionClass = $reflectionClass;

        if ($factoryReflectionFunction) {
            $instanceWrapper->factoryMethod = $factoryReflectionFunction;
            //$instanceWrapper->factoryMethodArguments = $this->buildArgumentsInstanceWrappersForFunction($factoryReflectionFunction);
        }


        $this->registerInstanceWrapperForClass($instanceWrapper, $reflectionClass);


        $injectionMethods = $this->getInjectMethods($reflectionClass);
        /** @var ReflectionMethod $injectionReflectionMethod */
        foreach ($injectionMethods as $injectionReflectionMethod) {
            $instanceWrapper->injectionMethods[] = $injectionReflectionMethod;
        }


        $this->registerParentClasses($reflectionClass, $instanceWrapper, $registerParents);
        $this->registerInterfaces($reflectionClass, $instanceWrapper, $registerParents);

        return $instanceWrapper;
    }

    private function buildInstanceWrapperForClass(ReflectionClass $reflectionClass)
    {
        if (!isset($this->instanceWrappers[$reflectionClass->getName()])) {

            $instanceWrapper = $this->createAndRegisterInstanceWrapper(null, $reflectionClass,
                self::PARENTS_REGISTER_IF_NOT_REGISTERED);

            $constructorReflectMethod = $reflectionClass->getConstructor();

            if ($constructorReflectMethod === null) { // no constructor.
                // go ahead an instantiate
                $instanceWrapper->instance = $reflectionClass->newInstance();
            } else {
                $instanceWrapper->factoryMethod = $constructorReflectMethod;
                //$instanceWrapper->factoryMethodArguments = $this->buildArgumentsInstanceWrappersForFunction($instanceWrapper->factoryMethod);
            }
        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     *
     * @return array
     */
    private function buildArgumentsInstanceWrappersForFunction(ReflectionFunctionAbstract $reflectionFunction)
    {
        $arguments = [];
        $signature = $this->getSignature($reflectionFunction);
        /** @var ReflectionClass $signatureReflectionClass */
        foreach ($signature as $argName => $signatureReflectionClass) {
            $arguments[$argName] = $signatureReflectionClass;
            $this->buildInstanceWrapperForClass($signatureReflectionClass);
        }

        return $arguments;
    }

    /**
     * @param InstanceWrapper $instanceWrapper
     *
     * @return object
     * @throws ContainerException
     */
    private function getInstanceFromWrapper(InstanceWrapper $instanceWrapper)
    {

        if (!is_object($instanceWrapper->instance)) {

            $reflectionFunction                      = $instanceWrapper->factoryMethod;
            $instanceWrapper->factoryMethodArguments = $this->buildArgumentsInstanceWrappersForFunction($instanceWrapper->factoryMethod);

            $args = [];
            foreach ($instanceWrapper->factoryMethodArguments as $reflectionClass) {
                $args[] = $this->resolveReflectionClass($reflectionClass);
            }

            /** @var ReflectionFunction|ReflectionMethod $reflectionFunction */
            if ($reflectionFunction->isClosure()) {
                $instance = $reflectionFunction->invokeArgs($args);
            } elseif ($reflectionFunction->isConstructor()) {
                if (!$instanceWrapper->reflectionClass->isInstantiable()) {
                    throw new ContainerException("Cannot call Private Constructor or Abstract Class for " . $reflectionFunction->getDeclaringClass()->getName());
                }
                $instance = $instanceWrapper->reflectionClass->newInstanceArgs($args);
            } else {
                $instance = $reflectionFunction->invokeArgs($args);
            }

            if (!is_object($instance)) {
                $error = sprintf("%s (%u:%u)", $reflectionFunction->getFileName(), $reflectionFunction->getStartLine(),
                    $reflectionFunction->getEndLine());
                throw new ContainerException("InstanceWrapper did not return an object. $error");
            }
            $instanceWrapper->instance = $instance;

        }

        while ($pendingFactory = array_shift($instanceWrapper->injectionMethods)) {
            $this->pendingInjectionMethods[$this->levelDepth][] = [$instanceWrapper->instance, $pendingFactory];
        }

        return $instanceWrapper->instance;
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
                $reflectionClass = $reflectionParameter->getClass();
            } catch (ReflectionException $e) {
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf("Method '%s:%s()' has unknown type hint for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name, $reflectionParameter->name,
                    $reflectionMethod->getFileName(), $reflectionMethod->getEndLine());
                throw new ContainerException($msg, 999, $e);
            }

            if (is_null($reflectionClass)) { // no class for argument
                if ($reflectionParameter->isOptional()) { // has default value
                    continue; // skip adding this parameter.. will break for mixed signatures.
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf("Method '%s:%s()' has no type hint  and no default value for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name, $reflectionParameter->name,
                    $reflectionMethod->getFileName(), $reflectionMethod->getEndLine());
                throw new ContainerException($msg);

            }

            $signature[$reflectionParameter->getName()] = $reflectionClass;
        }
        return $signature;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    private function resolveArguments(\ReflectionFunctionAbstract $reflectionMethod)
    {

        $methodArguments = $this->buildArgumentsInstanceWrappersForFunction($reflectionMethod);

        $args = [];
        /** @var ReflectionClass $reflectionClass */
        foreach ($methodArguments as $reflectionClass) {
            $className = $reflectionClass->getName();
            if (!isset($this->instanceWrappers[$className])) {
                throw new ContainerException("No factory wrappers for '$className'");
            }
            $factoryContainer = $this->instanceWrappers[$className];
            $args[]           = $this->getInstanceFromWrapper($factoryContainer);
        }

        return $args;

    }

    private function resolveReflectionClass(ReflectionClass $reflectionClass)
    {
        $resolvingKey = $reflectionClass->getName() . "$this->levelDepth";
        if (isset($this->resolvingClasses[$resolvingKey])) {
            $classes = join(" -> ", array_keys($this->resolvingClasses)) . " -> " . $resolvingKey;
            throw new ContainerException("Circular dependency detected. $classes");
        }
        $this->resolvingClasses[$resolvingKey] = 1;

        /** @var InstanceWrapper $instanceWrapper */
        $instanceWrapper = $this->instanceWrappers[$reflectionClass->getName()];
        $object          = $this->getInstanceFromWrapper($instanceWrapper);;

        unset($this->resolvingClasses[$resolvingKey]);

        return $object;
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param InstanceWrapper $instanceWrapper
     * @param string          $registerParents one the self::REGISTER_PARENTS constants
     */
    private function registerParentClasses(ReflectionClass $reflectionClass, InstanceWrapper $instanceWrapper, $registerParents)
    {
        $parentReflectionClass = $reflectionClass->getParentClass();
        if ($parentReflectionClass) {
            if ($this->shouldRegisterParent($parentReflectionClass, $registerParents)) {
                $this->registerInstanceWrapperForClass($instanceWrapper, $parentReflectionClass);
                $this->registerParentClasses($parentReflectionClass, $instanceWrapper, $registerParents);
            }

        }
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param InstanceWrapper $instanceWrapper
     * @param string          $registerParents one the self::REGISTER_PARENTS constants
     */
    private function registerInterfaces(ReflectionClass $reflectionClass, InstanceWrapper $instanceWrapper, $registerParents)
    {
        $interfaces = $reflectionClass->getInterfaces();
        /** @var ReflectionClass $interfacesReflectionClass */
        foreach ($interfaces as $interfacesReflectionClass) {
            if ($this->shouldRegisterParent($interfacesReflectionClass, $registerParents)) {
                $this->registerInstanceWrapperForClass($instanceWrapper, $interfacesReflectionClass);
            }
        }
    }


    private function registerInstanceWrapperForClass(InstanceWrapper $instanceWrapper, ReflectionClass $reflectionClass)
    {
        $className                          = $reflectionClass->getName();
        $this->instanceWrappers[$className] = $instanceWrapper;
    }

    /**
     * Will return any methods, whose name starts with 'inject'
     *
     * @param ReflectionClass $reflectionClass
     *
     * @return array of <ReflectionMethod>
     */
    private function getInjectMethods(ReflectionClass $reflectionClass)
    {
        // find methods matching /^inject/
        $injectMethods = (array_filter($reflectionClass->getMethods(), function (\ReflectionMethod $reflectionMethod) {
            return (preg_match("/^inject/", $reflectionMethod->getName()));
        }));

        return $injectMethods;
    }


    private function increaseDepth()
    {
        $this->levelDepth++;
        $this->pendingInjectionMethods[$this->levelDepth] = [];

    }
    /**
     * Will invoke the injection methods
     */
    private function invokePendingInjectFactories()
    {

        /** @var $injectMethod \ReflectionMethod */
        /** @var mixed $instance */
        while ($instanceAndMethod = array_shift($this->pendingInjectionMethods[$this->levelDepth])) {
            list($instance, $injectMethod) = $instanceAndMethod;

            $args = $this->resolveArguments($injectMethod);
            $injectMethod->invokeArgs($instance, $args);
        }

        $this->levelDepth--;
    }

    /**
     * @param ReflectionClass $parentReflectClass
     * @param string          $registerParents one the self::REGISTER_PARENTS constants
     * @return bool
     */
    private function shouldRegisterParent(\ReflectionClass $parentReflectClass, $registerParents)
    {
        $shouldRegister = false;
        switch ($registerParents) {

            case self::PARENTS_REGISTER_ALWAYS:
                $shouldRegister = true;
                break;

            case self::PARENTS_REGISTER_NEVER:
                $shouldRegister = false;
                break;

            case self::PARENTS_REGISTER_IF_NOT_REGISTERED:
                $shouldRegister = !isset($this->instanceWrappers[$parentReflectClass->getName()]);
                break;

            default:
                throw new \RuntimeException("Unknow value of '$registerParents' for \$registerParents");
                break;

        }

        return $shouldRegister;

    }

}

class InstanceWrapper
{
    /**
     * @var ReflectionClass
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
     * @var [<Callable>]
     */
    public $injectionMethods = [];

    /**
     * @var \ReflectionFunction|\ReflectionMethod
     */
    public $factoryMethod;
}
