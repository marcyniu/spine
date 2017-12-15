<?php

namespace Spine\IOC_Example;

require_once '../../vendor/autoload.php';

Class ClassA
{


}

Class ClassB
{
    /**
     * @var ClassA
     */
    private $classA;   


    public function __construct(ClassA $classA)
    {
        $this->classA = $classA;
    }

}

$container = new \Spine\Container();

$classB = $container->resolve(__NAMESPACE__ . '\\ClassB');

var_dump($classB);
