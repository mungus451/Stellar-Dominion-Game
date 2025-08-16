<?php

//namespace App\Controllers;

require_once __DIR__ . '/../../config/config.php';

class BaseController
{
    protected $db;

    public function __construct()
    {
        global $link; // Access the global database connection
        $this->db = $link;
    }
}
