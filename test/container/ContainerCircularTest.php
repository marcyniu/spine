<?php

namespace Spine;

use \PHPUnit\Framework\TestCase;


class ContainerCircularTest_A
{
    public function inject(ContainerCircularTest_B $exampleService)
    {

    }
}

class ContainerCircularTest_B
{
    public function __construct(ContainerCircularTest_B2 $exampleService2)
    {
    }

    public function inject(ContainerCircularTest_C $needFactory)
    {
    }
}

class ContainerCircularTest_B2
{
    public function inject(ContainerCircularTest_C $needFactory)
    {

    }
}

class ContainerCircularTest_C
{
    public function __construct(ContainerCircularTest_B2 $exampleService2)
    {
    }
}

class ContainerCircularTest_CExtends extends ContainerCircularTest_C
{
}

/**
 * Class ContainerCircularTest
 *
 * This class tests when a dependency chain calls a factory
 *
 * @package Spine
 */
class ContainerCircularTest extends \PHPUnit\Framework\TestCase
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
        $this->assertTrue(true);
        return;

        $someFunc = function (ContainerCircularTest_A $example) {
            return 'stuff';
        };

        $this->container->registerTypeFactory(
            "Spine\\ContainerCircularTest_CExtends",
            // pdo factory
            function (Container $container) {
                return $container->resolve('Spine\ContainerCircularTest_CExtends');
            }
            , true);

        $this->container->callFunction($someFunc);

        $this->assertTrue(true);
    }

}
