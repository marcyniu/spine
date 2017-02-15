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
    const HEADER_NAME = "X-XSRF-TOKEN";

    /**
     * @var Cookies
     */
    private $cookies;

    /**
     * XsrfPrevention constructor.
     * @param Cookies $cookies
     */
    public function injectCookies(Cookies $cookies)
    {
        $this->cookies = $cookies;
    }

    /**
     * @return bool
     */
    public function execute()
    {
        $tokenValue = $this->loadTokenFromCookie();

        if ($this->request->type() === 'POST') {
            return $this->matchingReferrer() && $this->hasMatchingParamToken($tokenValue);
        }
        if ($this->request->type() === 'GET') {
            $this->createCookieIfNeeded($tokenValue);
            return true;
        }

        return true;
    }

    protected function loadTokenFromCookie()
    {
        return $this->cookies->get(self::COOKIE_NAME);

    }

    private function matchingReferrer()
    {
        $referrer = $this->request->referrer();
        $pattern  = '|^https?://' . $this->request->host() . '|';

        if (preg_match($pattern, $referrer)) {
            return true;
        } else {
            throw new HttpBadRequestException("Invalid referrer '$referrer'");
        }

    }

    protected function hasMatchingParamToken($tokenValue)
    {
        $paramName = $this->request->param(self::PARAM_NAME);
        if ($tokenValue == $paramName) {
            return true;
        }

        $paramName = $this->request->header(self::HEADER_NAME);
        if ($tokenValue == $paramName) {
            return true;
        }

        $this->response->sendBody('XSRF fail.');

        return false;

    }

    protected function createCookieIfNeeded($tokenValue)
    {
        if (is_null($tokenValue)) {
            $this->createToken();
            $this->cookies->set(self::COOKIE_NAME, $this->token);
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