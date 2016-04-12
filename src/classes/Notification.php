<?php
class Notification extends DatabaseObject
{
    public $id;
    public $userID;
    public $notification;

    protected static $tableName = 'notifications'; 
    protected static $dbFields = array('id', 'userID', 'notification');
}
