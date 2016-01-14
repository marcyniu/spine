<?php

namespace Spine\Web;

use PHPUnit_Framework_TestCase;

class ControllerTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var FakeRequest
     */
    public $request;

    /**
     * @var FakeResponse
     */
    public $response;

    protected function setUp()
    {
        $this->request  = new FakeRequest();
        $this->response = new FakeResponse();
    }
}
