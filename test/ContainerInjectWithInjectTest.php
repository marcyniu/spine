<?php
namespace Spine;

use PHPUnit_Framework_TestCase;

class ContainerInjectWithInjectTest_ExampleService1
{
    /**
     * @var ContainerInjectWithInjectTest_ExampleService2
     */
    public $service2;

    public function inject(ContainerInjectWithInjectTest_ExampleService2 $service2)
    {
        $this->service2 = $service2;
    }
}

class ContainerInjectWithInjectTest_ExampleService2
{
    /**
     * @var ContainerInjectWithInjectTest_ExampleService3
     */
    public $service3;

    public function inject(ContainerInjectWithInjectTest_ExampleService3 $service3)
    {
        $this->service3 = $service3;
    }
}

class ContainerInjectWithInjectTest_ExampleService3
{
    /**
     * @var ContainerInjectWithInjectTest_ExampleService1
     */
    public $service1;

    public function inject(ContainerInjectWithInjectTest_ExampleService1 $service1)
    {
        $this->service1 = $service1;
    }
}


/**
 * Class ContainerInjectWithInjectTest
 *
 * This class tests when an inject dependency has an inject
 *
 * @package Spine
 */
class ContainerInjectWithInjectTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Container
     */
    private $container;

    protected function setUp()
    {
        $this->container = new Container;
    }

    public function testFoo()
    {
        /** @var ContainerInjectWithInjectTest_ExampleService1 $service1 */
        $service1 = $this->container->resolve('Spine\ContainerInjectWithInjectTest_ExampleService1');


        $this->assertInstanceOf('Spine\ContainerInjectWithInjectTest_ExampleService1', $service1);
        $this->assertInstanceOf('Spine\ContainerInjectWithInjectTest_ExampleService2', $service1->service2);
        $this->assertInstanceOf('Spine\ContainerInjectWithInjectTest_ExampleService3', $service1->service2->service3);
        $this->assertInstanceOf('Spine\ContainerInjectWithInjectTest_ExampleService1', $service1->service2->service3->service1);
    }

}
