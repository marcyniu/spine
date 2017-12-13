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
abstract class RestController extends Controller
{
    /**
     * @throws HttpMethodNotAllowedException
     * @return void
     */
    public function dispatch()
    {

        if (!isset($this->request)) {
            throw new \RuntimeException("\$this->request is not set. Check if this controller's (" . get_class($this) . ") constructor was overridden.");
        }

        $methodName = $this->getMethodName();

        $this->callMethod($methodName);

    }

    /**
     * @return string
     * @throws HttpMethodNotAllowedException
     */
    protected function getMethodName()
    {

        $type = $this->request->type();
        $idIsGiven = $this->request->resourceIdGiven();

        $methodName = '';
        switch ($type) {
            case 'GET':
                if ($idIsGiven) {
                    $methodName = 'get';
                } else {
                    $methodName = 'query';
                }
                break;
            case 'HEAD':
                $methodName = 'head';
                break;
            case 'POST':
                if ($idIsGiven) {
                    $methodName = 'update'; // Shouldn't update with POST... but we'll allow it.
                } else {
                    $methodName = 'create';
                }
                break;
            case 'PUT':
                if ($idIsGiven) {
                    $methodName = 'update';
                } else {
                    throw new HttpMethodNotAllowedException("PUT is invalid without resource identifier");
                }
                break;
            case 'DELETE':
                if ($idIsGiven) {
                    $methodName = 'delete';
                } else {
                    throw new HttpMethodNotAllowedException("DELETE is invalid without resource identifier");
                }
                break;
            case 'OPTIONS':
                $methodName = 'options';
                break;
            default:
                throw new HttpMethodNotAllowedException("Unknown HTTP request type of '$type'");
        }

        return $methodName;
    }

}

