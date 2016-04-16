<?php

class RequestJoinProject extends DatabaseObject
{
    public $id;
    public $userID;
    public $projectID;
    public $username;
    public $projectName;

    protected static $tableName = 'requestjoinproject';
    protected static $dbFields = array('id', 'userID', 'projectID');

    public static function doesRequestExistByProjectName($db, $projectName, $userID)
    {
        $sql = "SELECT * FROM requestjoinproject INNER JOIN projects ";
        $sql .= "ON projectID = projects.id ";
        $sql .= "WHERE projectName = ? AND  userID = " . (int)$userID . " ";
        $sql .= "LIMIT 1";
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);

        if($result) {
            return true;
        } else {
            return false;
        }
    }

    public static function getRequestByProjectNameAndUsername($db, $projectName, $username)
    {
        $sql = "SELECT requestjoinproject.id as id, userID, projectID FROM requestjoinproject ";
        $sql .= "INNER JOIN projects ON projectID = projects.id ";
        $sql .= "INNER JOIN users ON userID = users.id ";
        $sql .= "WHERE projectName = ? AND username = ? ";
        $sql .= "LIMIT 1";
        $paramArray = array($projectName, $username);

        $result = self::findBySQL($db, $sql, $paramArray);

        if($result) {
            return $result[0];
        } else {
            return false;
        }

    }

    public static function getRequestsByProjectName($db, $projectName)
    {
        $sql = "SELECT requestjoinproject.id as id, userID, projectID, username ";
        $sql .= "FROM requestjoinproject INNER JOIN projects ON projectID = projects.id ";
        $sql .= "INNER JOIN users ON userID = users.id ";
        $sql .= "WHERE projectName = ?";
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);

        if($result) {
            return $result;
        } else {
            return false;
        }
    }

    public static function getAllRequestsForOwner($db, $userID)
    {
        $sql = "SELECT projectName ";
        $sql .= "FROM requestjoinproject INNER JOIN projects ON projectID = projects.id ";
        $sql .= "WHERE ownerID = " . (int)$userID;
        $result = self::findBySQL($db, $sql);

        if($result) {
            return $result;
        } else {
            return false;
        }
    }

}
