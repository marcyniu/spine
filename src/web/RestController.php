<?php

namespace Spine\Web;

/**
 * Abstract Controller to handle REST requests
 *
 * @package    Spine
 * @subpackage Web
 * @author     Lance Rushing
 * @since      2013-11-20
 */
abstract class RestController extends BaseController implements ControllerInterface
{
    /**
     *
     * @throws HttpMethodNotAllowedException
     * @return void
     */
    public function dispatch()
    {
        $type = $this->request->type();
        switch ($type) {
            case 'GET':
                if ($this->request->resourceIdGiven()) {
                    $this->get();
                } else {
                    $this->query();
                }
                break;
            case 'HEAD':
                $this->head();
                break;
            case 'POST':
                if ($this->request->resourceIdGiven()) {
                    $this->update(); // Shouldn't update with POST... but we'll allow it.
                } else {
                    $this->create();
                }
                break;
            case 'PUT':
                if ($this->request->resourceIdGiven()) {
                    $this->update();
                } else {
                    throw new HttpMethodNotAllowedException("PUT is invalid without resource identifier");
                }
                break;
            case 'DELETE':
//				if ($this->request->resourceIdGiven()) {
                $this->delete();
//				} else {
//					throw new HttpMethodNotAllowedException("DELETE is invalid without resource identifier");
//				}
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
     */
    protected function query()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     */
    protected function get()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     */
    protected function head()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     */
    protected function update()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     */
    protected function create()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     */
    protected function delete()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Overridable method if the concrete controller wishes to handle.
     *
     * @return void
     */
    protected function options()
    {
        $this->notAllowed(__METHOD__);
    }

    /**
     * Throws not allowed for the given name
     *
     * @param $name
     *
     * @throws HttpMethodNotAllowedException
     */
    protected function notAllowed($name)
    {
        throw new HttpMethodNotAllowedException(
            sprintf("'%s()' not implemented", $name)
        );
    }
}

