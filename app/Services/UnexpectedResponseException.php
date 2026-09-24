<?php

namespace App\Services;

use RuntimeException;

/**
 * A WordCamp site answered successfully, but with something that isn't the
 * data asked for — an HTML page where JSON was expected, an object where a
 * list was. Kept distinct so the fetch log can say so plainly instead of
 * surfacing a PHP type error.
 */
class UnexpectedResponseException extends RuntimeException {}
