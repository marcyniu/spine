<?php
namespace Spine;

use PHPUnit_Framework_TestCase;


class ContainerCircularTest_ExampleController
{
    public function inject(ContainerCircularTest_ExampleService $exampleService)
    {

    }
}

class ContainerCircularTest_ExampleService
{
    public function __construct(ContainerCircularTest_ExampleService2 $exampleService2)
    {
    }

    public function inject(ContainerCircularTest_NeedFactory $needFactory)
    {
    }
}

class ContainerCircularTest_ExampleService2
{
    public function inject(ContainerCircularTest_NeedFactory $needFactory)
    {

    }
}

class ContainerCircularTest_NeedFactory
{
    public function __construct(ContainerCircularTest_ExampleService2 $exampleService2)
    {
    }
}

class ContainerCircularTest_NeedFactoryExtends extends  ContainerCircularTest_NeedFactory {}

/**
 * Class ContainerCircularTest
 *
 * This class tests when a dependancy chain calls a factory
 *
 * @package Spine
 */
class ContainerCircularTest extends PHPUnit_Framework_TestCase
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

        $someFunc = function (
            ContainerCircularTest_ExampleController $exampleController
        ) {
            return 'stuff';

        };

        $this->container->registerTypeFactory(
            "Spine\\ContainerCircularTest_NeedFactoryExtends",
            // pdo factory
            function (Container $container) {
                return $container->resolve('Spine\ContainerCircularTest_NeedFactory');
            }
        , true);

        $this->container->callFunction($someFunc);

        $this->assertTrue(true);
    }

}
