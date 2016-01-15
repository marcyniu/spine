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
    /**
     * @var array <TypeFactoryWrapper>
     */
    private $typeFactoryWrappers = [];

    /**
     * Used to detect circular __construct() dependencies
     *
     * @var array
     */
    private $resolvingClasses = [];

    /**
     * Array of [$callIndex][<ReflectionMethod>, <instance>]
     *
     * Keeps track of any inject*() methods that need to be called after object construction
     *
     * @var array
     */
    private $pendingInjectionMethods = [];

    /**
     *
     */
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
        $reflectionFunction = new \ReflectionFunction($callable);
        $args = $this->resolveArguments($reflectionFunction);
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

        $args = $this->resolveArguments($reflectionMethod);

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

        $this->_registerType($object, $reflectionObject, $registerParentClasses);

        return $this;
    }

    /**
     * @param string $className class/interface Name
     * @param object $object
     * @param bool   $registerParentClasses
     *
     * @return $this
     */
    public function registerType($className, $object, $registerParentClasses = false)
    {

        $typeFactoryWrapper = new TypeFactoryWrapper();
        $typeFactoryWrapper->instance = $object;
        $typeFactoryWrapper->reflectionClass = new ReflectionClass($className);;

        $this->_registerTypeFactoryWrapper($typeFactoryWrapper);


        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($typeFactoryWrapper->reflectionClass, $typeFactoryWrapper);
            $this->registerInterfaces($typeFactoryWrapper->reflectionClass, $typeFactoryWrapper);
        }
        return $this;

    }

    /**
     * @param ReflectionClass $reflectionClass class/interface the object extends/implements
     * @param object          $object
     * @param bool            $registerParentClasses
     *
     * @return $this
     */
    private function _registerType($object, ReflectionClass $reflectionClass, $registerParentClasses)
    {
        $typeFactoryWrapper = new TypeFactoryWrapper();
        $typeFactoryWrapper->instance = $object;
        $typeFactoryWrapper->reflectionClass = $reflectionClass;

        $this->_registerTypeFactoryWrapper($typeFactoryWrapper);


        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($reflectionClass, $typeFactoryWrapper);
            $this->registerInterfaces($reflectionClass, $typeFactoryWrapper);
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

        $typeFactoryWrapper                         = new TypeFactoryWrapper();
        $typeFactoryWrapper->reflectionClass        = new ReflectionClass($type);
        $typeFactoryWrapper->factoryMethod          = new ReflectionFunction($callableFactory);
        $typeFactoryWrapper->factoryMethodArguments = $this->buildTypeFactoriesForFunctionArguments($typeFactoryWrapper->factoryMethod);;

        $this->_registerTypeFactoryWrapper($typeFactoryWrapper);

        if ($registerParents) {
            $this->registerParents($typeFactoryWrapper->reflectionClass , $typeFactoryWrapper);
            $this->registerInterfaces($typeFactoryWrapper->reflectionClass , $typeFactoryWrapper);
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
        $reflectionClass = new ReflectionClass($className);

        $this->buildTypeFactoryWrappers($reflectionClass);

        $object = $this->resolveReflectionClass($reflectionClass);
        $this->invokePendingInjectFactories();
        return $object;
    }

    private function buildTypeFactoryWrappers(ReflectionClass $reflectionClass)
    {
        if (!isset($this->typeFactoryWrappers[$reflectionClass->getName()])) {

            $typeFactoryWrapper                  = new TypeFactoryWrapper();
            $typeFactoryWrapper->reflectionClass = $reflectionClass;

            // Register it now.
            $this->_registerTypeFactoryWrapper($typeFactoryWrapper);

            $constructorReflectMethod = $reflectionClass->getConstructor();

            if ($constructorReflectMethod === null) { // no constructor.
                $typeFactoryWrapper->instance = $reflectionClass->newInstance();
            } else {
                $typeFactoryWrapper->factoryMethod = $constructorReflectMethod;

                $typeFactoryWrapper->factoryMethodArguments = $this->buildTypeFactoriesForFunctionArguments($constructorReflectMethod);
            }

            $injectionMethods = $this->getInjectMethods($reflectionClass);
            /** @var ReflectionMethod $injectionReflectionMethod */
            foreach($injectionMethods as $injectionReflectionMethod) {
                $typeFactoryWrapper->injectionMethods[] = $injectionReflectionMethod;
            }

        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     *
     * @return array
     */
    private function buildTypeFactoriesForFunctionArguments(ReflectionFunctionAbstract $reflectionFunction)
    {
        $arguments = [];
        $signature = $this->getSignature($reflectionFunction);
        /** @var ReflectionClass $signatureReflectionClass */
        foreach ($signature as $argName => $signatureReflectionClass) {
            $arguments[$argName] = $signatureReflectionClass;
            $this->buildTypeFactoryWrappers($signatureReflectionClass);
        }

        return $arguments;
    }

    /**
     * @param TypeFactoryWrapper $factoryWrapper
     *
     * @return object
     * @throws ContainerException
     */
    private function getFactoryWrapperInstance(TypeFactoryWrapper $factoryWrapper)
    {

        if (!is_object($factoryWrapper->instance)) {

            $reflectionFunction = $factoryWrapper->factoryMethod;

            $args = [];
            foreach ($factoryWrapper->factoryMethodArguments as $reflectionClass) {
                $args[] = $this->resolveReflectionClass($reflectionClass);
            }

            /** @var ReflectionFunctionAbstract $reflectionFunction */
            if ($reflectionFunction->isClosure()) {
                /** @var ReflectionFunction $reflectionFunction */
                $instance = $reflectionFunction->invokeArgs($args);
            } elseif ($reflectionFunction->isConstructor()) {
                if (!$reflectionFunction->getDeclaringClass()->isInstantiable()) {
                    throw new ContainerException("Cannot call Private Constructor");
                }
                $instance = $factoryWrapper->reflectionClass->newInstanceArgs($args);
            } else {
                $instance = $reflectionFunction->invokeArgs($args);
            }

            if (!is_object($instance)) {
                $error = sprintf("%s (%u:%u)", $reflectionFunction->getFileName(),
                    $reflectionFunction->getStartLine(), $reflectionFunction->getEndLine());
                throw new ContainerException("TypeFactoryWrapper did not return an object. $error");
            }
            $factoryWrapper->instance = $instance;

        }

        while($pendingFactory = array_shift($factoryWrapper->injectionMethods)) {

            $this->pendingInjectionMethods[] = [$factoryWrapper->instance, $pendingFactory];
        }

        return $factoryWrapper->instance;
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

            if (is_null($reflectionClass)) { // no class for argument
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

        $methodArguments = $this->buildTypeFactoriesForFunctionArguments($reflectionMethod);

        $args = [];
        /** @var ReflectionClass $reflectionClass */
        foreach ($methodArguments as $reflectionClass) {
            $className = $reflectionClass->getName();
            if (!isset( $this->typeFactoryWrappers[$className])) {
                throw new ContainerException("No factory wrappers for '$className'");
            }
            $factoryContainer = $this->typeFactoryWrappers[$className];
            $args[] = $this->getFactoryWrapperInstance($factoryContainer);
        }

        return $args;


//            if (isset($this->resolvingClasses[$type])) {
//
//                if ($reflectionMethod instanceof \ReflectionFunction) {
//                    /** @var \ReflectionFunction $reflectionMethod */
//                    $msg = sprintf('Circular Reference Detected. Function defined in "%s:%s" requires type "%s".',
//                        $reflectionMethod->getFileName(), $reflectionMethod->getEndLine(), $type);
//                } else {
//                    /** @var \ReflectionMethod $reflectionMethod */
//                    $msg = sprintf('Circular Reference Detected. Method "%s::%s" defined in "%s:%s requires type "%s".',
//                        $reflectionMethod->class, $reflectionMethod->getName(), $reflectionMethod->getFileName(),
//                        $reflectionMethod->getEndLine(), $type);
//                }
//
//                throw new ContainerException($msg);
//            }


    }

    private function resolveReflectionClass(ReflectionClass $reflectionClass)
    {
        if (isset($this->resolvingClasses[$reflectionClass->getName()])) {
            $classes = join(" -> ", array_keys($this->resolvingClasses)) . " -> " . $reflectionClass->getName();
            throw new ContainerException("Circular dependency detected. $classes");
        }
        $this->resolvingClasses[$reflectionClass->getName()] = 1;

        /** @var TypeFactoryWrapper $factoryWrapper */
        $factoryWrapper = $this->typeFactoryWrappers[$reflectionClass->getName()];
        $object         = $this->getFactoryWrapperInstance($factoryWrapper);;

        unset($this->resolvingClasses[$reflectionClass->getName()]);

        return $object;
    }

    /**
     * @param ReflectionClass    $reflectionClass
     * @param TypeFactoryWrapper $typeFactoryWrapper
     */
    private function registerParents(ReflectionClass $reflectionClass, TypeFactoryWrapper $typeFactoryWrapper)
    {
        $parentReflectionClass = $reflectionClass->getParentClass();
        if ($parentReflectionClass) {
            $this->_registerTypeFactoryWrapper($typeFactoryWrapper, $parentReflectionClass);
            $this->registerParents($parentReflectionClass, $typeFactoryWrapper);
        }
    }

    /**
     * @param ReflectionClass    $reflectionClass
     * @param TypeFactoryWrapper $typeFactoryWrapper
     */
    private function registerInterfaces(ReflectionClass $reflectionClass, TypeFactoryWrapper $typeFactoryWrapper)
    {
        $interfaces = $reflectionClass->getInterfaces();
        /** @var ReflectionClass $interfacesReflectionClass */
        foreach ($interfaces as $interfacesReflectionClass) {
            $this->_registerTypeFactoryWrapper($typeFactoryWrapper, $interfacesReflectionClass);
        }
    }



    private function _registerTypeFactoryWrapper(TypeFactoryWrapper $typeFactoryWrapper, ReflectionClass $reflectionClass = null)
    {
        if (empty($reflectionClass)) {
            $reflectionClass = $typeFactoryWrapper->reflectionClass;
        }

        $className = $reflectionClass->getName();
        $this->typeFactoryWrappers[$className] = $typeFactoryWrapper;
    }

    /**
     * Will register any methods, who's name starts with 'inject'
     *
     * @param ReflectionClass $reflectionClass
     *
     * @return array
     */
    private function getInjectMethods(ReflectionClass $reflectionClass)
    {

        // find methods matching /^inject/
        $injectMethods = (array_filter(
            $reflectionClass->getMethods(),
            function (\ReflectionMethod $reflectionMethod) {
                return (preg_match("/^inject/", $reflectionMethod->getName()));
            }
        ));

        return $injectMethods;
    }



    /**
     * Will invoke the injection methods
     */
    private function invokePendingInjectFactories()
    {
        /** @var $injectMethod \ReflectionMethod */
        /** @var mixed $instance */
        while ($instanceAndMethod = array_shift($this->pendingInjectionMethods)) {
            list($instance, $injectMethod) = $instanceAndMethod;

            $args = $this->resolveArguments($injectMethod);
            $injectMethod->invokeArgs($instance, $args);
        }
    }

}

class TypeFactoryWrapper
{
    /**
     * @var ReflectionClass
     */
    public $reflectionClass;
    public $instance;
    public $factoryMethodArguments = [];
    public $injectionMethods = [];

    /**
     * @var \ReflectionFunction|\ReflectionMethod
     */
    public $factoryMethod;
}
