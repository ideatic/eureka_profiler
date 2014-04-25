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
     * @param type $type
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

    public function total_query_time()
    {
        $total = 0;
        foreach ($this->events_of_type('db') as $event) {
            $total += $event->duration;
        }
        return $total;
    }

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
        $this->url = ProfilerTools::current_url();
        $this->client_ip = ProfilerTools::client_ip();
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
            $this->loaded_files[] = new EurekaProfiler_Included(ProfilerTools::clean_path($file), filesize($file));
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

class EurekaProfiler_Event
{

    /**
     * Event name
     * @var string
     */
    public $name;

    /**
     * Event type or category
     * @var string
     */
    public $type;

    /**
     * Seconds from the current session start till the current event
     * @var float
     */
    public $timemark;

    /**
     * Event duration (in seconds)
     * @var float
     */
    public $duration;

    /**
     * @var EurekaProfiler_Event
     */
    public $parent;

    /**
     * @var mixed
     */
    public $data;

    /**
     *
     * @var EurekaProfiler_Event[]
     */
    public $children = array();

    /**
     *
     * @var string
     */
    public $backtrace;
    private $_finish_callback;

    /**
     * @var EurekaProfiler_Session
     */
    private $_session;

    public function __construct(EurekaProfiler_Session $session, $name = '')
    {
        $this->timemark = microtime(true) - $session->start;
        $this->_session = $session;
        $this->name = $name;
    }

    public function add_child(EurekaProfiler_Event $event)
    {
        $this->children[] = $event;
        $event->parent = $this;
    }

    public function finish($data = null)
    {
        $this->duration = microtime(true) - $this->_session->start - $this->timemark;
        if (isset($data)) {
            $this->data = $data;
        }

        if ($this->_finish_callback) {
            call_user_func($this->_finish_callback, $this);
        }
    }

    public function on_finish($callback)
    {
        $this->_finish_callback = $callback;
    }

}

class EurekaProfiler_Query extends EurekaProfiler_Event
{

    /**
     * Real executed query
     * @var string
     */
    public $query;

    /**
     * Executed query, with placeholders if available
     * @var string
     */
    public $text;
    public $explain;

    public function __construct(EurekaProfiler_Session $session)
    {
        parent::__construct($session);
        $this->query = & $this->name;
        $this->type = 'db';
    }

}

class EurekaProfiler_Included
{

    public $path;
    public $size;

    public function __construct($path, $size)
    {
        $this->path = $path;
        $this->size = $size;
    }

}