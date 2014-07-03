<?php
namespace Spine\Web;

use PHPUnit_Framework_TestCase;

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
        $this->routes  = new Routes($this->request);

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

        $this->request->fakePath = "/Fake/Routes/Are/Fun";
        $controllerName          = $this->routes->resolve();

        $this->assertEquals(__NAMESPACE__ . "\\FakeController", $controllerName);

        $this->request->fakePath = "/json/accounts/account123456/rules";
        $controllerName          = $this->routes->resolve();

        $this->assertEquals(__NAMESPACE__ . "\\AccountRuleRestController", $controllerName);

    }

}
