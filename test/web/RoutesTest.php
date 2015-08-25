<?php
namespace Spine\Web;

use PHPUnit_Framework_TestCase;

class TestRoutes extends Routes {

}

/**
 * Class RouteTest
 *
 * @package Spine\Web
 */
class RoutesTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var FakeRequest
     */
    public $request;
    /**
     * @var Routes
     */
    private $routes;

    protected function setUp()
    {
        $this->request = new FakeRequest();
        $this->routes  = new TestRoutes($this->request);

        $reflectionRoutes = new \ReflectionProperty($this->routes, "routes");
        $reflectionRoutes->setAccessible(true);
        $reflectionRoutes->setValue(
            $this->routes,
            array(
                "/Fake/Routes/Are/Fun"               => "FakeController",
                '/json/accounts/{accountUuid}/rules' => "AccountRuleRestController"
            )
        );

    }

    public function testResolve()
    {

        $this->setExpectedException('Spine\\Web\\HttpNotFoundException');
        $this->request->fakePath = "/Fake/Routes/Are/Fun";
        $controllerName          = $this->routes->resolve();

    }

}
