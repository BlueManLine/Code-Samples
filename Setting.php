<?php

class MrBlue_Setting
{
    /**
     * DB table name of storage
     * @var string
     * @access private
     */
    private static $_table = 'settings';


    /**
     * Temporary stored variables, much more faster then quering every time cache engine
     * @var array
     * @access private
     */
    private static $_values = array();


    /**
     * Array of variable types
     * @var array
    */
    private static $_types = array();


    /**
     * Cache engine should be use or not?
     * @var bool
    */
    private static $_bUseCacheEngine = false;


    /**
     * Updating setting value
     *
     * @param string $key
     * @param string $value
     *
     * @return boolean
     */
    public static function set($key, $value)
    {
        $db = self::getDbAdapter();

        $value = self::filterByType($value, isset(self::$_types[$key]) ? self::$_types[$key] : null );

        $bUpdater = $db->update(self::$_table, array('config_value'=>$value), 'config_name='.$db->quote($key));

        if ( $bUpdater==1 ) {
            self::$_values[$key] = $value;
            	
            // storeing the value in cache engine
            if( Zend_Registry::isRegistered('cache') && self::$_bUseCacheEngine ) {
                MrBlue_Cache::getInstance()->set('setting_'.$key, $value);
            }
        }

        return $bUpdater;
    }


    /**
     * Get stored setting value
     *
     * @param string $key		key of setting value
     * @param mixed $default	OPTIONAL default value, if the key cannot be found
     *
     * @return string
     */
    public static function get($key, $default='')
    {
        $sReturnValue = $default;

        if ( !isset(self::$_values[$key]) ) {

            // maybe it is in cache engine?
            if( Zend_Registry::isRegistered('cache') && self::$_bUseCacheEngine ) {
                $mCache = MrBlue_Cache::getInstance()->get('setting_'.$key);
                if ( $mCache!==false ) {
                    // yes! it is
                    self::$_values[$key] = $mCache;
                    return $mCache;
                }
            }

            $db = self::getDbAdapter();
            $sql = 'SELECT config_value, config_type FROM '.self::$_table.' WHERE config_name='.$db->quote($key);
            $aGetter = $db->query($sql)->fetch();
            	
            if( $aGetter!=false )
            {
                $sReturnValue = self::filterByType($aGetter['config_value'], $aGetter['config_type']);

                self::$_values[$key] = $sReturnValue;
                self::$_types[$key] = $aGetter['config_type'];

                // storeing the value in cache engine
                if( Zend_Registry::isRegistered('cache') && self::$_bUseCacheEngine ) {
                    MrBlue_Cache::getInstance()->set('setting_'.$key, $sReturnValue);
                }
            }
        }
        else {
            $sReturnValue = self::$_values[$key];
        }

        return $sReturnValue;
    }

    private static function filterByType($value, $type)
    {
        if( $type!='null' ) {
            settype($value, $type);
        }

        return $value;
    }


    /**
     * Get db adapter stored in Zend_Registry
     *
     * @return Zend_Db_Adapter_Abstract
     * @access private
     */
    private static function getDbAdapter()
    {
        return Zend_Registry::get('db');
    }
}
