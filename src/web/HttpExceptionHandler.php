<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine\Web;

use Exception;
use Throwable;

/**
 * @codeCoverageIgnore
 */
class HttpExceptionHandler extends HttpExceptionPrinter
{
    private $jsonPrinter;
    private $htmlPrinter;


    public function __construct($dir)
    {
        $this->jsonPrinter = new JsonExceptionPrinter();
        $this->htmlPrinter = new HtmlExceptionPrinter($dir);
    }

    /**
     * Registers the error itself as the an error handler.
     *
     * @return void
     */
    public function register()
    {
        set_exception_handler([$this, "handleException"]);
    }

    public function handleException(Throwable $throwable)
    {
        $this->sendHeaders($throwable);
        $this->sendBody($throwable);

        // re throw the exception down the chain
        restore_exception_handler();
        throw new Exception("Uncaught exception", 0, $throwable);

    }


    private function sendHeaders(Throwable $throwable)
    {
        $code = $throwable->getCode();
        if (!isset($this->validErrors[$code])) {
            $code = "500";
        }
        $message = $this->validErrors[$code];

        if (headers_sent() === false) {
            header(
                sprintf('HTTP/1.1 %s %s', $code, $message),
                true,
                $code
            );
        }

    }

    private function sendBody(Throwable $throwable)
    {
        $this->htmlPrinter->printThrowable($throwable);
        $this->jsonPrinter->printThrowable($throwable);
    }

}
