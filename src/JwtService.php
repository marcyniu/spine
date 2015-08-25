<?php

namespace Spine;

use JWT;

/**
 * JWT Token
 *
 * @author Lance Rushing
 */
class JwtService
{

    /**
     * How long are tokens valid for in seconds
     *
     * @var int
     */
    private $lifeTime = 3000;

    /**
     * @var int
     */
    private $nbfFuzz = 5;

    /**
     * Key to 'sign' the plain strings with.
     *
     * @var string
     */
    private $key;

    /**
     *
     * @param string $key Key to 'sign' the plain strings with.
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    public function createToken($subject)
    {
        $token = array(
            "exp" => time() + $this->lifeTime,
            "nbf" => time() - $this->nbfFuzz,
            "sub" => $subject
        );

        return JWT::encode($token, $this->key);
    }

    /**
     * @param $token
     *
     * @return mixed
     */
    public function getSubject($token)
    {
        $token = JWT::decode($token, $this->key);

        $this->verify($token);

        return $token->sub;
    }

    /**
     * Checks if the saltAndHashed string matches the plain string.
     *
     *
     * @param $token
     *
     * @return bool
     */
    private function verify($token)
    {
        if (!isset($token->nbf)) {
            throw new \RuntimeException("nbf required");
        }

        if (time() < $token->nbf) {
            throw new \RuntimeException("token is invalid");
        }

        return true;
    }

}

