<?php
/**
 *
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  9/5/14
 */

namespace Spine\Web;

abstract class Filter implements \Spine\Filter
{

    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Response
     */
    protected $response;

    public function __construct(Request $request, Response $response)
    {

        $this->request  = $request;
        $this->response = $response;
    }

} 