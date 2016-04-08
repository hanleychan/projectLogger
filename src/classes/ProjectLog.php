<?php

class ProjectLog extends DatabaseObject
{
    public $id;
    public $projectTime;
    public $userID;
    public $projectID;
    public $comment;

    protected static $tableName = 'projectlogs';
    protected static $dbFields = array('id', 'projectTime', 'userID', 'projectID', 'comment');

    public static function isValidTime($hours, $minutes)
    {
        $hours = (int)$hours;
        $minutes = (int)$minutes;

        if($hours < 0 || $hours > 24) {
            return false;
        } elseif ($hours === 24 && $minutes > 0) {
            return false;
        } else {
            return true;
        }
    }


    
}
