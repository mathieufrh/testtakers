<?php

/**
 * Filter a resource.
 *
 * @param  mix  $attach
 * @return Resource;
 */
function filter($attach)
{
    return app('filter')->attach($attach);
}

/**
 * Check if the string starts with the given substring.
 *
 * @param  string  $string
 * @param  string  $substring
 * @return bool
 */
function startsWith($string, $substring)
{
     $length = strlen($substring);

     return (substr($string, 0, $length) === $substring);
}

/**
 * Check if the string ends with the given substring.
 *
 * @param  string  $string
 * @param  string  $substring
 * @return bool
 */
function endsWith($string, $substring)
{
    $length = strlen($substring);

    if ($length == 0) {
        return true;
    }

    return (substr($string, -$length) === $substring);
}
