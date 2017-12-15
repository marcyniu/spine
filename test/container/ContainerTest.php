<?php
namespace Spine;

use \PHPUnit\Framework\TestCase;
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
    public $classD;
    public function __construct(TestClassD $testClassD)
    {
        $this->classD = $testClassD;
    }
}


class TestClassD {
    public $classC;
    public function inject(TestClassC $testClassC) {
        $this->classC = $testClassC;
    }

}

interface TestInterfaceA1 {

}

class TestClassA1 implements TestInterfaceA1 {

}

class TestClassA2 extends TestClassA1 {

}

class TestClassA3 extends TestClassA2 {

}


class ContainerTest extends \PHPUnit\Framework\TestCase
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



        $this->container->registerTypeFactory(
            "stdClass",
            function (TestClassA $classA) {
                return new TestClassB($classA);
            }
        );


        $returned = $this->container->resolve("Spine\\TestClassC");
        $this->assertInstanceOf("Spine\\TestClassC", $returned);


    }



    public function testResolveThrowsException()
    {
        $this->expectException("\\ReflectionException");
        $this->container->resolve("Non existent class");
    }

    public function testResolveThrowsExceptionForSingleton()
    {
        $this->expectException("Spine\\ContainerException");
        $this->container->resolve("Spine\\ContainerTest_FakeClass_Cannot_Instantiate");
    }

    /**
     */
    public function testResolve()
    {

        $returned = $this->container->resolve("Spine\\ContainerTest_FakeClass_NeedsAClass");

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
        $this->expectException(
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
        $this->expectException(
            "Spine\\ContainerException"
        );

        $method = new \ReflectionMethod($this->container, "getSignature");
        $method->setAccessible(true);

        $function = function (NotARealClassName $class) {
        };

        $method->invoke($this->container, new \ReflectionFunction($function));

    }

    public function testChickenAndEggDetection() {
        $this->expectException(
            "Spine\\ContainerException"
        );
        $this->container->resolve("Spine\\TestClassA");
    }

    public function testChickenAndEggFactoryDetection() {

        $factory = function(TestClassB $classB) { };

        $this->expectException("Spine\\ContainerException");
        $this->container->registerTypeFactory("Spine\\TestClassB", $factory);
        $this->container->resolve("Spine\\TestClassB");
    }


    public function testChickenAndEggFixWithInject() {

        $classC = $this->container->resolve("Spine\\TestClassC");

        $this->assertInstanceOf("Spine\\TestClassC", $classC);

        $this->assertInstanceOf("Spine\\TestClassD", $classC->classD);
        $this->assertInstanceOf("Spine\\TestClassC", $classC->classD->classC);

        $this->assertSame($classC, $classC->classD->classC);

    }

    public function testFactoryParentInterfaces() {


        $factory = function() { return  new TestClassA3(); };

        $this->container->registerTypeFactory("\\Spine\\TestClassA3", $factory, true);

        $instance1 = $this->container->resolve("Spine\\TestClassA3");
        $this->assertInstanceOf("Spine\\TestClassA3", $instance1);


        $instance2 = $this->container->resolve("Spine\\TestClassA1");
        $this->assertInstanceOf("Spine\\TestClassA1", $instance2);

        $this->assertSame($instance1, $instance2);

        $instance3 = $this->container->resolve("Spine\\TestInterfaceA1");
        $this->assertInstanceOf("Spine\\TestClassA1", $instance3);
        $this->assertSame($instance1, $instance3);

    }

    public function testRegisterType()
    {
        $mock = $this->getMockBuilder('Spine\\TestClassA1')->disableOriginalConstructor()->disableProxyingToOriginalMethods()->getMock();
        $this->container->registerType('Spine\\TestClassA1', $mock);

        $actual = $this->container->resolve('Spine\\TestClassA1');
        $this->assertSame($mock, $actual);
    }

}
