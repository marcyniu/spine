<?php
namespace Spine;

use \PHPUnit\Framework\TestCase;

class Container2Test_Test1
{

}

abstract class Container2Test_TestBase
{
}

class Container2Test_NeedsBase
{
    public function __construct(Container2Test_TestBase $testBase)
    {
    }
}

interface Container2Test_Interface
{
}

class Container2Test_TestExtends extends Container2Test_TestBase implements Container2Test_Interface
{
    public function __construct(Container2Test_Test1 $test1)
    {
    }
}

class Container2Test_TestExtendsMore extends Container2Test_TestExtends
{

}


class Container2Test_Test2
{
    /**
     * @var Container2Test_Test1
     */
    public $test1;

    public function __construct(Container2Test_Test1 $test1)
    {
        $this->test1 = $test1;
    }
}

class Container2Test_Test3
{
    public $stringValue = '';
}

class Container2Test_CallMethod
{
    public $test2;

    public function testMethod(Container2Test_Test2 $test2)
    {
        $this->test2 = $test2;
    }
}

class Container2Test_ScalarInConstructor
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
}

class Container2Test extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Container
     */
    private $container;

    protected function setUp()
    {
        $this->container = new Container;

    }

    public function testResolve()
    {
        $object = $this->container->resolve('Spine\\Container2Test_Test1');
        $this->assertInstanceOf('Spine\\Container2Test_Test1', $object);
    }

    public function testResolveConstructorDependencies()
    {
        $object = $this->container->resolve('Spine\\Container2Test_Test2');
        $this->assertInstanceOf('Spine\\Container2Test_Test2', $object);
        $this->assertInstanceOf('Spine\\Container2Test_Test1', $object->test1);
    }

    public function testFactory()
    {
        $factory = function () {
            $obj              = new Container2Test_Test3();
            $obj->stringValue = 'From Factory';
            return $obj;
        };

        $this->container->registerTypeFactory("Spine\\Container2Test_Test3", $factory);

        $object = $this->container->resolve('Spine\\Container2Test_Test3');
        $this->assertInstanceOf('Spine\\Container2Test_Test3', $object);

        $this->assertEquals('From Factory', $object->stringValue);

    }

    public function testBadFactory()
    {
        $this->expectException("Spine\\ContainerException");

        $factory = function () {
            // doesn' return anything
        };

        $this->container->registerTypeFactory("Spine\\Container2Test_Test3", $factory);

        $object = $this->container->resolve('Spine\\Container2Test_Test3');

    }


    public function testCallFunction()
    {
        $function = function (Container2Test_Test2 $test2) {
            return $test2;
        };

        $result = $this->container->callFunction($function);
        $this->assertInstanceOf('Spine\\Container2Test_Test2', $result);

    }

    public function testCallMethod()
    {
        $obj = new Container2Test_CallMethod();

        $this->container->callMethod($obj, 'testMethod');
        $this->assertInstanceOf('Spine\\Container2Test_Test2', $obj->test2);
    }

    public function testRegisterWithParents()
    {
        $newObject = new Container2Test_TestExtends(new Container2Test_Test1);
        $this->container->register($newObject);

        $returned = $this->container->resolve("Spine\\Container2Test_TestExtends");
        $this->assertEquals($newObject, $returned);


        $interfaceProvider = $this->container->resolve("Spine\\Container2Test_Interface");
        $this->assertEquals($interfaceProvider, $returned);

        $baseInstance = $this->container->resolve("Spine\\Container2Test_TestBase");
        $this->assertSame($returned, $baseInstance);

    }


    public function testRegisterWithOutParents()
    {
        $newObject = new Container2Test_TestExtends(new Container2Test_Test1);
        $this->container->register($newObject);

        $returned = $this->container->resolve("Spine\\Container2Test_TestExtends");
        $this->assertEquals($newObject, $returned);


        $baseInstance = $this->container->resolve("Spine\\Container2Test_TestBase");
        $this->assertSame($returned, $baseInstance);


        $newerObject = new Container2Test_TestExtendsMore(new Container2Test_Test1);
        $this->container->register($newerObject, false);

        $newerResolved = $this->container->resolve("Spine\\Container2Test_TestExtendsMore");
        $this->assertNotSame($baseInstance, $newerResolved);


    }

    public function testRegisterTypeNoParents()
    {

        // register what the base will be
        $newObject = new Container2Test_TestExtends(new Container2Test_Test1);
        $this->container->registerType("Spine\\Container2Test_TestBase", $newObject);

        // later we ask for new version the extended class
        $newExtendedVersion = $this->container->resolve("Spine\\Container2Test_TestExtends");
        $this->assertNotSame($newObject, $newExtendedVersion); // should not be the same as the first


        // later need the base
        $anotherBaseInstance = $this->container->resolve("Spine\\Container2Test_TestBase");
        $this->assertSame($newObject, $anotherBaseInstance); // should be same as first (not second)
    }


    public function testRegisterTypeWithParents()
    {
        $newObject = new Container2Test_TestExtendsMore(new Container2Test_Test1);

        $this->container->registerType("Spine\\Container2Test_TestExtends", $newObject, true);

        $interfaceProvider = $this->container->resolve("Spine\\Container2Test_Interface");
        $this->assertSame($newObject, $interfaceProvider);

    }


    public function testFactoryWithParents()
    {
        $factory = function () {
            $obj = new Container2Test_TestExtends(new Container2Test_Test1);
            return $obj;
        };

        $this->container->registerTypeFactory("Spine\\Container2Test_TestExtends", $factory, true);

        $object = $this->container->resolve('Spine\\Container2Test_TestExtends');
        $this->assertInstanceOf('Spine\\Container2Test_TestExtends', $object);

        $baseObject = $this->container->resolve('Spine\\Container2Test_TestBase');
        $this->assertSame($object, $baseObject);
    }

    public function testConstructorWithScalar()
    {
        $this->expectException("Spine\\ContainerException");
        $this->container->resolve("Spine\\Container2Test_ScalarInConstructor");

    }

    public function testConstructorWithScalarForFactory()
    {

        $factory1 = function (Container2Test_ScalarInConstructor $object) {
            return new Container2Test_Test1();
        };

        $factory2 = function () {
            return new Container2Test_ScalarInConstructor('foo');
        };


        $this->container->registerTypeFactory("Spine\\Container2Test_Test1", $factory1);
        $this->container->registerTypeFactory("Spine\\Container2Test_ScalarInConstructor", $factory2);


        $object = $this->container->resolve('Spine\\Container2Test_Test1');
        $this->assertInstanceOf('Spine\\Container2Test_Test1', $object);
    }


    public function testChildFactoryOnlyCalled()
    {
        $factory = function () {
            $obj = new Container2Test_TestExtends(new Container2Test_Test1);
            return $obj;
        };

        $this->container->registerTypeFactory("Spine\\Container2Test_TestExtends", $factory, true);

        $object = $this->container->resolve("Spine\\Container2Test_NeedsBase");
        $this->assertInstanceOf('Spine\\Container2Test_NeedsBase', $object);


    }
}
