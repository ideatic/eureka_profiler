<?php

/**
 * Represents a profiler session and all its associated data
 */
class EurekaProfiler_Session
{

    /**
     * Timemark representing the current session start
     * @var float
     */
    public $start;

    /**
     * Duration, in seconds, of the current session
     * @var float
     */
    public $duration;

    /**
     * Events logged in this session
     * @var EurekaProfiler_Event[]
     */
    public $events = array();
    public $status;
    public $response;
    public $url;
    public $client_ip;
    public $user_agent;
    public $memory_used;
    public $memory_limit;
    public $max_execution_time;
    public $get_data;
    public $post_data;
    public $cookies;
    public $request_headers;
    public $response_headers;

    /**
     * @var EurekaProfiler_Included[]
     */
    public $loaded_files = array();

    public function __construct($start_time = null)
    {
        $this->start = isset($start_time) ? $start_time : microtime(true);
    }

    /**
     * Gets all declared events
     * @return EurekaProfiler_Event[]
     */
    public function all_events()
    {
        $result = array();
        foreach ($this->events as $e) {
            $this->_all_childs($result, $e);
        }
        return $result;
    }

    private function _all_childs(&$result, $event)
    {
        $result[] = $event;
        foreach ($event->children as $e) {
            $this->_all_childs($result, $e);
        }
    }

    /**
     * Gets events of the indicated type
     *
     * @param string $type
     *
     * @return EurekaProfiler_Event[]
     */
    public function events_of_type($type)
    {
        $result = array();
        foreach ($this->all_events() as $event) {
            if ($event->type == $type) {
                $result[] = $event;
            }
        }
        return $result;
    }

    /**
     * Get the total time consumed by database query events
     * @return float
     */
    public function total_query_time()
    {
        $total = 0;
        foreach ($this->events_of_type('db') as $event) {
            $total += $event->duration;
        }
        return $total;
    }

    /**
     * Get the total size, in bytes, of the included PHP files
     * @return int
     */
    public function total_included_size()
    {

        $total = 0;
        foreach ($this->loaded_files as $file) {
            $total += $file->size;
        }
        return $total;
    }

    /**
     * Ends the current session, gathering all the available environment data
     */
    public function gather_data($response = '', EurekaProfiler_DB_Adapter $db_adapter = null)
    {
        $this->duration = microtime(true) - $this->start;

        //Request data
        $this->url = EurekaProfiler_Tools::current_url();
        $this->client_ip = EurekaProfiler_Tools::client_ip();
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'];

        $this->get_data = $_GET;
        $this->post_data = $_POST;
        $this->cookies = $_COOKIE;

        if (function_exists('getallheaders')) {
            $this->request_headers = getallheaders();
        } else {
            $this->request_headers = array();

            foreach ($_SERVER as $key => $val) {
                $extra_headers = array('CONTENT_TYPE', 'CONTENT_LENGTH');
                if (strpos($key, 'HTTP_') === 0 || in_array($key, $extra_headers)) {
                    $name = str_replace(array('HTTP_', '_'), array('', '-'), $key);
                    $this->request_headers[$name] = $val;
                }
            }
        }
        $this->request_headers = array_change_key_case($this->request_headers, CASE_UPPER);

        //Response data
        $this->status=function_exists('http_response_code')? http_response_code():200;
        $this->response = $response;
        $this->response_headers = array();
        if (function_exists('apache_response_headers')) {
            $this->response_headers += apache_response_headers();
        }
        foreach (headers_list() as $header) {
            list($name, $value) = explode(':', $header, 2);
            $this->response_headers[$name] = trim($value);
        }

        //Runtime data
        $this->memory_limit = ini_get('memory_limit');
        $this->memory_used = memory_get_peak_usage(true);
        $this->max_execution_time = ini_get('max_execution_time');

        //Included files
        $files = get_included_files();
        foreach ($files as $file) {
            $this->loaded_files[] = new EurekaProfiler_Included(EurekaProfiler_Tools::clean_path($file), filesize($file));
        }

        //Explain queries
        if (isset($db_adapter)) {
            foreach ($this->events_of_type('db') as $query) {
                if (stripos($query->query, 'SELECT') === 0) {
                    $query->explain = $db_adapter->first_row("EXPLAIN $query->query");
                } else {
                    $query->explain = false;
                }
            }
        }
    }

}

