<?php
/**
 *
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  2017-02-16
 */

namespace Spine\Web;

class RequireHttps extends Filter
{
    /**
     * @return bool
     */
    public function execute()
    {
        $request = $this->request;

        if ($request->port() == '443'
            || $request->header('x-forwarded-port') == '443'
        ) {
            return true;
        }

        $this->response->redirect('https://' . $request->host() . $request->path());
        return false;
        
    }

}