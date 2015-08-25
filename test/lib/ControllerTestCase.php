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

trait FakeRequestTrait
{
    public $fakePath = "/";

    public $fakeHeaders = [];

    public $fakeBody = [];
    public $fakeHost = 'FakeRequestTrait';
    public $fakeReferrer = 'fakeReferrer';
    public $fakeAccept = '';

    public $fakeType = 'GET';

    public $fakeQueryString = '';

    public function getPort()
    {
        return 80;
    }

    public function host()
    {
        return $this->fakeHost;
    }

    public function port()
    {
        return 80;
    }

    public function path()
    {
        return $this->fakePath;
    }

    public function accept()
    {
        return $this->fakeAccept;
    }

    public function queryString()
    {
        return $this->fakeQueryString;
    }

    public function referrer()
    {
        return $this->fakeReferrer;
    }

    public function sendHeader($string, $replace = null, $httpResponseCode = null)
    {
        $this->fakeHeaders[] = $string;
    }

    public function sendBody($string)
    {
        $this->fakeBody = $string;
    }

    public function header($name)
    {
        return isset($this->fakeHeaders[$name]) ? $this->fakeHeaders[$name] : null;
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

    /**
     * @param array $params
     *
     * @return FakeRequest $this
     *
     */
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    public function type()
    {
        return $this->fakeType;
    }
}

class FakeResponse extends Response
{
    use FakeResponseTrait;
}

trait FakeResponseTrait
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
        $this->fakeHeaders[] = $string;
    }

    public function endBody()
    {
        $this->fakeBody = ob_get_clean();
    }

    /**
     * Redirect request.
     *
     * @param string $url A URL to redirect to.
     *
     * @return void
     */
    public function redirect($url)
    {
        $this->sendHeader('Location: ' . $url, true, 302);
    }
}