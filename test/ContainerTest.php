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

        $returned = $this->container->resolve("StdClass");
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

        // and case wrongly cased class names....
        $returned = $this->container->resolve("stdclass");
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
        $this->setExpectedException("Uptracs\\Spine\\ContainerException");
        $this->container->resolve("Non existent class");
    }

    /**
     * @covers Spineer::make
     */
    public function testMakeThrowsExceptionForSingleton()
    {
        $this->setExpectedException("Uptracs\\Spine\\ContainerException");
        $this->container->make("Uptracs\\Spine\\ContainerTest_FakeClass_Cannot_Instantiate");
    }

    /**
     */
    public function testMake()
    {

        $returned = $this->container->make("Uptracs\\Spine\\ContainerTest_FakeClass_NeedsAClass");

        $this->assertInstanceOf("Uptracs\\Spine\\ContainerTest_FakeClass_NeedsAClass", $returned);

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
            "Uptracs\\Spine\\ContainerException",
            "Method 'Uptracs\\Spine\\ContainerTest:Uptracs\\Spine\\{closure}()' has no type hint  and no default value for 'string' parameter, found in"
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
            "Uptracs\\Spine\\ContainerException",
            "Method 'Spine\CoSpineptracs\Spine\{closure}()' has unknown type hint for 'class' parameter"
        );

        $method = new \ReflectionMethod($this->container, "getSignature");
        $method->setAccessible(true);

        $function = function (NotARealClassName $class) {
        };

        $method->invoke($this->container, new \ReflectionFunction($function));

    }

}
