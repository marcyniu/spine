<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine\Web;

use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @codeCoverageIgnore
 */
class ErrorHandler
{

    protected $httpResponseCode = 500;
    private $httpResponseMessage = "Internal Server Error";
    private $headerSet = false;
    private $prettySent = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $errorTemplateDirectory = "";

    /**
     * ErrorHandler constructor.
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        $this->errorTemplateDirectory = $_SERVER['DOCUMENT_ROOT'];
    }


    /**
     * Registers the error itself as the an error handler.
     *
     * @return void
     */
    public function register()
    {
        set_error_handler([$this, "handleError"]);
        set_exception_handler([$this, "handleException"]);
        register_shutdown_function([$this, "shutdownFunction"]);
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
    public function handleException(Throwable $exception)
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
            if ($this->isJsonRequest()) {
                $this->displayPrettyJsonError();
            } else {
                $this->displayPretty();
            }
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
                header("HTTP/1.1 $this->httpResponseCode $this->httpResponseMessage", false);
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

        return $displayErrors === 'on' || $displayErrors === '1';
    }

    /**
     * Displays a pretty error.
     *
     * Checks for a file in DOCUMENT ROOT called {ErrorCode}.[php|html], and will include it
     * @return void
     */
    public function displayPretty()
    {
        if ($this->prettySent === false) {
            $errorDocumentBasename = sprintf('%s/%s', $this->errorTemplateDirectory, $this->httpResponseCode);


            if (is_file("$errorDocumentBasename.html") === true) {
                include "$errorDocumentBasename.html";
            } elseif (is_file("$errorDocumentBasename.php") === true) {
                include "$errorDocumentBasename.php";
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
     * @param Throwable $exception
     *
     * @return void
     */
    protected function logException(Throwable $exception)
    {
        if ($this->logger) {


            while (null !== $exception) {

                $uniqueLogId = uniqid();

                $msg = sprintf(
                    "Unhandled Exception %s %s %s (%s)",
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                );

                $serverKeys = array_flip(['REMOTE_ADDR', 'SERVER_NAME', 'REQUEST_URI', 'REQUEST_METHOD', 'HTTP_USER_AGENT', 'HTTP_COOKIE']);

                $stackTrace = [];
                foreach ($exception->getTrace() as $key => $trace) {
                    $class = '';
                    $file  = '';

                    if (empty($trace['class']) !== true) {
                        $class = $trace['class'] . $trace['type'];
                    }

                    if (empty($trace['file']) !== true) {
                        $file = sprintf('%s (%s)', $trace['file'], $trace['line']);
                    }

                    $stackTrace[] = sprintf("$uniqueLogId:%s %s %s %s", $key, $class, $trace['function'], $file);
                }


                $this->logger->error($msg, ['TRACE' => $stackTrace, '_GET' => $_GET, '_POST' => $_POST, '_SERVER' => array_intersect_key($_SERVER, $serverKeys)]);

                $exception = $exception->getPrevious();
            }
            //end while
        }
    }

    /**
     *
     * @param Exception $exception
     *
     * @return void
     */
    public static function printException(Throwable $exception)
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

    /**
     * @param string $errorTemplateDirectory
     */
    public function setErrorTemplateDirectory(string $errorTemplateDirectory)
    {
        if (!is_dir($errorTemplateDirectory)) {
            throw new \InvalidArgumentException("Could not find directory '$errorTemplateDirectory'");
        }
        $this->errorTemplateDirectory = $errorTemplateDirectory;
    }

    private function isJsonRequest()
    {
        if (isset($_SERVER['HTTP_ACCEPT']) && stristr($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            return true;
        }
        return false;
    }

    private function displayPrettyJsonError()
    {
        if (headers_sent() === false) {
            header("Content-Type: application/json", true);
        }
        echo json_encode([
            "code"    => $this->httpResponseCode,
            "message" => $this->httpResponseMessage,
        ]);

    }

}
