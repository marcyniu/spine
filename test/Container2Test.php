<?php
namespace Spine;

use PHPUnit_Framework_TestCase;

class Container2Test_Test1
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

class Container2Test extends PHPUnit_Framework_TestCase
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
    }
}
