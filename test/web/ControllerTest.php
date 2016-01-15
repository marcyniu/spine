<?php
/**
 *
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  2016-01-14
 */

namespace Spine\Web;

use Spine\Container;

class ControllerTest_SampleService
{

}

class ControllerTest_Controller extends Controller
{

    public function get(ControllerTest_SampleService $sampleService)
    {

    }
}

class ControllerTest extends ControllerTestCase
{

    public function testDispatch() {
        $controller = new ControllerTest_Controller($this->request, $this->response);
        $controller->injectContainer(new Container());
        $controller->dispatch();


    }

}
