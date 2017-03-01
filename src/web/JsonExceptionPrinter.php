<?php

namespace Spine\Web;

use Throwable;

class HttpExceptionPrinter
{

    protected $validErrors = [
        "200" => "OK",
        "201" => "Created",
        "204" => "No Content",
        "304" => "Not Modified",
        "400" => "Bad Request",
        "401" => "Unauthorized",
        "403" => "Forbidden",
        "404" => "Not Found",
        "409" => "Conflict",
        "500" => "Internal Server Error",
        "100" => "Continue",
        "101" => "Switching Protocols",
        "102" => "Processing",
        "202" => "Accepted",
        "203" => "Non-Authoritative Information",
        "205" => "Reset Content",
        "206" => "Partial Content",
        "207" => "Multi-Status",
        "208" => "Already Reported",
        "226" => "IM Used",
        "300" => "Multiple Choices",
        "301" => "Moved Permanently",
        "302" => "Found",
        "303" => "See Other",
        "305" => "Use Proxy",
        "307" => "Temporary Redirect",
        "308" => "Permanent Redirect",
        "402" => "Payment Required",
        "405" => "Method Not Allowed",
        "406" => "Not Acceptable",
        "407" => "Proxy Authentication Required",
        "408" => "Request Timeout",
        "410" => "Gone",
        "411" => "Length Required",
        "412" => "Precondition Failed",
        "413" => "Request Entity Too Large",
        "414" => "Request-URI Too Long",
        "415" => "Unsupported Media Type",
        "416" => "Requested Range Not Satisfiable",
        "417" => "Expectation Failed",
        "418" => "I'm a teapot",
        "420" => "Enhance Your Calm",
        "422" => "Unprocessable Entity",
        "423" => "Locked",
        "424" => "Failed Dependency",
        "425" => "Reserved for WebDAV",
        "426" => "Upgrade Required",
        "428" => "Precondition Required",
        "429" => "Too Many Requests",
        "431" => "Request Header Fields Too Large",
        "444" => "No Response",
        "449" => "Retry With",
        "450" => "Blocked by Windows Parental Controls",
        "451" => "Unavailable For Legal Reasons",
        "499" => "Client Closed Request",
        "501" => "Not Implemented",
        "502" => "Bad Gateway",
        "503" => "Service Unavailable",
        "504" => "Gateway Timeout",
        "505" => "HTTP Version Not Supported",
        "506" => "Variant Also Negotiates",
        "507" => "Insufficient Storage",
        "508" => "Loop Detected",
        "509" => "Bandwidth Limit Exceeded",
        "510" => "Not Extended",
        "511" => "Network Authentication Required",
    ];

    /**
     * Wraps ini_get('display_errors') to return a boolean.
     *
     * PHP doc says ini_get() will return a 0 for negative values like "off".
     * But this is not the case.
     *
     * @return boolean.
     */
    protected function iniGetDisplayErrors()
    {
        $displayErrors = strtolower(ini_get('display_errors'));

        return $displayErrors === 'on' || $displayErrors === '1';
    }

}

class JsonExceptionPrinter extends HttpExceptionPrinter
{

    public function printThrowable(\Throwable $throwable)
    {
        if (!$this->isJsonRequest()) {
            return;
        }
        $this->sendHeader();

        if ($this->iniGetDisplayErrors()) {
            $this->displayDeveloperJsonError($throwable);
        } else {
            $this->displaySafeJsonError($throwable);
        }
    }


    private function displayDeveloperJsonError(Throwable $throwable)
    {
        $errors = [];

        while (null !== $throwable) {
            $error                        = [];
            $error[get_class($throwable)] = sprintf("%s on line %s ", $throwable->getFile(), $throwable->getLine());
            $error['msg']                 = $throwable->getMessage();

            $traces = [];
            foreach ($throwable->getTrace() as $key => $trace) {
                $class    = "";
                $file     = "";
                $function = "";
                if (empty($trace['class']) === false) {
                    $class = $trace['class'] . $trace['type'];
                }

                if (empty($trace['file']) === false) {
                    $file = $trace['file'] . ":" . $trace['line'];
                }

                if (empty($trace['function']) === false) {
                    $function = $trace['function'] . "()";
                }


                $trace = trim("$class$function $file");

                $traces[$key] = $trace;
            }
            $error['trace'] = $traces;

            $errors[]  = $error;
            $throwable = $throwable->getPrevious();
        }

        echo json_encode($errors);
        // exit out to prevent other error handlers from ending output.
        // hack
        exit;
    }

    private function sendHeader()
    {
        if (headers_sent() === false) {
            header("Content-Type: application/json", true);
        }
    }

    private function displaySafeJsonError(Throwable $throwable)
    {
        echo json_encode([
            "code"    => $throwable->getCode(),
            "message" => $throwable->getMessage(),
        ]);
    }

    private function isJsonRequest()
    {
        if (isset($_SERVER['HTTP_ACCEPT']) && stristr($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            return true;
        }
        return false;
    }


}