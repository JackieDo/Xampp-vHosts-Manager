<?php

if (! function_exists('file_lines')) {
    /**
     * Reads entire file into an array
     *
     * @param string  $filename Path to the file.
     * @param integer $flags    Same as the `flags` parameter of the `file` function.
     *
     * @return array|false      Returns the file in an array. Each element of the array
     *                          corresponds to a line in the file, with the newline still
     *                          attached. Upon failure, file_lines() returns false.
     */
    function file_lines($filename, $flags = 0) {
        return file($filename, $flags);
    }
}

if (! function_exists('line_exists')) {
    /**
     * Check if file exists for a specific line, excluding blank lines
     *
     * @param string $needle   The searched value.
     * @param string $filename Path to the file.
     *
     * @return boolean         Returns true if needle is found in the file, false otherwise.
     */
    function line_exists($needle, $filename) {
        return in_array($needle, file_lines($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }
}

if (! function_exists('line_preg_match')) {
    /**
     * Perform a regular expression match for earch line in the file
     *
     * @param string  $pattern  The pattern to search for, as a string.
     * @param string  $filename Path to the file.
     * @param array   &$matches If this parameter is provided, then it is filled with the results
     *                          of search. `$matches[0]` will contain the text that matched the full
     *                          pattern, `$matches[1]` will have the text that matched the first
     *                          captured parenthesized subpattern, and so on.
     * @param integer $flags    Same as the `flags` parameter of the `preg_match` function.
     * @param integer $offset   Same as the `offset` parameter of the `preg_match` function.
     *
     * @return boolean          Returns true if the pattern matches anny line, false if it does not.
     */
    function line_preg_match($pattern, $filename, &$matches = null, $flags = 0, $offset = 0) {
        $lines = file_lines($filename, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            $matches   = null;
            $pregmatch = preg_match($pattern, $line, $matches, $flags, $offset);

            if ($pregmatch) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('create_ini_file')) {
    /**
     * Create ini file
     *
     * @param  string  $filename         The path to file want to create.
     * @param  array   $data             The content want to save.
     * @param  boolean $process_sessions Use session names in data.
     *
     * @return boolean
     */
    function create_ini_file($filename, $data = [], $process_sessions = false) {
        $content = '';

        if ((bool) $process_sessions) {
            foreach ($data as $section => $values) {
                $content .= PHP_EOL . '[' . $section. ']' . PHP_EOL;

                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        for ($i = 0; $i < count($value); $i++) {
                            $content .= $key . '[] = "' . $value[$i] . '"' . PHP_EOL;
                        }
                    } else if (empty($value)) {
                        $content .= $key . ' = ' . PHP_EOL;
                    } else {
                        $content .= $key . ' = "' . str_replace('"', '\"', $value) . '"' . PHP_EOL;
                    }
                }
            }
        } else {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    for ($i = 0; $i < count($value); $i++) {
                        $content .= $key . '[] = "' . $value[$i] . '"' . PHP_EOL;
                    }
                } else if (empty($value)) {
                    $content .= $key . ' = ' . PHP_EOL;
                } else {
                    $content .= $key . ' = "' . str_replace('"', '\"', $value) . '"' . PHP_EOL;
                }
            }
        }

        return file_put_contents($filename, ltrim($content));
    }
}

if (! function_exists('clean_dir')) {
    /**
     * Delete all files and subfolders in the specific directory.
     *
     * @param  string  $dirPath The path to directory want to clear.
     *
     * @return boolean          Whether the removal was successful or not
     */
    function clean_dir($dirPath) {
        if (! $handle = opendir($dirPath)) {
            return false;
        }

        while($item = readdir($handle)) {
            if (($item != '.') && ($item != '..')) {
                $realpath = realpath($dirPath . DIRECTORY_SEPARATOR . $item);

                if (is_dir($realpath)) {
                    // Recursively calling custom copy function for sub directory
                    if (! undir($realpath)) {
                        return false;
                    }
                } else {
                    $removed = unlink($realpath);

                    if (! $removed) {
                        return false;
                    }

                    unset($removed);
                }
            }
        }

        closedir($handle);

        return true;
    }
}

if (! function_exists('undir')) {
    /**
     * Remove entire directory.
     *
     * @param  string  $dirPath The path to directory want to remove.
     *
     * @return boolean          Whether the removal was successful or not
     */
    function undir($dirPath) {
        if (! clean_dir($dirPath)) {
            return false;
        }

        return rmdir($dirPath);
    }
}

if (! function_exists('maybe_phpdir')) {
    /**
     * Determine if the directory contains the file `php.exe` or not.
     *
     * @param  string  $dirPath The path to directory want to check.
     *
     * @return boolean
     */
    function maybe_phpdir($dirPath) {
        return (is_dir($dirPath)) && (is_file($dirPath . '\php.exe'));
    }
}

if (! function_exists('maybe_xamppdir')) {
    /**
     * Determine if the directory contains the file `xampp-control.exe` or not.
     *
     * @param  string  $dirPath The path to directory want to check.
     *
     * @return boolean
     */
    function maybe_xamppdir($dirPath) {
        return (is_dir($dirPath)) && (is_file($dirPath . '\xampp-control.exe'));
    }
}

if (! function_exists('maybe_apachedir')) {
    /**
     * Determine if the directory may be an "apache" directory.
     *
     * @param  string  $dirPath The path to directory want to check.
     *
     * @return boolean
     */
    function maybe_apachedir($dirPath) {
        return (is_dir($dirPath)) && (is_file($dirPath . '\bin\httpd.exe'));
    }
}

if (! function_exists('maybe_path')) {
    /**
     * Determines whether a string can be a path.
     *
     * @param  string  $string The input string.
     *
     * @return boolean
     */
    function maybe_path($string) {
        return (bool) preg_match('/^[^\"\<\>\?\*\|]+$/', $string);
    }
}

if (! function_exists('winstyle_path')) {
    /**
     * Convert paths to Windows style.
     *
     * @param  string $path The input path.
     *
     * @return string
     */
    function winstyle_path($path) {
        return str_replace('/', '\\', $path);
    }
}

if (! function_exists('unixstyle_path')) {
    /**
     * Convert paths to Unix style.
     *
     * @param  string $path The input path.
     *
     * @return string
     */
    function unixstyle_path($path) {
        return str_replace('\\', '/', $path);
    }
}

if (! function_exists('osstyle_path')) {
    /**
     * Convert paths to current OS style.
     *
     * @param  string $path The input path.
     *
     * @return string
     */
    function osstyle_path($path) {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

if (! function_exists('absolute_path')) {
    /**
     * Return absolute path from input path.
     * This function is an alternative to realpath() function for non-existent paths.
     *
     * @param  string $path      The input path.
     * @param  string $separator The directory separator wants to use in the results.
     *
     * @return string
     */
    function absolute_path($path, $separator = DIRECTORY_SEPARATOR) {
        // Normalize directory separators
        $path = str_replace(['/', '\\'], $separator, $path);

        // Store root part of path
        $root = null;
        while (is_null($root)) {
            // Check if path start with a separator (UNIX)
            if (substr($path, 0, 1) === $separator) {
                $root = $separator;
                $path = substr($path, 1);
                break;
            }

            // Check if path start with drive letter (WINDOWS)
            preg_match('/^[a-z]:/i', $path, $matches);
            if (isset($matches[0])) {
                $root = $matches[0] . $separator;
                $path = substr($path, 2);
                break;
            }

            $path = getcwd() . $separator . $path;
        }

        // Get and filter empty sub paths
        $subPaths = array_filter(explode($separator, $path), 'strlen');

        $absolutes = [];
        foreach ($subPaths as $subPath) {
            if ('.' === $subPath) {
                continue;
            }

            if ('..' === $subPath) {
                array_pop($absolutes);
                continue;
            }

            $absolutes[] = $subPath;
        }

        return $root . implode($separator, $absolutes);
    }
}

if (! function_exists('relative_path')) {
    /**
     * Return relative path from source directory to destination
     *
     * @param  string $from      The path of source directory.
     * @param  string $to        The path of file or directory to be compare.
     * @param  string $separator The directory separator wants to use in the results.
     *
     * @return string
     */
    function relative_path($from, $to, $separator = DIRECTORY_SEPARATOR) {
        $fromParts  = explode($separator, absolute_path($from, $separator));
        $toParts    = explode($separator, absolute_path($to, $separator));
        $diffFromTo = array_diff($fromParts, $toParts);
        $diffToFrom = array_diff($toParts, $fromParts);

        if ($diffToFrom === $toParts) {
            return implode($separator, $toParts);
        }

        return str_repeat('..' . $separator, count($diffFromTo)) . implode($separator, $diffToFrom);
    }
}
