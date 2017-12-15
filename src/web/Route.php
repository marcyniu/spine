<?php

namespace Spine\Web;

/**
 * Route Definition
 */
class Route
{

    public $controllerClassName;
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function isAllowed()
    {

        return true;
    }

    /**
     * Extend and add your routes
     *
     * @return array
     */
    protected function routes()
    {
        return array();

    }

}

