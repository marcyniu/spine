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
     * @param string      $name
     * @param string|null $value
     * @param int|null    $expire
     * @param string|null $path
     * @param string|null $domain
     * @param bool|null   $secure
     * @param bool|null   $httponly
     */
    public function set(string $name, string $value = null, int $expire = null, string $path = null, string $domain = null, bool $secure = null, bool $httponly = null)
    {
        $this->store[$name] = $value;
    }

}