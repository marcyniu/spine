<?php

namespace Spine;

/**
 * A nice sha512 hmac hasher, useful for message signing
 *
 * @author Lance Rushing
 */
class MessageHasher
{

    /**
     * Key to 'sign' the plain strings with.
     *
     * @var string
     */
    private $key;

    private $saltBytes = 16;

    /**
     *
     * @param string $key Key to 'sign' the plain strings with.
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Return a salt and hash (delimited) of the given string.
     *
     * @param string $plain
     *
     * @return string
     */
    public function hash($plain)
    {
        $salt = $this->getSalt();
        $hash = $this->_hash($salt, $plain);
        return base64_encode($salt . $hash);
    }

    /**
     * Gets a salty random number.
     *
     * @return string
     */
    private function getSalt() :string
    {
        return openssl_random_pseudo_bytes($this->saltBytes);
    }

    /**
     * @param string $salt
     * @param string $string
     *
     * @return string
     */
    private function _hash(string $salt, string $string) :string
    {
        return hash_hmac("sha512", $salt . $string, $this->key, true);
    }

    /**
     * Checks if the saltAndHashed string matches the plain string.
     *
     * @param string $plain        Plain string to compare hash against.
     * @param string $existingHash Salt and hash concatenate.
     *
     * @return boolean
     */
    public function verify($plain, $existingHash)
    {
        $existingHash = base64_decode($existingHash);
        $salt         = substr($existingHash, 0, $this->saltBytes);
        $hash         = substr($existingHash, $this->saltBytes);
        $newHash      = $this->_hash($salt, $plain);
        return ($hash === $newHash);
    }

}

