<?php

class ProjectMember extends DatabaseObject
{
    public $id;
    public $userID;
    public $projectID;
    public $isAdmin;

    protected static $tableName = 'projectmembers';
    protected static $dbFields = array('id', 'userID', 'projectID', 'isAdmin');


    public static function isProjectMember($db, $projectID, $userID)
    {
        $sql = "SELECT * FROM projectmembers ";
        $sql .= "WHERE projectID = " . (int)$projectID . " ";
        $sql .= "AND userID = " . (int)$userID . " ";
        $sql .= "LIMIT 1";
        
        $result = self::findBySQL($db, $sql);

        if($result) {
            return true;
        } else {
            return false;
        }
    }
}
