<?php
/**
 * @since  2014-02-24
 * @author Lance Rushing
 */

namespace Spine\Web;

use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * @codeCoverageIgnore
 */
class HttpBodyFallbackErrorHandler
{

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
                $this->fallbackNotice($code, $exception);
            }
            $exception = $exception->getPrevious();
        }
    }


    /**
     * Usually we want to send HTTP headers to NGINX can put a pretty message, but sometimes headers are already sent.
     *
     * So try to grab a template from doc_root and send it
     */
    private function fallbackNotice($code, $e)
    {
        echo "fall back\n";
        $errorDocumentFilename = sprintf('%s/%s.html', $_SERVER["DOCUMENT_ROOT"], $code);
        if (is_file($errorDocumentFilename) === true) {
            echo file_get_contents($errorDocumentFilename);
        } else {
            printf('<h1>%s</h1><p>%s</p>', htmlentities(strval($code)),
                htmlentities($e->getMessage()));
        }

    }

}
