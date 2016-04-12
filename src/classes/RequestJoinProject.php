<?php

class RequestJoinProject extends DatabaseObject
{
    public $id;
    public $userID;
    public $projectID;

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
}
