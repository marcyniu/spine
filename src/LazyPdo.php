<?php

namespace Spine;

/**
 * Class LazyPdo
 * @package Spine
 * @author Lance Rushing <lancerushing@gmail.com>
 * @see http://stackoverflow.com/questions/5484790/auto-connecting-to-pdo-only-if-needed
 */
class LazyPdo extends \PDO
{

    /**
     * @var string
     */
    private $dsn;

    /**
     * @var array
     */
    private $options;

    /**
     * @var string
     */
    private $pass;

    /**
     * @var string
     */
    private $user;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var bool
     */
    private $connected = false;

    /**
     * LazyPdo constructor.
     * @param string $dsn
     * @param string $user
     * @param string $pass
     * @param array $options
     */
    public function __construct($dsn, $user = null, $pass = null, array $options = null)
    {
        $this->dsn     = $dsn;
        $this->user    = $user;
        $this->pass    = $pass;
        $this->options = $options;
    }

    public function beginTransaction()
    {
        $this->connect();
        return parent::beginTransaction();
    }

    public function commit()
    {
        $this->connect();
        return parent::commit();
    }

    public function errorCode()
    {
        $this->connect();
        return parent::errorCode();
    }

    public function errorInfo()
    {
        $this->connect();
        return parent::errorInfo();
    }

    public function exec($statement)
    {
        $this->connect();
        return parent::exec($statement);
    }

    public function getAttribute($attribute)
    {
        $this->connect();
        return parent::getAttribute($attribute);
    }

    public function inTransaction()
    {
        $this->connect();
        return parent::inTransaction();
    }

    public function lastInsertId($name = null)
    {
        $this->connect();
        return parent::lastInsertId($name);
    }

    public function prepare($statement, $driver_options = null)
    {
        $this->connect();
        if (empty($ctorargs)) {
            return parent::prepare($statement);

        }
        return parent::prepare($statement, $driver_options);
    }

    public function query($statement, $mode = null, $arg3 = null, array $ctorargs = [])
    {
        $this->connect();
        if (empty($ctorargs)) {
            if (is_null($arg3)) {
                if (is_null($mode)) {
                    return parent::query($statement);
                }
                return parent::query($statement, $mode);
            }
            return parent::query($statement, $mode, $arg3);
        }
        return parent::query($statement, $mode, $arg3, $ctorargs);
    }

    public function quote($string, $parameter_type = \PDO::PARAM_STR)
    {
        $this->connect();
        return parent::quote($string, $parameter_type);
    }

    public function rollBack()
    {
        $this->connect();
        return parent::rollBack();
    }

    public function setAttribute($attribute, $value)
    {
        if ($this->connected) {
            return parent::setAttribute($attribute, $value);
        } else {
            $this->attributes[$attribute] = $value;
            return true;
        }

    }

    private function connect()
    {
        if (!$this->connected) {
            parent::__construct($this->dsn, $this->user, $this->pass, $this->options);
            $this->connected = true;
            foreach ($this->attributes as $name => $value) {
                parent::setAttribute($name, $value);
            }
        }
    }
}