<?php

/**
 * General purpose functions used by the profiler
 */
abstract class EurekaProfiler_Tools
{

    public static function readable_interval($seconds, $granularity = 2, $allow_micro = true)
    {
        //Prepare units
        $units = array(
            31536000 => '%count% years',
            2592000  => '%count% months',
            604800   => '%count% weeks',
            86400    => '%count% days',
            3600     => '%count% h',
            60       => '%count% m',
            1        => '%count% s',
        );
        if ($allow_micro) {
            $units['1e-3'] = '%count% ms';
            $units['1e-6'] = '%count% µs';
            $units['1e-9'] = '%count% ns';
        }

        //Write result
        $output = '';
        foreach ($units as $value => $text) {
            $value = floatval($value);
            if ($seconds >= $value) {
                $amount = floor($seconds / $value);
                $output .= ($output ? ' ' : '') . str_replace('%count%', $amount, $text);
                $seconds -= $amount * $value;
                $granularity--;
            }

            if ($granularity == 0) {
                break;
            }
        }

        if (empty($output)) { //If the output is empty, use last unit to represent it
            $output = str_replace('%count%', 0, $text);
        }
        return $output;
    }

    public static function readable_number($number, $decimals = 0, $optimize_decimals = false)
    {
        $result = number_format($number, $decimals, '.', ',');

        if ($optimize_decimals) {
            $result = rtrim(rtrim($result, '0'), '.');
        }

        return $result;
    }

    public static function readable_size($size, $kilobyte = 1024, $format = '%size% %unit%')
    {
        if ($size < $kilobyte) {
            $unit = 'bytes';
        } else {
            $size = $size / $kilobyte;
            $units = array('KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
            foreach ($units as $unit) {
                if ($size > $kilobyte) {
                    $size = $size / $kilobyte;
                } else {
                    break;
                }
            }
        }

        return strtr(
            $format,
            array(
                '%size%' => self::readable_number($size, 2),
                '%unit%' => $unit
            )
        );
    }

    private static $_special_paths = array();

    /**
     * Sets special paths that will be replaced in the profiler. E.g.:
     *         APP_PATH => /home/project/www/
     */
    public static function set_special_paths(array $paths)
    {
        self::$_special_paths = $paths;
    }

    /**
     * Clean a path, replacing the special folders defined in the config.
     *
     * @param string $path
     * @param bool   $restore True for restore a cleared path to its original state
     *
     * @return string
     */
    public static function clean_path($path, $restore = false)
    {
        foreach (self::$_special_paths as $clean_path => $source_path) {
            if ($restore) {
                if (strpos($path, $clean_path) === 0) {
                    $path = $source_path . substr($path, strlen($clean_path));
                    break;
                }
            } else {
                if (strpos($path, $source_path) === 0) {
                    $path = $clean_path . substr($path, strlen($source_path));
                    break;
                }
            }
        }

        return str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
    }

    public static function current_url($include_query = true)
    {
        //Protocol        
        $protocol = strtolower($_SERVER['SERVER_PROTOCOL']);
        if (($dash = strpos($protocol, '/')) !== false) {
            $protocol = substr($protocol, 0, $dash);
        }
        if (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == 'on') {
            $protocol .= 's';
        }

        //Port
        $port = '';
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80) {
            $port = ':' . $_SERVER['SERVER_PORT'];
        }

        //Request
        if (isset($_SERVER['REQUEST_URI'])) {
            $request = $_SERVER['REQUEST_URI'];

            if (!$include_query && ($pos = strpos($request, '?')) !== false) {
                $request = substr($request, 0, $pos);
            }
        } else {
            if (isset($_SERVER['REDIRECT_URL'])) {
                $request = $_SERVER['REDIRECT_URL'];
            } else {
                $request = $_SERVER['PHP_SELF'];
            }

            if ($include_query) {
                $query = isset($_SERVER['REDIRECT_QUERY_STRING']) ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];
                if (!empty($query)) {
                    $request .= '?' . $query;
                }
            }
        }

        //Generate full URL
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $port . $request;
    }

    public static function client_ip()
    {
        foreach (array(
                     'HTTP_CLIENT_IP',
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'REMOTE_ADDR'
                 ) as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !==
                        false
                    ) {
                        return $ip;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Renders an abbreviated version of the backtrace
     *
     * @param array $ call stack trace to be analyzed, if not use this parameter indicates the call stack before the function
     *
     * @return string
     */
    public static function backtrace_small(array $trace = null, $ignore_last = 2)
    {
        if ($trace === null) {
            $trace = debug_backtrace();

            //Remove last locations
            $trace = array_slice($trace, $ignore_last);
        }

        $output = array();
        foreach ($trace as $i => $step) {

            //Get data from the current step
            foreach (array('class', 'type', 'function', 'file', 'line') as $param) {
                $$param = isset($step[$param]) ? $step[$param] : '';
            }

            $extra = '';
            if (in_array($function, array('include', 'include_one', 'require', 'require_once'))) {
                $extra = "({$step['args'][0]})";
            }

            //Generate HTML
            $location = htmlspecialchars(self::clean_path($file) . ":$line $extra");
            $output[] = "<abbr title=\"$location\">{$class}{$type}{$function}</abbr>";
        }

        return implode(' &rarr; ', array_reverse($output));
    }

}