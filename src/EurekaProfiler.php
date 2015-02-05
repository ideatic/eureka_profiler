<?php

/**
 * Tool to analyze and profile an application performance
 */
class EurekaProfiler
{

    /**
     * Current session data
     * @var EurekaProfiler_Session
     */
    private $_session;

    private $_enabled = true;

    private $_table = 'eureka_profiler';

    /**
     * DB Adapter used to query the database (optional)
     * @var EurekaProfiler_DB_Adapter
     */
    public $db_adapter;

    /**
     * URL of profiler assets
     * @var string
     */
    public $static_url = '/static/eureka_profiler/';

    public function __construct($start_time = null)
    {
        $this->_session = new EurekaProfiler_Session($start_time);
    }

    /**
     * Gets the current active profiler session
     * @return EurekaProfiler_Session
     */
    public function session()
    {
        return $this->_session;
    }

    /**
     * Ends the current session, gathering all the available environment data
     *
     * @param string                    $response   HTML response for the current request
     * @param EurekaProfiler_DB_Adapter $db_adapter DB Adapter used to query the database
     * @param boolean                   $store      Store current profile data in the database
     */
    public function finish($response = '', $store = false)
    {
        $this->disable();

        //Create events tree
        $event_tree = array();
        foreach ($this->_session->events as $event) {
            $closest = null;
            //Search event that starts before and ends after current
            foreach ($this->_session->events as $sibling) {
                if ($sibling != $event && $sibling->duration > 0 && $sibling->timemark < $event->timemark &&
                    $sibling->timemark + $sibling->duration > $event->timemark + $event->duration
                ) {

                    $closest = $sibling;
                }
            }

            if ($closest) {
                $closest->add_child($event);
            } else {
                $event_tree[] = $event;
            }
        }
        $this->_session->events = $event_tree;

        $this->_session->gather_data($response, $this->db_adapter);

        //Store session data
        if ($store) {
            if (!$this->db_adapter) {
                throw new RuntimeException('A DB adapter must be configured before storing profiler sessions');
            }

            $this->db_adapter->insert(
                $this->_table,
                array(
                    'date' => $this->_session->start,
                    'url'  => EurekaProfiler_Tools::current_url(),
                    'data' => base64_encode(gzencode(serialize($this->_session), 9))
                )
            );
        }
    }

    /**
     * Show the console for the current session
     */
    public function show_console($session = null, $show_at_bottom = true)
    {
        if (!$this->_session->duration) {
            $this->finish();
        }

        if (!isset($session)) {
            $session = $this->_session;
        }

        $static_url = $this->static_url;

        require 'templates/Console.php';
    }

    /**
     * Show a list of all store sessions
     */
    public function show_log()
    {
        if (!$this->db_adapter) {
            throw new RuntimeException('A DB adapter must be configured to read the stored profiler sessions');
        }

        if (isset($_REQUEST['show']) && is_numeric($_REQUEST['show'])) {
            $session_id = $_REQUEST['show'];
            $session_meta = $this->db_adapter->query("SELECT * FROM $this->_table WHERE id=$session_id");
            $session_meta = $session_meta[0];
            $session = unserialize(gzdecode(base64_decode($session_meta['data'])));
        } else {
            if (isset($_REQUEST['remove'])) {
                $session_id = $_REQUEST['remove'];
                if ($session_id == 'all') {
                    $this->db_adapter->query("TRUNCATE $this->_table");
                } elseif (is_numeric($session_id)) {
                    $this->db_adapter->query("DELETE FROM $this->_table WHERE id=$session_id");
                }
            }

            $offset = isset($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : 0;
            $per_page = isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 25;

            $sessions = $this->db_adapter->query("SELECT * FROM $this->_table ORDER BY date DESC LIMIT $offset,$per_page");

            $total = $this->db_adapter->query("SELECT COUNT(*) AS c FROM $this->_table");
            $total = $total[0]['c'];

        }


        require 'templates/Log.php';
    }

    /**
     * Allow the profiler to gather data and events
     */
    public function enable()
    {
        $this->_enabled = true;
    }

    /**
     * Disallow the profiler to gather data and events
     */
    public function disable()
    {
        $this->_enabled = false;
    }

    /**
     * Logs an event in the current section
     *
     * @param string|EurekaProfiler_Event $name
     * @param boolean|int                 $backtrace Enable or disable the backtract info for the event. If it's a number, it will represent the number of steps deleted
     *
     * @return EurekaProfiler_Event
     */
    public function log_event($name, $type = 'app', $data = null, $backtrace = true)
    {
        if (!$this->_enabled) {
            return false;
        }

        if ($name instanceof EurekaProfiler_Event) {
            $event = $name;
        } else {
            $event = new EurekaProfiler_Event($this->_session, $name);
            $event->type = $type;
            $event->data = $data;
        }
        if ($backtrace) {
            $event->backtrace = EurekaProfiler_Tools::backtrace_small(null, is_bool($backtrace) ? 2 : 2 + $backtrace);
        }

        $this->_session->events[] = $event;

        return $event;
    }

    /**
     * Log a database query
     *
     * @return EurekaProfiler_Event
     */
    public function log_query($query, $text = '', $duration = 0)
    {
        $log = new EurekaProfiler_Query($this->_session);
        $log->query = $query;
        $log->text = $text;
        $log->duration = $duration;

        return $this->log_event($log);
    }

}
