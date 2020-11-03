<?php

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