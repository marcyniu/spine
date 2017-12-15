<?php

namespace Spine\Web;

use \PHPUnit\Framework\TestCase;

class ControllerTestCase extends \PHPUnit\Framework\TestCase
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
