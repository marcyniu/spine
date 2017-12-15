<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine\Web;

use Throwable;



/**
 * @codeCoverageIgnore
 */
class HttpHeadersErrorHandler implements ExceptionHandlerInterface
{

    /**
     * @param \Exception|Throwable|HttpException $exception
     */
    public function handleException($exception)
    {
        while (null !== $exception) {
            $code = 500;
            if (is_a($exception, 'Spine\Web\HttpException') === true) {
                $code = $exception->getCode();
            } elseif (method_exists($exception, 'getHttpCode')) {
                $code = $exception->getHttpCode();
            }

            if (headers_sent($file, $line)) {
                echo "\n<!-- Headers Sent $file:$line -->\n"; // send a comment to when the headers were sent
            } else {
                http_response_code($code);
            }
            $exception = $exception->getPrevious();
        }
    }

    public function handleError($code, $message, $file, $line)
    {
        if (headers_sent() === false) {
            $errorReporting = intval(ini_get('error_reporting'));
            if ($errorReporting & $code) { //allows for use of the '@' notation. ex: @ignoreThisError()
                http_response_code(500);
            }
        }

    }

    public function handleShutdown()
    {
        $lastError = error_get_last();

        if ($lastError !== null) {
            http_response_code(500);
        }
    }


}
