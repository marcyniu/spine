<?php

namespace Spine\Web;

use Spine\StrictClass;

/**
 *
 */
abstract class BaseController extends StrictClass
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @param Request  $request
     * @param Response $response
     */
    function __construct(Request $request, Response $response)
    {
        $this->request  = $request;
        $this->response = $response;
    }
}

