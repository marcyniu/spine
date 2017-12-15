<?php

namespace Spine\Web;

use Spine\UUID;

/**
 * Class CrossSiteRequestSecurity
 *
 * @package Spine\Web
 */
class CrossSiteRequestSecurity
{
    const COOKIE_NAME = "XSRF-TOKEN";
    const HEADER_NAME = "X-XSRF-TOKEN";
    public $token = null;

    public function createNewToken() :string
    {
        return UUID::v4();
    }
}