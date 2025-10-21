<?php
// src/Controllers/BaseController.php

require_once __DIR__ . '/../../config/config.php';

class BaseController
{
    protected $db;

    /**
     * The constructor now accepts the database connection.
     * This is the dependency injection pattern.
     *
     * @param mysqli $db_connection The database link
     */
    public function __construct($db_connection)
    {
        // Don't use global, use the provided argument
        $this->db = $db_connection;
    }
}