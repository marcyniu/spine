<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine\Web;

use ErrorException;
use Exception;

/**
 * @codeCoverageIgnore
 */
class ErrorHandler
{

    private $httpResponseCode = 500;
    private $httpResponseMessage = "Internal Server Error";
    private $headerSet = false;
    private $prettySent = false;

    /**
     * Registers the error itself as the an error handler.
     *
     * @return void
     */
    public function register()
    {
        set_error_handler(array($this, "handleError"));
        set_exception_handler(array($this, "handleException"));
        register_shutdown_function(array($this, "shutdownFunction"));
    }

    /**
     * @param integer $code
     * @param string  $message
     * @param string  $file
     * @param integer $line
     *
     * @return void
     * @throws ErrorException If an error occurs.
     */
    public function handleError($code, $message, $file, $line)
    {
        if (intval(ini_get('error_reporting')) === 0) {
            //allows for use of the '@' notation. ex: @possibleBadThing()
            return;
        }

        // restore handler in case the handler has an error;
        restore_error_handler();
        throw new ErrorException($message, 0, $code, $file, $line);
    }

    /**
     * If $exception is a HttpException, this will send the correct HTTP error codes .
     *
     * For pretty error messages use the web server's ErrorDocument configurations.
     *
     * @param Exception|HttpException $exception
     *
     * @return void
     */
    public function handleException(Exception $exception)
    {
        restore_error_handler();
        restore_exception_handler();

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
        } else {
            $this->printCloseHtmlTags();
        }

        $this->headerSet = true;

        if ($this->iniGetDisplayErrors() === true) {
            self::printException($exception);
        } else {
            $this->displayPretty();
        }

        $this->logException($exception);
    }

    /**
     * Makes sure error headers are set correctly.
     *
     * PHP Fatal Errors will not trigger error handlers set with set_error_handler().
     *
     * @return void
     */
    public function shutdownFunction()
    {

        $lastError = error_get_last();

        if ($lastError !== null) {
            if ($this->headerSet === false) {
                if (headers_sent() === false) {
                    header("HTTP/1.1 $this->httpResponseCode $this->httpResponseMessage", false);
                } else {
                    $this->printCloseHtmlTags();
                }
            }
            if ($this->iniGetDisplayErrors() === false) {
                $this->displayPretty();
            }
        }
    }

    /**
     * Wraps ini_get('display_errors') to return a boolean.
     *
     * PHP doc says ini_get() will return a 0 for negative values like "off".
     * But this is not the case.
     *
     * @return boolean.
     */
    public function iniGetDisplayErrors()
    {

        $displayErrors = strtolower(ini_get('display_errors'));

        if ($displayErrors === 'on' || $displayErrors === '1') {
            // let PHP show the errors. (dev)
            return true;
        }

        return false;
    }

    /**
     * Displays a pretty error.
     *
     * Checks for a file in DOCUMENT ROOT called {ErrorCode}.html, and will display
     * it
     *
     * @return void
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public function displayPretty()
    {
        if ($this->prettySent === false) {
            $errorDocumentFilename = sprintf('%s/%s.html', $_SERVER["DOCUMENT_ROOT"], $this->httpResponseCode);
            if (is_file($errorDocumentFilename) === true) {
                echo file_get_contents($errorDocumentFilename);
            } else {
                printf(
                    '<h1>%s</h1><p>%s</p>',
                    htmlentities($this->httpResponseCode),
                    htmlentities($this->httpResponseMessage)
                );
            }

            $this->prettySent = true;
        }
    }

    /**
     * Prints closing HTML tags that typically prevent an an error message from rendering in the browser.
     *
     * @return void
     */
    private function printCloseHtmlTags()
    {
        echo "'\"</script></style></tr></table></body></html>";
    }

    /**
     *
     * @param Exception $exception
     *
     * @return void
     */
    private function logException(Exception $exception)
    {
        while (null !== $exception) {
            $uniqueLogId = uniqid();
            error_log(
                sprintf(
                    "$uniqueLogId:Unhandled Exception %s %s %s (%s)",
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                )
            );
            error_log(sprintf("$uniqueLogId:GET: %s", json_encode($_GET)));
            error_log(sprintf("$uniqueLogId:POST: %s", json_encode($_POST)));
            foreach ($_SERVER as $key => $value) {
                if (!is_string($value)) {
                    $value = json_encode($value);
                }
                error_log(sprintf("$uniqueLogId:SERVER[%s]: %s", $key, $value));
            }

            error_log("$uniqueLogId:Call Stack");
            foreach ($exception->getTrace() as $key => $trace) {
                $class = '';
                $file  = '';

                if (empty($trace['class']) !== true) {
                    $class = $trace['class'] . $trace['type'];
                }

                if (empty($trace['file']) !== true) {
                    $file = sprintf('%s (%s)', $trace['file'], $trace['line']);
                }

                error_log(sprintf("$uniqueLogId:%s %s %s %s", $key, $class, $trace['function'], $file));
            }

            $exception = $exception->getPrevious();
        }
        //end while
    }

    /**
     *
     * @param Exception $exception
     *
     * @return void
     */
    public static function printException(Exception $exception)
    {
        while (null !== $exception) {
            if (isset($exception->xdebug_message) === true) {
                /** @noinspection PhpUndefinedFieldInspection */
                echo "<table class='error'>" . $exception->xdebug_message . "</table>";
            } else {
                echo "<table class='error'>
					<tr><th colspan='3'>Unhandled Exception '" . get_class($exception) . "':  in '" . htmlentities(
                        $exception->getFile()
                    ) . "' on line <i>'" . htmlentities($exception->getLine()) . "'</i></th></tr>
					<tr><th colspan='3'>" . htmlentities($exception->getMessage()) . "</th></tr>
					<tr><th colspan='3'>Call Stack</th></tr>
					<tr><th>#</th><th>Function</th><th>Location</th></tr>
					";
                foreach ($exception->getTrace() as $key => $trace) {
                    $class = "";
                    $file  = "";
                    if (empty($trace['class']) === false) {
                        $class = $trace['class'] . $trace['type'];
                    }

                    if (empty($trace['file']) === false) {
                        $file = $trace['file'] . "<b>:</b>" . $trace['line'];
                    }

                    echo "<tr><td>$key</td><td>$class</td><td>$file</td></tr>\n";
                }

                echo "</table>";
            }
            //end if

            $exception = $exception->getPrevious();
        }
        //end while
    }

}
