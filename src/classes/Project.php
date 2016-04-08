<?php

class Project extends DatabaseObject
{
    public $id;
    public $projectName;
    public $ownerID;
    public $ownerName;
    public $userID;
    public $isAdmin;
    public $username;
    public $date;
    public $comment;
    public $projectTime;

    const NAME_MAX_LENGTH = 20;
    const NAME_MIN_LENGTH = 1; 
    protected static $tableName = 'projects';
    protected static $dbFields = array('id', 'projectName', 'ownerID');

    /**
     * Returns whether a project name is in a valid format
     */
    public static function isValidProjectName($projectName)
    {
        if(preg_match('/^[a-zA-Z0-9]{' . self::NAME_MIN_LENGTH . ',' . self::NAME_MAX_LENGTH . '}$/', $projectName)) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Returns whether a project name already exists
     */
    public static function doesProjectExist($db, $projectName)
    {
        $sql = "SELECT * FROM projects WHERE projectName = ? LIMIT 1";
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);
        if($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns all projects for a specified userID
     */
    public static function findProjectsByUser($db, $userID)
    {
        $sql = "SELECT projects.id as id, projectName, ownerID, userID, isAdmin ";
        $sql .= "FROM projects INNER JOIN projectmembers ON projects.id = projectmembers.projectID ";
        $sql .= "WHERE userID = " . (int)$userID;
        $result = self::findBySQL($db, $sql);

        if($result) {
            return $result;
        } else {
            return false;
        }
    }


    /**
     * Returns a project for a specified project name
     */
    public static function findProjectByName($db, $projectName)
    {
        $sql = "SELECT projects.id as id, projectName, ownerID, username as ownerName FROM projects INNER JOIN users ON ownerID = users.id WHERE projectName = ? LIMIT 1";
        $paramArray = array($projectName);

        $result = self::findBySQL($db, $sql, $paramArray);
        if($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns a project by both project name and userID 
     */
    public static function findProjectByNameAndUser($db, $projectName, $userID)
    {
        $sql = "SELECT projects.id as id, projectName, ownerID, userID, isAdmin, username as ownerName ";
        $sql .= "FROM projects INNER JOIN projectmembers ON projects.id = projectmembers.projectID ";
        $sql .= "INNER JOIN users ON projects.ownerID = users.id ";
        $sql .= "WHERE projectName = ? AND userID = " . (int)$userID . " LIMIT 1";

        $paramArray = array($projectName);
        $result = self::findBySQL($db, $sql, $paramArray);
        if($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns all project members for a project from a specified project name
     */
    public static function findProjectMembersByProjectName($db, $projectName)
    {
        $sql = "SELECT ownerID, userID, username  ";
        $sql .= "FROM projectmembers INNER JOIN projects ON projects.id = projectID ";
        $sql .= "INNER JOIN users ON userID = users.id ";
        $sql .= "WHERE projectName = ?";
        $paramArray = array($projectName);
        $results = self::findBySQL($db, $sql, $paramArray);
        if($results) {
            return $results;
        } else {
            return false;
        }
    }

    public static function findLogsByProjectName($db, $projectName)
    {
        $sql = "SELECT username, date, comment, projectTime, userID ";
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

