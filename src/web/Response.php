<?php
namespace Spine\Web;

/**
 *
 */
class Response
{
    private $p3pSent = false;

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
        exit;
    }

    /**
     * Redirect request.
     *
     * @param string $url A URL to redirect to.
     *
     * @return void
     */
    public function redirectPermanently($url)
    {
        $this->sendHeader('Location: ' . $url, true, 301);
        exit;
    }

    /**
     * Wraps php function header().
     *
     * @param string  $string
     * @param string  $replace
     * @param integer $httpResponseCode
     *
     * @return void
     */
    public function sendHeader($string, $replace = null, $httpResponseCode = null)
    {
        if (null !== $httpResponseCode) {
            header($string, $replace, $httpResponseCode);
        } else {
            header($string, $replace);
        }
    }

    /**
     * @param $string
     */
    public function sendBody($string)
    {
        echo $string;
    }

    /**
     * @param     $mixed
     * @param int $options [optional] <p>
     *                     Bitmask consisting of <b>JSON_HEX_QUOT</b>,
     *                     <b>JSON_HEX_TAG</b>,
     *                     <b>JSON_HEX_AMP</b>,
     *                     <b>JSON_HEX_APOS</b>,
     *                     <b>JSON_NUMERIC_CHECK</b>,
     *                     <b>JSON_PRETTY_PRINT</b>,
     *                     <b>JSON_UNESCAPED_SLASHES</b>,
     *                     <b>JSON_FORCE_OBJECT</b>,
     *                     <b>JSON_UNESCAPED_UNICODE</b>. The behaviour of these
     *                     constants is described on
     *                     the JSON constants page.
     *                     </p>
     */
    public function sendJson($mixed, $options = 0)
    {
        $this->sendHeaderContentTypeJson();
        $this->sendBody(json_encode($mixed), $options);
    }

    /**
     * Empty function. Available to override in unit tests or output buffering.
     */
    public function startBody()
    {

    }

    /**
     * Empty function. Available to override in unit tests or output buffering.
     */
    public function endBody()
    {

    }

    /**
     *
     * @return void
     */
    public function sendCreated()
    {
        $this->sendHeader('HTTP/1.1 201 Created', true, 201);
    }

    /**
     *
     * @return void
     */
    public function sendNotFound()
    {
        $this->sendHeader('HTTP/1.1 404 Not Found', true, 404);
    }

    /**
     *
     * @return void
     */
    public function sendNoContentHeader()
    {
        $this->sendHeader('HTTP/1.1 204 No Content', true, 204);
    }

    /**
     *
     * @return void
     */
    public function sendConflictHeader()
    {
        $this->sendHeader('HTTP/1.1 409 Conflict', true, 409);
    }

    /**
     * @return void
     */
    public function sendBadRequestHeader()
    {
        $this->sendHeader('HTTP/1.1 400 Bad Request', true, 400);
    }

    /**
     * @return void
     */
    public function sendForbiddenHeader()
    {
        $this->sendHeader('HTTP/1.1 403 Forbidden', true, 403);
    }

    public function sendMethodNotAllowed()
    {
        $this->sendHeader('HTTP/1.1 405 Method Not Allowed', true, 405);
    }

    public function sendHeaderContentTypeJson()
    {
        $this->sendHeader("Content-Type: application/json", true);
    }

    /**
     * @param string $name
     * @param string $value
     * @param int    $expires
     * @param string $path
     */
    public function setCookie($name, $value, $expires = null, $path = '/')
    {
        $this->sendP3pHeader();
        setcookie($name, $value, $expires, $path);
    }

    private function sendP3pHeader()
    {
        if (!$this->p3pSent) {
            header('p3p: CP="IDC DSP COR NID DEVi OUR BUS INT"'); // p3p headers are needed for IE
            $this->p3pSent = true;
        }
    }
}