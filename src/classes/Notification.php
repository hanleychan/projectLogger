<?php
class Notification extends DatabaseObject
{
    public $id;
    public $userID;
    public $notification;
    public $date;

    protected static $tableName = 'notifications'; 
    protected static $dbFields = array('id', 'userID', 'notification', 'date');

    public static function getNotifications($db, $userID)
    {
        $sql = "SELECT * FROM notifications WHERE userID = " . (int)$userID;
        $sql .= " ORDER BY id DESC";

        $result = self::findBySQL($db, $sql);

        if($result) {
            return $result;
        } else {
            return false;
        }
    }
}
