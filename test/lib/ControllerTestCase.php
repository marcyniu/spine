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
     * @var FakeRequest
     */
    public $response;

    protected function setUp()
    {
        $this->request  = new FakeRequest();
        $this->response = new FakeResponse();
    }
}

class FakeRequest extends Request
{
    use FakeRequestTrait;
}

trait FakeRequestTrait
{
    public $fakePath = "/";

    public function path()
    {
        return $this->fakePath;
    }

    /**
     * @param $key
     * @param $value
     *
     * @return FakeRequest $this
     */
    public function setParam($key, $value)
    {
        $this->params[$key] = $value;
        return $this;
    }
}

class FakeResponse extends Response
{
    public $fakeHeaders = array();

    public $fakeBody = "";

    public function startBody()
    {
        ob_start();
    }

    public function sendBody($string)
    {
        $this->fakeBody .= $string;
    }

    public function sendHeader($string, $replace = null, $httpResponseCode = null)
    {
        $this->fakeHeaders[] = array($string, $replace, $httpResponseCode);
    }

    public function endBody()
    {
        $this->fakeBody = ob_get_clean();
    }
}