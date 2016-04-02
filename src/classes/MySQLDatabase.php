<?php
class MySQLDatabase {
    private $connection;

    /**
     * Setup database
     */
    public function __construct($host, $dbName, $port, $user, $password) {
        $this->openConnection($host, $dbName, $port, $user,$password);
    }

    /**
     * Opens the the connection to the MySql database
     */
    public function openConnection($host, $dbName, $port, $user, $password) {
        try {
            $this->connection = new PDO("mysql:host=" . $host . ";dbname=" . $dbName . ";port=" . $port, $user, $password );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->exec("SET NAMES 'utf8'");
        }
        catch(Exception $e) {
            die("Database connection failed");
        }
    }

    /**
     * Closes the database connection
     */
    public function closeConnection() {
        $this->connection = null;
    }

    /**
     * Executes a query and returns the result
     */
    public function query($sql) {
        try {
            $results = $this->connection->query($sql);
        }
        catch(Exception $e) {
            die("Database query failed.");
        }

        return $results;
    }

    /**
     * Prepares a sql statement for execution
     */
    public function prepare($sql) {
        try {
            $results = $this->connection->prepare($sql);
        }
        catch(Exception $e) {
            die("Database query failed");
        }

        return $results;
    }

    /**
     * Returns the id of the last inserted row
     */
    public function lastInsertId() {
        try {
            $lastInsertId = $this->connection->lastInsertId();
        }
        catch(Exception $e) {
            die("Error retrieving from database.");
        }

        return $lastInsertId;
    }
}

?>
