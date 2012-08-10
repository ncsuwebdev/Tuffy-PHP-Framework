<?php

/**
 * This class contains utilities that are mostly internal to Tuffy. (But
 * they are still documented, so you can use them too.)
 *
 * @author Matthew Frazier <mlfrazie@ncsu.edu>
 */
class Tuffy_Util {
    /**
     * Builds a query string out of an array of data. It uses multiple
     * key/value pairs to represent arrays.
     *
     * @param array $data The data to put in the query string.
     * @param boolean $phpArrays Whether to append `[]` to the field name
     * if the value is an array (default is FALSE).
     */
    public static function buildQuery ($data, $phpArrays = FALSE) {
        $pairs = array();
        foreach ($data as $name => $value) {
            if (is_array($value)) {
                $name = $phpArrays
                      ? rawurlencode($name) . '[]'
                      : rawurlencode($name);
                foreach ($value as $item) {
                    $pairs[] = $name . '=' . rawurlencode($item);
                }
            } else {
                $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
            }
        }
        return implode('&', $pairs);
    }

    /**
     * Takes a $path, which can be either relative or absolute. If relative,
     * it joins it to $base.
     *
     * @param string $path The path to interpret (either relative or
     * absolute).
     * @param string $base The base path, for if the path is relative.
     * Should have a trailing /. (Defaults to TUFFY_APP_PATH.)
     * @return $path as an absolute path.
     */
    public static function interpretPath ($path, $base = NULL) {
        // Regex posted by mindplay.dk on:
        // http://stackoverflow.com/questions/7392274
        if (preg_match('/^(?:\\/|\\\\|\w\:\\\\).*$/', $path) === FALSE) {
            return ($base === NULL ? TUFFY_APP_PATH : $base) . $path;
        } else {
            return $path;
        }
    }

    /**
     * Requires a file, and returns an array of all the variables that it
     * defined.
     *
     * WARNING: This is not safe to run on user input. This is barely even
     * safe enough to run on *developer* input.
     *
     * @param string $filename The name of the file to run. Ensure that it
     * exists before calling loadFile.
     * @return All the nonglobal variables defined in the file.
     */
    public static function loadVariables ($filename) {
        ob_start();     // in case someone forgets a <?php
        require($filename);
        ob_end_clean();
        $vars = get_defined_vars();
        if (array_key_exists('filename', $vars)) unset($vars['filename']);
        return $vars;
    }
}

