<?php

class ProjectMember extends DatabaseObject
{
    public $id;
    public $userID;
    public $projectID;
    public $isAdmin;
    
    protected static $tableName = 'projectmembers';
    protected static $dbFields = array('id', 'userID', 'projectID', 'isAdmin');
}
