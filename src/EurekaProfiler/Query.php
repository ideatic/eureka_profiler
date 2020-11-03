<?php

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
        $this->query = &$this->name;
        $this->type = 'db';
    }

}