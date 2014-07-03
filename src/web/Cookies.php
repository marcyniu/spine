<?php
/**
 * @author: Lance Rushing
 * @since 2014-02-22
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
     */
    public function set($name, $value)
    {
        setcookie($name, $value, null, "/");
    }

} 