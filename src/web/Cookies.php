<?php
/**
 * @author: lance
 * @since 20140-02-22
 */

namespace Spine\Web;

/**
 * Class Cookies, wraps up the Cookie super globals
 *
 * @package Spine\Web
 */
class Cookies
{

    /**
     * @param string $name
     *
     * @return mixed cookie value or NULL if cookie doesn't exist
     */
    public function get($name)
    {
        if (isset($_COOKIE[$name])) {
            return $_COOKIE[$name];
        }
        return null;
    }

    /**
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     */
    public function set(string $name, string $value = null, int $expire = null, string $path = null, string $domain = null, bool $secure = null, bool $httponly = null)
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function delete($name, $path=null)
    {
        unset($_COOKIE[$name]);
        $this->set($name, null, -1, $path);
    }
} 