<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine;

use Exception;
use Throwable;

/**
 * Uses composition to call several error handlers.
 */
class ErrorHandlerCollection
{

    private $errorHandlers = [];
    private $exceptionHandlers = [];

    private $suppressBuiltInPhpErrorHandler = false;

    public function __construct()
    {
        $eh = new ExceptionPrinterAndLogger();
        $this->addExceptionHandler([$eh, 'handleException']);
    }

    /**
     * Registers the error itself as the an error handler.
     *
     * @return void
     */
    public function register()
    {
        $previous = set_error_handler([$this, "handleError"]);

        if (!is_null($previous)) {
            // there exists another error handler ..
            $this->suppressBuiltInPhpErrorHandler = true;
        }

        $previous = set_exception_handler([$this, "handleException"]);
        if (!is_null($previous)) {
            // there exists another exception handler ..
            trigger_error("Another Exception Handler was detected.", E_USER_ERROR);
        }


    }


    /**
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     * @param array  $errcontext
     * @return bool
     */
    public function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
    {
        restore_error_handler();
        // Reverse loop through array
        for (end($this->errorHandlers); key($this->errorHandlers)!==null; prev($this->errorHandlers)){
            $errorHandler = current($this->errorHandlers);
            call_user_func($errorHandler, $errno, $errstr, $errfile, $errline, $errcontext);
        }

        return $this->suppressBuiltInPhpErrorHandler; // false will cause the standard PHP error handle to trigger
    }

    /**
     * @param Exception|\Throwable $exception
     * @throws Exception
     */
    public function handleException($exception)
    {
        // Reverse loop through array
        for (end($this->exceptionHandlers); key($this->exceptionHandlers)!==null; prev($this->exceptionHandlers)){
            $exceptionHandler = current($this->exceptionHandlers);
            call_user_func($exceptionHandler, $exception);
        }

    }

    public function addErrorHandler($callable)
    {
        $this->errorHandlers[] = $callable;
    }

    public function addExceptionHandler($callable)
    {
        $this->exceptionHandlers[] = $callable;
    }


}
