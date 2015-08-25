<?php
/**
 *
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  11/10/14
 */

namespace Spine\Web;

class FakeCookies extends Cookies
{

    public $store = [];

    /**
     * @param string $name
     *
     * @return mixed cookie value or NULL if cookie doesn't exist
     */
    public function get($name)
    {
        if (isset($this->store[$name])) {
            return $this->store[$name];
        }
        return null;
    }

    /**
     * @param string $name
     * @param string $value
     * @param null   $expire
     */
    public function set($name, $value, $expire = null)
    {
        $this->store[$name] = $value;
    }

}