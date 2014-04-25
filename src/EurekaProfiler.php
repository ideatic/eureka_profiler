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
     */
    public function finish($response = '', $db_adapter = null)
    {
        $this->disable();

        //Create events tree
        $active_events = array();
        $event_tree = array();
        foreach ($this->_session->events as $event) {
            $closest = null;
            //Buscar el evento que comienza antes y termina después del actual
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

        $this->_session->gather_data($response, $db_adapter);
    }

    /**
     * Show the console for the current session
     */
    public function show_console($static_url = '/static/eureka_profiler/')
    {
        if (!$this->_session->duration) {
            $this->finish();
        }

        $session = $this->_session;

        require 'templates/Console.php';
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
     * @param string|ProfilerEvent $name
     * @param boolean|int          $backtrace Enable or disable the backtract info for the event. If it's a number, it will represent the number of steps deleted
     *
     * @return ProfilerEvent
     */
    public function log_event($name, $type = 'app', $data = null, $backtrace = true)
    {
        if (!$this->_enabled) {
            return false;
        }

        if ($name instanceof ProfilerEvent) {
            $event = $name;
        } else {
            $event = new ProfilerEvent($this->_session, $name);
            $event->type = $type;
            $event->data = $data;
        }
        if ($backtrace) {
            $event->backtrace = ProfilerTools::backtrace_small(null, is_bool($backtrace) ? 2 : 2 + $backtrace);
        }

        $this->_session->events[] = $event;

        return $event;
    }

    /**
     * Log a database query
     */
    public function log_query($query, $text = '', $duration = 0)
    {
        $log = new ProfilerQuery($this->_session);
        $log->query = $query;
        $log->text = $text;
        $log->duration = $duration;

        return $this->log_event($log);
    }

}
