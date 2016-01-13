<?php
namespace Spine;

use PHPUnit_Framework_TestCase;
use stdClass;

class ContainerTest_FakeClass_Extends_StdClass extends stdClass
{
}

class ContainerTest_FakeClass_NeedsAClass extends stdClass
{
    public function __construct(ContainerTest_FakeClass_Extends_StdClass $needs)
    {

    }
}

class ContainerTest_FakeClass_Cannot_Instantiate extends stdClass
{
    private function __construct()
    {
    }
}

class TestClassA {
    public function __construct(TestClassB $testClassB)
    {
    }

}

class TestClassB {
    public function __construct(TestClassA $testClassA)
    {
    }
}

class TestClassC {
    public function __construct(TestClassD $testClassD)
    {
    }
}


class TestClassD {
    public function inject(TestClassC $testClassC) {

    }

}


class ContainerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Container
     */
    private $container;

    protected function setUp()
    {
        $this->container = new Container;

    }

    public function testRegister()
    {
        $newObject = new stdClass;
        $this->container->register($newObject);

        $returned = $this->container->resolve("stdClass");
        $this->assertEquals($newObject, $returned);
    }

    public function testRegisterParentClass()
    {
        $newObject = new ContainerTest_FakeClass_Extends_StdClass;
        $this->container->register($newObject);

        $returned = $this->container->resolve(__NAMESPACE__ . "\\ContainerTest_FakeClass_Extends_StdClass");
        $this->assertEquals($newObject, $returned);

        $returned = $this->container->resolve("stdClass");
        $this->assertEquals($newObject, $returned);

    }

    public function testRegisterTypeFactory()
    {

        $this->container->registerTypeFactory(
            "stdClass",
            function (ContainerTest_FakeClass_Extends_StdClass $requirement) {
                return new ContainerTest_FakeClass_NeedsAClass($requirement);
            }
        );

        $returned = $this->container->resolve("stdClass");
        $this->assertInstanceOf("stdClass", $returned);

    }

    public function testResolveThrowsException()
    {
        $this->setExpectedException("Spine\\ContainerException");
        $this->container->resolve("Non existent class");
    }

    /**
     * @covers Spineer::make
     */
    public function testMakeThrowsExceptionForSingleton()
    {
        $this->setExpectedException("Spine\\ContainerException");
        $this->container->make("Spine\\ContainerTest_FakeClass_Cannot_Instantiate");
    }

    /**
     */
    public function testMake()
    {

        $returned = $this->container->make("Spine\\ContainerTest_FakeClass_NeedsAClass");

        $this->assertInstanceOf("Spine\\ContainerTest_FakeClass_NeedsAClass", $returned);

    }

    /**
     */
    public function testSignatureOptionalParameter()
    {
        $method = new \ReflectionMethod($this->container, "getSignature");
        $method->setAccessible(true);

        $function = function ($string = "") {
        };

        $args = $method->invoke($this->container, new \ReflectionFunction($function));

        $this->assertCount(0, $args);

    }

    /**
     */
    public function testSignatureScalarParameter()
    {
        $this->setExpectedException(
            "Spine\\ContainerException",
            "Method 'Spine\\ContainerTest:Spine\\{closure}()' has no type hint  and no default value for 'string' parameter, found in"
        );

        $method = new \ReflectionMethod($this->container, "getSignature");
        $method->setAccessible(true);

        $function = function ($string) {
        };

        $method->invoke($this->container, new \ReflectionFunction($function));

    }

    /**
     */
    public function testSignatureUnknownClassParameter()
    {
        $this->setExpectedException(
            "Spine\\ContainerException"
        );

        $method = new \ReflectionMethod($this->container, "getSignature");
        $method->setAccessible(true);

        $function = function (NotARealClassName $class) {
        };

        $method->invoke($this->container, new \ReflectionFunction($function));

    }

    public function testChickenAndEggDetection() {
        $this->setExpectedException(
            "Spine\\ContainerException"
        );
        $this->container->resolve("Spine\\TestClassA");
    }

    public function testChickenAndEggFactoryDetection() {

        $factory = function(TestClassB $classB) { };

        $this->setExpectedException(
            "Spine\\ContainerException"
        );
        $this->container->registerTypeFactory("Spine\\TestClassB", $factory);
        $this->container->resolve("Spine\\TestClassB");
    }


    public function testChickenAndEggFixWithInject() {

        $this->container->resolve("Spine\\TestClassC");
    }

}
