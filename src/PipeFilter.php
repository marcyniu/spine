<?php
namespace Spine;

/**
 * The Pipe class to attach filters to
 *
 * @package    Spine
 * @author     Lance Rushing
 * @since      2014-02-05
 */

class Pipe
{
    /**
     *
     * @var Array $filters to hold the pipe's filters
     */
    private $filters = array();

    /**
     * @param Filter $filter
     *
     * @return Pipe
     */
    public function add(Filter $filter)
    {
        $this->filters[] = $filter;
        return $this;
    }

    /**
     * @return boolean
     */
    public function execute()
    {
        $result = false;
        /** @var $filter PipeFilter */
        foreach ($this->filters as $filter) {
            $result = $filter->execute();
            if ($result === false) {
                break;
            }
        }

        return $result;
    }

}

/**
 * Base Class for Filters
 */
Interface Filter
{
    /**
     * @return bool
     */
    public function execute();

}