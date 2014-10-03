<?php

namespace Spine\Web;

/**
 * Basic Http Controller
 *
 * @since 2011-10-03
 */
abstract class Controller extends BaseController implements ControllerInterface
{
    /**
     *
     * @throws HttpMethodNotAllowedException
     * @return void
     */
    public function dispatch()
    {
        switch ($this->request->type()) {
            case 'GET':
                $this->get();
                break;
            case 'HEAD':
                $this->head();
                break;
            case 'POST':
                $this->post();
                break;
            case 'PUT':
                $this->put();
                break;
            case 'DELETE':
                $this->delete();
                break;
            case 'OPTIONS':
                $this->options();
                break;
            default:
                throw new HttpMethodNotAllowedException();
        }
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     * @throws HttpMethodNotAllowedException If called.
     */
    protected function get()
    {
        throw new HttpMethodNotAllowedException();
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     * @throws HttpMethodNotAllowedException If called.
     */
    protected function head()
    {
        $this->response->sendMethodNotAllowed();
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     * @throws HttpMethodNotAllowedException If called.
     */
    protected function post()
    {
        throw new HttpMethodNotAllowedException();
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     * @throws HttpMethodNotAllowedException If called.
     */
    protected function delete()
    {
        throw new HttpMethodNotAllowedException();
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     * @throws HttpMethodNotAllowedException If called.
     */
    protected function options()
    {
        $this->response->sendMethodNotAllowed();
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     * @throws HttpMethodNotAllowedException If called.
     */
    protected function put()
    {
        throw new HttpMethodNotAllowedException();
    }
}