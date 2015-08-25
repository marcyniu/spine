<?php

namespace Spine;

/**
 * Uses the __set magic function to prevent properties from being set unless they are defined.
 */
class StrictClass
{
    use StrictTrait;
}
