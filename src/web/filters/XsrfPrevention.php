<?php
/**
 *
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  9/5/14
 */

namespace Spine\Web;

class XsrfPrevention extends Filter
{

    const COOKIE_NAME = "XSRF-TOKEN";
    const PARAM_NAME = "XSRF-TOKEN";

    /**
     * @var string
     */
    protected $token;

    /**
     * @return bool
     */
    public function execute()
    {
        $this->loadTokenFromCookie();

        if ($this->request->type() === 'POST') {
            return $this->hasMatchingParamToken();
        }
        if ($this->request->type() === 'GET') {
            $this->createCookieIfNeeded();
            return true;
        }

        return true;
    }

    protected function loadTokenFromCookie()
    {
        $cookieValue = $this->request->getCookie(self::COOKIE_NAME);
        $this->token = $cookieValue;
    }

    protected function hasMatchingParamToken()
    {
        $cookieValue = $this->request->getCookie(self::COOKIE_NAME);
        $paramName   = $this->request->requiredParam(self::PARAM_NAME);
        if ($cookieValue == $paramName) {
            return true;
        }

        $this->response->sendBody('XSRF fail.');

        return false;

    }

    protected function createCookieIfNeeded()
    {
        $cookieValue = $this->request->getCookie(self::COOKIE_NAME);

        if (is_null($cookieValue)) {
            $this->createToken();
            $this->response->setCookie(self::COOKIE_NAME, $this->token);
        }

    }

    protected function createToken()
    {
        $this->token = sha1(openssl_random_pseudo_bytes(120));
    }

    public function getToken()
    {
        return $this->token;
    }
}