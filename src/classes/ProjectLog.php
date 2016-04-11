<?php

class ProjectLog extends DatabaseObject
{
    public $id;
    public $projectTime;
    public $userID;
    public $projectID;
    public $comment;
    public $date;
    public $username;

    protected static $tableName = 'projectlogs';
    protected static $dbFields = array('id', 'projectTime', 'userID', 'projectID', 'comment', 'date');

    public static function isValidTime($hours, $minutes)
    {
        $hours = (int)$hours;
        $minutes = (int)$minutes;

        if ($hours === 24 && $minutes > 0) {
            return false;
        } elseif($hours < 0 || $minutes < 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Formats date from mm/dd/yyyy format to yyyy-mm-dd format
     */
    public static function formatDateToSQL($date)
    {
        $date = trim($date);
        $month = substr($date, 0, 2);
        $day = substr($date, 3, 2);
        $year = substr($date, 6, 4);

        // check if date is valid
        if(strlen($date) != 10) {
            return false;
        } elseif ($date[2] !== '/' || $date[5] !== '/') {
            return false;
        } elseif (!checkdate($month, $day, $year)) {
            return false;
        } else {
            $formattedDate = date('Y-m-d', strtotime($date));
            return $formattedDate;
        }
    }

    /**
     * Formats date from yyyy-mm-dd format to mm/dd/yyyy format
     */
    public static function formatDateFromSQL($date)
    {
        $date = trim($date);
        $year = substr($date, 0, 4);
        $month = substr($date, 5, 2);
        $day = substr($date, 8, 2);

        // check if date is valid
        if(strlen($date) != 10) {
            return false;
        } elseif ($date[4] !== '-' || $date[7] !== '-') {
            return false;
        } elseif (!checkdate($month, $day, $year)) {
            return false;
        } else {
            $formattedDate = date('m/d/Y', strtotime($date));
            return $formattedDate;
        }
    }
    
    public static function findLogsByProjectName($db, $projectName)
    {
        $sql = "SELECT projectlogs.id as id, username, date, comment, projectTime, userID ";
        $sql .= "FROM projectlogs INNER JOIN users ON userID = users.id ";
        $sql .= "INNER JOIN projects ON projectID = projects.id ";
        $sql .= "WHERE projectName = ?";
        $paramArray = array($projectName);

        $results = self::findBySQL($db, $sql, $paramArray);
        if($results) {
            return $results;
        } else {
            return false;
        }
    }
}
