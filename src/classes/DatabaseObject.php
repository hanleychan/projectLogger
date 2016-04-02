<?php

class DatabaseObject
{
    private $db;

    /**
     * Set database.
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Returns the total number of entries in the table.
     */
    public static function getTotalEntries($db)
    {
        $sql = 'SELECT count(*) FROM '.static::$tableName.' LIMIT 1';
        $result = $db->query($sql);

        return (int) $result->fetch()[0];
    }

    /**
     * Queries the database and returns all entries in the table.
     */
    public static function findAll($db)
    {
        $sql = 'SELECT * FROM '.static::$tableName;
        $results = static::findBySQL($db, $sql);

        return $results;
    }

    /**
     * Queries the database and returns data for the specified id value.
     */
    public static function findById($db, $id = '')
    {
        if (!empty($id)) {
            $id = intval($id);
            $sql = 'SELECT * FROM '.static::$tableName." WHERE id={$id} LIMIT 1";
            $results = static::findBySQL($db, $sql);

            return $results[0];
        }
    }

    /**
     * Queries the database and returns all data for a specified query string.
     */
    public static function findBySQL($db, $sql = '', $paramArray = array())
    {
        if (!empty($sql)) {
            $object_array = array();
            try {
                $results = $db->prepare($sql);
                $results->execute($paramArray);
                while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
                    $object_array[] = static::instantiate($db, $row);
                }

                return $object_array;
            } catch (Exception $e) {
                die($e->getMessage());
            }
        } else {
            return false;
        }
    }

    /**
     * Creates and returns a new object with the specified array containing properties and values.
     */
    private static function instantiate($db, $record)
    {
        $object = new static($db);

        foreach ($record as $attribute => $value) {
            if (property_exists($object, $attribute)) {
                $object->$attribute = $value;
            }
        }

        return $object;
    }

    /**
     * Calls the correct method to either update or create a new database entry.
     */
    public function save($updateAttributes = '')
    {
        if (isset($this->id)) {
            $this->update($updateAttributes);
        } else {
            $this->create();
        }
    }

    /**
     * Updates an entry in the database.
     */
    private function update($updateAttributes)
    {
        $attributeError = false;

        if (is_array($updateAttributes)) {
            foreach ($updateAttributes as $attribute) {
                if (!property_exists($this, $attribute)) {
                    $attributeError = true;
                    break;
                }
            }

            if ($attributeError === true) {
                $attributes = static::$dbFields;
            } else {
                $attributes = $updateAttributes;
            }
        } else {
            $attributes = static::$dbFields;
        }

        $attributePairs = array();

        foreach ($attributes as $attribute) {
            $attributePairs[] = $attribute.' = ?';
        }

        try {
            $sql = 'UPDATE '.static::$tableName.' SET ';

            $sql .= implode(', ', $attributePairs);
            $sql .= ' WHERE id = ?';

            $results = $this->db->prepare($sql);
            for ($ii = 1;$ii <= count($attributes);++$ii) {
                $attribute = $attributes[$ii - 1];
                $results->bindParam($ii, $this->$attribute);
            }
            $results->bindParam((count($attributes) + 1), $this->id);

            $results->execute();
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Creates a new entry into the database.
     */
    private function create()
    {
        $attributes = static::$dbFields;

        try {
            $sql = 'INSERT INTO '.static::$tableName.' (';
            $sql .= implode(', ', $attributes);
            $sql .= ') VALUES (';
            for ($ii = 0;$ii < count($attributes);++$ii) {
                $sql .= '?';
                if ($ii !== (count($attributes) - 1)) {
                    $sql .= ', ';
                }
            }
            $sql .= ')';

            $results = $this->db->prepare($sql);

            for ($ii = 1;$ii <= count($attributes);++$ii) {
                $attribute = $attributes[$ii - 1];
                $results->bindParam($ii, $this->$attribute);
            }
            $results->execute();

            $this->id = $this->db->lastInsertId();
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Deletes an entry in the database.
     */
    public function delete()
    {
        try {
            $sql = 'DELETE FROM '.static::$tableName.' WHERE id = ?';
            $result = $this->db->prepare($sql);
            $result->bindValue(1, $this->id, PDO::PARAM_INT);
            $result->execute();
        } catch (Exception $e) {
            die('Error removing from database.');
        }
    }
}
