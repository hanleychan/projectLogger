<?php

class RequestJoinProject extends DatabaseObject
{
    public $id;
    public $userID;
    public $projectID;

    protected static $tableName = 'requestjoinproject';
    protected static $dbFields = array('id', 'userID', 'projectID');
}
