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

    /**
     * Returns a project join request for a specified project name
     */
    public static function getRequestByProjectName($db, $projectName, $userID)
    {
        $sql = 'SELECT requestjoinproject.id as id, userID, projectID ';
        $sql .= 'FROM requestjoinproject INNER JOIN projects ';
        $sql .= 'ON projectID = projects.id ';
        $sql .= 'WHERE projectName = ? AND  userID = '.(int) $userID.' ';
        $sql .= 'LIMIT 1';
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);

        if ($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns a project join request for a specified project name and username
     */
    public static function getRequestByProjectNameAndUsername($db, $projectName, $username)
    {
        $sql = 'SELECT requestjoinproject.id as id, userID, projectID FROM requestjoinproject ';
        $sql .= 'INNER JOIN projects ON projectID = projects.id ';
        $sql .= 'INNER JOIN users ON userID = users.id ';
        $sql .= 'WHERE projectName = ? AND username = ? ';
        $sql .= 'LIMIT 1';
        $paramArray = array($projectName, $username);

        $result = self::findBySQL($db, $sql, $paramArray);

        if ($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns all project join requests for a specified project name
     */
    public static function getRequestsByProjectName($db, $projectName)
    {
        $sql = 'SELECT requestjoinproject.id as id, userID, projectID, username ';
        $sql .= 'FROM requestjoinproject INNER JOIN projects ON projectID = projects.id ';
        $sql .= 'INNER JOIN users ON userID = users.id ';
        $sql .= 'WHERE projectName = ?';
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Returns all projects with pending requests for a user where they are the owner
     */
    public static function getProjectsWithPendingRequestsForOwner($db, $userID)
    {
        $sql = 'SELECT DISTINCT projectName ';
        $sql .= 'FROM requestjoinproject INNER JOIN projects ON projectID = projects.id ';
        $sql .= 'WHERE ownerID = '.(int) $userID;
        $result = self::findBySQL($db, $sql);

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Returns all project join requests for a user
     */
    public static function getAllRequestsForUser($db, $userID)
    {
        $sql = 'SELECT projectName ';
        $sql .= 'FROM requestjoinproject INNER JOIN projects ON projectID = projects.id ';
        $sql .= 'WHERE userID = '.(int) $userID;
        $result = self::findBySQL($db, $sql);

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }
}
