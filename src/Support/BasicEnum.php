<?php
/**
 * Created by PhpStorm.
 * User: ssaez
 * Date: 2/05/18
 * Time: 12:58
 */

namespace AppSupport\Support;

abstract class BasicEnum
{
    private static $constCacheArray = NULL;

    /**
     * Gets constant from defined enum
     * @return: (mixed)
     */
    public static function getConstants()
    {
        if (self::$constCacheArray == NULL) {
            self::$constCacheArray = [];
        }
        $calledClass = get_called_class();
        if (!array_key_exists($calledClass, self::$constCacheArray)) {
            $reflect = new \ReflectionClass($calledClass);
            self::$constCacheArray[$calledClass] = $reflect->getConstants();
        }

        return self::$constCacheArray[$calledClass];
    }

    /**
     * Checks if name exists in enum
     * @param: (string)
     * @param: (bool)
     * @return: (bool)
     */
    public static function isValidName($name, $strict = false)
    {
        $constants = self::getConstants();

        if ($strict) {
            return array_key_exists($name, $constants);
        }

        $keys = array_map('strtolower', array_keys($constants));

        return in_array(strtolower($name), $keys);
    }

    /**
     * Checks if value exists in enum
     * @param: (mixed)
     * @return: (bool)
     */
    public static function isValidValue($value, $strict = true)
    {
        $values = array_values(self::getConstants());

        return in_array($value, $values, $strict);
    }

    /**
     * Returns value with a given name
     * @param: (string)
     * @return: (mixed)
     */
    public static function getValueByName($name)
    {
        $constants = self::getConstants();
        if (array_key_exists($name, $constants))
            return $constants[$name];

        return false;
    }

    /**
     * Returns the name of the constant with a given value
     * If Enum has non-unqiue values then the first instance is returned
     * Should have it return array of constants with given value
     * @param: (string)
     * @return: (mixed)
     */
    public static function getNameByValue($value)
    {
        $constants = self::getConstants();
        $flip = array_flip($constants);
        if (array_key_exists($value, $flip))
            return $flip[$value];

        return false;
    }
}