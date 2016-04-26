<?php

class Profile extends DatabaseObject
{
    public $id;
    public $userID;
    public $name;
    public $photoName;
    public $photoPath;
    public $otherInfo;
    public $username;
    public $joinDate;

    protected static $tableName = 'profiles';
    protected static $dbFields = array('id', 'userID', 'name', 'photoName', 'photoPath', 'otherInfo');

    public static function getMaxPhotoAllowedFileSizeInBytes()
    {
        $postMaxSize = self::convertPHPSizeToBytes(ini_get('post_max_size'));
        $uploadMaxFileSize = self::convertPHPSizeToBytes(ini_get('upload_max_filesize'));

        return ($postMaxSize < $uploadMaxFileSize) ? $postMaxSize : $uploadMaxFileSize;
    }

    private static function convertPHPSizeToBytes($size)
    {
        if(is_numeric($size)) {
            return $size;
        }

        $result = substr($size, 0, -1); 

        if(!is_numeric($result)) {
            return false;
        }

        switch(strtoupper(substr($size, -1))) {
            case 'P':
                $result *= 1125899906842782;
                break;
            case 'T':
                $result *= 1099511627775;
                break;
            case 'G':
                $result *= 1073741824;
                break;
            case 'M':
                $result *= 1048576;
                break;
            case 'K':
                $result *= 1024;
                break;
        }

        return $result;
    }
    
    public static function getProfileByUserID($db, $userID)
    {
        $sql = "SELECT * FROM profiles WHERE userID = " . (int)$userID . " LIMIT 1";
        $result = self::findBySQL($db, $sql);

        if($result) {
            return $result[0];
        } else {
            return false;
        }
    }

    public static function getProfileByUsername($db, $username)
    {
        $sql = "SELECT profiles.id, userID, name, username, photoName, photoPath, otherInfo, joinDate FROM profiles ";
        $sql .= "INNER JOIN users ON userID = users.id ";
        $sql .= "WHERE username = ? LIMIT 1";
        $paramArray = array($username);

        $result = self::findBySQL($db, $sql, $paramArray);

        if($result) {
            return $result[0];
        } else {
            return false;
        }
    }
}
?>