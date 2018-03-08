<?php
namespace Spine\Web;

/** @noinspection SpellCheckingInspection */
if (!function_exists('getallheaders')) {
    /** @noinspection SpellCheckingInspection */
    function getallheaders()
    {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$name] = $value;
            }
            // standardize key names
            if ($name == "CONTENT_TYPE") {
                $headers["Content-Type"] = $value;
            }
            if ($name == "CONTENT_LENGTH") {
                $headers["Content-Length"] = $value;
            }

        }
        return $headers;
    }
}

/**
 *
 */
class Request
{

    protected $params = array();

    protected $server = array();

    protected $pathParams = array();
    protected $phpInput = null;

    /**
     * Setup request.
     */
    public function __construct()
    {
        $this->server = $_SERVER;

        $this->validateParamsAndType();
        $this->setupParams();
    }

    /**
     * @return string
     */
    public function accept()
    {
        return isset($this->server['HTTP_ACCEPT']) ? $this->server['HTTP_ACCEPT'] : null;
    }

    /**
     * @return string
     */
    public function host()
    {
        return isset($this->server['HTTP_HOST']) ? $this->server['HTTP_HOST'] : null;
    }

    /**
     * @param $key
     *
     * @return string|array|null
     */
    public function param($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    /**
     * @param $key
     *
     * @return string | null
     */
    public function pathParam($key)
    {
        return isset($this->pathParams[$key]) ? $this->pathParams[$key] : null;
    }

    /**
     * @return array | null
     */
    public function pathParams()
    {
        return $this->pathParams;
    }

    /**
     *
     * @return string
     */
    public function path()
    {
        return parse_url($this->server['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * @return string
     */
    public function port()
    {
        return isset($this->server['SERVER_PORT']) ? $this->server['SERVER_PORT'] : null;
    }

    /**
     *
     * @return string
     */
    public function queryString()
    {
        if (isset($this->server['QUERY_STRING'])) {
            return $this->server['QUERY_STRING'];
        }
        return "";
    }

    /**
     *
     * @return string
     */
    public function referrer()
    {
        return isset($this->server['HTTP_REFERER']) ? $this->server['HTTP_REFERER'] : '';
    }

    /**
     * @param $key
     *
     * @return string|string[]
     * @throws HttpBadRequestException
     */
    public function requiredParam($key)
    {
        $value = $this->param($key);
        if (is_null($value)) {
            throw new HttpBadRequestException("Parameter '$key' is missing.");
        }
        return $value;
    }

    /**
     * @param $key
     *
     * @return string
     * @throws HttpBadRequestException
     */
    public function requiredStringParam(string $key) :string
    {
        $value = $this->requiredParam($key);
        if(!is_string($value)) {
            throw new HttpBadRequestException("Parameter '$key' is not a string.");
        }
        return $value;
    }

    /**
     * @param $key
     *
     * @return string[]
     * @throws HttpBadRequestException
     */
    public function requiredArrayParam(string $key) :array
    {
        $value = $this->requiredParam($key);
        if(!is_array($value)) {
            throw new HttpBadRequestException("Parameter '$key' is not an array.");
        }
        return $value;
    }

    /**
     * @return array
     */
    public function params()
    {
        return $this->params;
    }

    /**
     *
     * @return string
     */
    public function type()
    {
        return isset($this->server['REQUEST_METHOD']) ? $this->server['REQUEST_METHOD'] : null;
    }

    /**
     *
     * @return string
     */
    public function agent()
    {
        return isset($this->server['HTTP_USER_AGENT']) ? $this->server['HTTP_USER_AGENT'] : null;
    }

    public function userIp()
    {
        $ip = null;
        if (isset($this->server['HTTP_CLIENT_IP']) && !empty($this->server['HTTP_CLIENT_IP'])) {
            $ip = $this->server['HTTP_CLIENT_IP'];
        } elseif (isset($this->server['HTTP_X_FORWARDED_FOR']) && !empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $ip = $this->server['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = isset($this->server['REMOTE_ADDR']) ? $this->server['REMOTE_ADDR'] : null;
        }
        return $ip;
    }

    public function header($name)
    {
        $name    = strtolower($name);
        $headers = array_change_key_case($this->headers());
        return isset($headers[$name]) ? $headers[$name] : null;
    }

    protected function headers()
    {
        return getallheaders();
    }

    protected function cleanParam(&$val)
    {
        if (is_string($val)) {
            $val = trim($val);
            $val = str_replace(
                array(
                    "\xe2\x80\x98",
                    "\xe2\x80\x99",
                    "\xe2\x80\x9c",
                    "\xe2\x80\x9d",
                    "\xe2\x80\x93",
                    "\xe2\x80\x94",
                    "\xe2\x80\xa6"
                ),
                array(
                    "'",
                    "'",
                    '"',
                    '"',
                    '-',
                    '--',
                    '...'
                ),
                $val
            );
            // Next, replace their Windows-1252 equivalents.
            $val = str_replace(
                array(chr(145), chr(146), chr(147), chr(148), chr(150), chr(151), chr(133)),
                array("'", "'", '"', '"', '-', '--', '...'),
                $val
            );
        }
    }

    /**
     *
     * @return void
     */
    protected function setupParams()
    {
        switch ($this->type()) {
            case 'GET':
            case 'HEAD':
                $this->params = $_GET;
                break;
            case 'POST':
                $this->params = $this->hasAjaxData()
                    ? json_decode(
                        file_get_contents('php://input'),
                        true
                    ) ?: $_POST // default to _POST if no php://input
                    : $_POST;
                break;
            case 'PUT':
            case 'DELETE':
                $this->params = $this->hasAjaxData()
                    ? json_decode(
                        $this->getInput(),
                        true
                    ) ?: array() // default to array() if no php://input
                    : array_merge($_GET, $_POST);
                break;
        }

        // trim and clean the params array;
        array_walk_recursive($this->params, array($this, "cleanParam"));

    }

    protected function getInput()
    {
        if ($this->phpInput === null) {
            $this->phpInput = file_get_contents('php://input');
        }
        return $this->phpInput;
    }

    /**
     *
     * @return void
     * @throws HttpBadRequestException When params don't match request type.
     */
    private function validateParamsAndType()
    {
        if ($this->type() === 'POST') {
//			if (count($_GET) !== 0) {
//				throw new HttpBadRequestException("POST request contains GET parameters");
//			}
        }

        if ($this->type() !== 'POST') {
            if (count($_POST) !== 0) {
                throw new HttpBadRequestException("Non-POST request contains POST parameters");
            }
        }
    }

    /**
     * @return bool
     */
    private function hasAjaxData()
    {
        $requestHeaders = getallheaders();
        return isset($requestHeaders['Content-Type']) && stripos(
            $requestHeaders['Content-Type'],
            'application/json'
        ) !== false;
    }

    public function setPathParams($pathParams)
    {
        $this->pathParams = $pathParams;
    }

    public function resourceIdGiven()
    {
        $lastPathParamValue = "/" . end($this->pathParams);
        reset($this->pathParams);
        $lastPartOfPath = substr($this->path(), -strlen($lastPathParamValue));
        return ($lastPathParamValue == $lastPartOfPath);

    }

}