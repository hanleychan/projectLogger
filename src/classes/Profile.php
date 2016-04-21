<?php

class Profile extends DatabaseObject
{
    public $id;
    public $userID;
    public $name;
    public $photo;
    public $otherInfo;

    protected static $tableName = 'profiles';
    protected static $dbFields = array('id', 'userID', 'name', 'photo', 'otherInfo');
}
?>
