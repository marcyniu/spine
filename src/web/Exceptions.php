<?php

namespace Spine\Web;

use Exception;

/**
 * HttpExceptions
 *
 * @author Lance Rushing
 * @since  10/03/2011
 */
class HttpException extends Exception
{

}

// @codingStandardsIgnoreStart

/**
 * Class HttpBadRequestException
 *
 * @package Spine\Web
 */
class HttpBadRequestException extends HttpException
{

    protected $message = "Bad Request";
    protected $code = 400;

}

/**
 * Class HttpNotFoundException
 *
 * @package Spine\Web
 */
class HttpNotFoundException extends HttpException
{

    protected $message = "Not Found";
    protected $code = 404;

}

/**
 * Class HttpMethodNotAllowedException
 *
 * @package Spine\Web
 */
class HttpMethodNotAllowedException extends HttpException
{

    protected $message = "Method Not Allowed";
    protected $code = 405;

}


// @codingStandardsIgnoreEnd