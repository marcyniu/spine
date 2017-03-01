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
class HttpExceptionHandler
{

    protected $httpResponseCode = 500;
    private $httpResponseMessage = "Internal Server Error";

    /**
     * Registers the error itself as the an error handler.
     *
     * @return void
     */
    public function register()
    {
        set_exception_handler([$this, "handleException"]);
    }

    public function handleException(Throwable $exception)
    {

        if (is_a($exception, 'Spine\Web\HttpException') === true) {
            $this->httpResponseCode    = $exception->getCode();
            $this->httpResponseMessage = $exception->getMessage();
        }

        if (headers_sent() === false) {
            header(
                sprintf('HTTP/1.1 %s %s', $this->httpResponseCode, $this->httpResponseMessage),
                true,
                $this->httpResponseCode
            );
        }


        restore_exception_handler();
        throw new Exception("Uncaught exception", 0, $exception);

    }

}
