<?php
namespace Spine\Web;

/**
 * @author Lance Rushing <lance@lancerushing.com>
 */


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