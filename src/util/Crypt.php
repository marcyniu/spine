<?php

namespace Spine;

class Crypt
{

    private $key;
    private $cipher = 'aes-256-cbc';
    private $ivSize = 16;

    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public function encrypt($message)
    {
        $iv = openssl_random_pseudo_bytes($this->ivSize);
        return base64_encode($iv . openssl_encrypt($message, $this->cipher, $this->key, true, $iv));

    }

    /**
     *
     * @param string $encrypted
     *
     * @return string
     */
    public function decrypt($encrypted)
    {
        $encrypted        = base64_decode($encrypted);
        $iv               = substr($encrypted, 0, $this->ivSize);
        $encryptedMessage = substr($encrypted, $this->ivSize);
        return openssl_decrypt($encryptedMessage, $this->cipher, $this->key, true, $iv);
    }

}