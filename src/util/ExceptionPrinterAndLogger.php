<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine;


/**
 * Uses composition to call several error handlers.
 */
class ExceptionPrinterAndLogger
{

    /**
     * @param \Exception|\Throwable $exception
     */
    public function handleException($exception)
    {

        while (null !== $exception) {
            if ($this->iniGetDisplayErrors()) {
                $this->printException($exception);
            }

            $this->logException($exception);
            $exception = $exception->getPrevious();
        }
    }

    /**
     * @param \Exception|\Throwable $exception
     */
    private function printException($exception)
    {
        $msg = sprintf("Fatal error: Uncaught exception '%s' with message '%s' in %s on line %u", get_class($exception), $exception->getMessage(), $exception->getFile(),
            $exception->getLine());
        if (isset($exception->xdebug_message)) {

            echo php_sapi_name() != "cli" ? '<table class="xdebug-error xe-uncaught-exception" dir="ltr" border="1" cellspacing="0" cellpadding="1">' : '';
            printf('<tr><th align="left" bgcolor="#f57900" colspan="5"><span style="background-color: #cc0000; color: #fce94f; font-size: x-large;">( ! )</span>%s</th></tr>', $msg);
            echo $exception->xdebug_message;
            echo php_sapi_name() != "cli" ? '</table>' : '';
        } else {
            echo php_sapi_name() != "cli" ? '<pre>' : '';
            echo str_repeat("=", 150) . "\n";
            echo $msg;
            echo str_repeat("-", 150) . "\n";
            printf("Unhandled Exception '%s': in '%s' on line %s\n", get_class($exception), $exception->getFile(),
                $exception->getLine());

            $i = 0;
            foreach ($exception->getTrace() as $key => $trace) {
                $i++;
                $function = !empty($trace['function']) ? $trace['function'] : '';

                if (!empty($trace['class'])) {
                    $function = $trace['class'] . $trace['type'] . $trace['function'];
                }

                $file = "";
                if (empty($trace['file']) === false) {
                    $file = $trace['file'] . ":" . $trace['line'];
                }
                printf("%03u  %-50s  %20s\n", $i, $function . '()', $file);
            }

            echo php_sapi_name() != "cli" ? '</pre>' : '';
        }
    }

    /**
     * @param \Exception|\Throwable $exception
     */
    private function logException($exception)
    {
        $msg = sprintf("%s: %s : in '%s' on line %s", get_class($exception), $exception->getMessage(), $exception->getFile(),
            $exception->getLine());
        error_log($msg);

        $msg = sprintf("Unhandled Exception '%s': in '%s' on line %s", get_class($exception), $exception->getFile(),
            $exception->getLine());
        error_log($msg);

        error_log(var_export($exception->getTraceAsString(), true));
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
            return true;
        }
        return false;
    }
}
