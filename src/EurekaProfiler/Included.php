<?php

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