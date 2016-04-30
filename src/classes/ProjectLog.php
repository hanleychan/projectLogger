<?php

class ProjectLog extends DatabaseObject
{
    public $id;
    public $minutes;
    public $userID;
    public $projectID;
    public $comment;
    public $date;
    public $username;
    public $totalMinutes;
    public $numLogs;

    protected static $tableName = 'projectlogs';
    protected static $dbFields = array('id', 'minutes', 'userID', 'projectID', 'comment', 'date');

    public static function isValidTime($hours, $minutes)
    {
        $hours = (int)$hours;
        $minutes = (int)$minutes;

        if ($hours === 24 && $minutes > 0) {
            return false;
        } elseif($hours < 0 || $minutes < 0) {
            return false;
        } elseif ($hours === 0 && $minutes === 0) {
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
    
    public static function calculateTotalMinutes($hours, $minutes)
    {
        return $minutes + ($hours * 60);
    }

    public static function formatTimeOutput($minutes)
    {
        $output = "";

        $hours = floor($minutes / 60);
        $output .= $hours;
        $output .= ($hours > 1) ? " hrs " : " hr ";

        $minutes = $minutes % 60;
        $output .= $minutes;
        $output .= ($minutes > 1) ? " mins" : " min";

        return $output;
    }

    
    public static function getTotalTimeByProjectName($db, $projectName)
    {
        $sql = "SELECT SUM(minutes) as totalMinutes FROM projectlogs ";
        $sql .= "INNER JOIN projects ON projectlogs.projectID = projects.id ";
        $sql .= "WHERE projectName = ? LIMIT 1";
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);
        if($result) {
            return (int)$result[0]->totalMinutes;
        } else {
            return false;
        }
    }

    public static function getTotalTimeByProjectNameAndUser($db, $projectName, $userID)
    {
        $sql = "SELECT SUM(minutes) as totalMinutes FROM projectlogs ";
        $sql .= "INNER JOIN projects ON projectlogs.projectID = projects.id ";
        $sql .= "WHERE projectName = ? AND userID = " . (int)$userID . " ";
        $sql .= "LIMIT 1";
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);
        if($result) {
            return (int)$result[0]->totalMinutes;
        } else {
            return false;
        }
    }

    public static function findLogsByProjectName($db, $projectName, $username = "", $limit="", $offset=0)
    {
        $sql = "SELECT projectlogs.id as id, username, date, comment, minutes, userID ";
        $sql .= "FROM projectlogs INNER JOIN users ON userID = users.id ";
        $sql .= "INNER JOIN projects ON projectID = projects.id ";
        $sql .= "WHERE projectName = ? ";
        
        if(!empty($username)) {
            $sql .= "AND username = ? ";
        }

        $sql .= "ORDER BY date DESC, id DESC";

        if(!empty($limit)) {
            $sql .= " LIMIT " . (int)$offset . ", " . (int)$limit;
        }

        $paramArray = array($projectName);

        if(!empty($username)) {
            array_push($paramArray, $username);
        }

        $results = self::findBySQL($db, $sql, $paramArray);
        if($results) {
            return $results;
        } else {
            return false;
        }
    }

    public static function getNumLogsByProjectName($db, $projectName, $username = "")
    {
        $sql = "SELECT COUNT(*) as numLogs ";
        $sql .= "FROM projectlogs INNER JOIN users ON userID = users.id ";
        $sql .= "INNER JOIN projects ON projectID = projects.id ";
        $sql .= "WHERE projectName = ? ";
        
        if(!empty($username)) {
            $sql .= "AND username = ? ";
        }

        $sql .= "LIMIT 1";

        $paramArray = array($projectName);

        if(!empty($username)) {
            array_push($paramArray, $username);
        }

        $results = self::findBySQL($db, $sql, $paramArray);
        if($results) {
            return (int)$results[0]->numLogs;
        } else {
            return 0;
        }
    }
}
