<?php

class User extends DatabaseObject
{
    public $id;
    public $username;
    public $password;

    protected static $tableName = 'admins';
    protected static $dbFields = array('id', 'username', 'password');
}
