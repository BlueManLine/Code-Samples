<?php

/**
 * Simple cache class
 *
 * @uses        MrBlue_Cache::getInstance()->get('your_key')
 * @uses        MrBlue_Cache::getInstance()->set('your_key','set_value')
 *
 */
class MrBlue_Cache
{
    protected static $_instance;
    
    protected $oCache;
    protected $oConfig;
    
    const CACHE_INDEX_UNIQUE_KEY = 'CacheIndex';
    /**
     * if on server there are many sites using memory caching
     * (for example memcache), there may be problems without prefix
     */
    const CACHE_PREFIX_NAME = 'SportsLadder2013__';
    
    protected $aCacheOptions = array(
        'default_lifetime' => 86400,
        'index_lifetime' => 865000
    );
    
    public function __construct($aOptions=array())
    {
        $this->oConfig = Zend_Registry::get('config');
		
    	if(Zend_Registry::isRegistered('cache')) {
			$this->oCache = Zend_Registry::get('cache');
		}
		else {
			throw new Exception('Cache->__construct() : No object cache in registry!');
		}
		
		$this->aCacheOptions = array_merge($this->aCacheOptions, $aOptions);
    }
    
    /**
     * Singleton of MrBlue_Cache
     *
     * @param array $aOptions
     *
     * @return MrBlue_Cache
     */
    public static function getInstance($aOptions=array()) {
        if( null === self::$_instance ){
            self::$_instance = new self($aOptions);
		}
        return self::$_instance;
    }
    
	/**
	 * tell is cache active
	 * @return boolean
	 */
    public function isActive(){
        if(! $this->oCache){
            return false;
        }
        return true;
    }
    
    /**
     * if you want to now what data are cached
     * @return null
     */
    public function getIndex(){
        if(! $this->isActive()){
            return null;
        }
        return $this->get(self::CACHE_INDEX_UNIQUE_KEY);
    }
    
    /**
     * Saving items to cache engine
     *
     * @param string $sCacheKey     cache key
     * @param mixed $mCacheValue    value to cache
     * @param array $aTags          OPTIONAL only needen if you want delete cache by tags
     * @param int $iLifetime        OPTIONAL in seconds
     *
     * @return null|boolean
     * @throws Exception
     */
    public function set($sCacheKey, $mCacheValue, array $aTags=array(), $iLifetime=-1){
        if(! isset($mCacheValue)){
            throw new Exception('Cache->set() : $mCacheValue invalid!');
        }
        if(! $sCacheKey){
            throw new Exception('Cache->set() : $sCacheKey invalid!');
        }
        
        if(! is_array($aTags)){
            throw new Exception('Cache->set() : $aTags must be an array value!');
        }
        
        if( $iLifetime===-1 ){
            $iLifetime = $this->getOption('default_lifetime');
        }
        elseif(! is_int($iLifetime)) {
            throw new Exception('Cache->set() : $iLifetime must be an integer value!');
        }
        
        if(! $this->isActive()){
            return null;
        }
		
        $this->oCache->save($mCacheValue, $this->prepareKey($sCacheKey), $aTags, $iLifetime);
        
        $this->updateIndex($sCacheKey);
        
        return true;
    }
    
    /**
     * get cached value
     *
     * @param mixed $mCacheKey strin or array
     * @return null|array
     * @throws Exception
     */
    public function get($mCacheKey){
        if(!$mCacheKey){
            throw new Exception('Cache->get() : $mCacheValue invalid!');
        }
        if(! $this->isActive()){
			return null;
        }
        if(! is_array($mCacheKey)){
             return $this->oCache->load($this->prepareKey($mCacheKey));
        }
        else {
            $aReturnCache =array();
            if(! empty($mCacheKey)){
                foreach($mCacheKey as $key){
                    $aReturnCache[$key] = $this->oCache->load($this->prepareKey($key));
                }
            }
            return $aReturnCache;
        }
    }
    
    /**
     * delete cache or group of cache and his index
     * @param mixed $mCacheKey
     * @return null|boolean
     * @throws Exception
     */
    public function delete($mCacheKey){
        if(!$mCacheKey){
            throw new Exception('Cache->delete() : $mCacheKey invalid!');
        }
        if(! $this->isActive()){
            return null;
        }
        if(! is_array($mCacheKey)){
             $this->oCache->remove($this->prepareKey($mCacheKey));
             $this->updateIndex($mCacheKey, true);
             return true;
        }
        else {
            if (!empty($mCacheKey)) {
                foreach ($mCacheKey as $key) {
                    $this->oCache->remove($this->prepareKey($key));
                    $this->updateIndex($key, true);
                }
            }
            else {
                return null;
            }
            return true;
        }
    }
    
    /**
     * clean cache maching $method
     * @link http://framework.zend.com/manual/en/zend.cache.theory.html
     *
     * @param string $method
     * @param array $aTags
     *
     * @return boolean
     */
    public function clean($method = Zend_Cache::CLEANING_MODE_ALL, $aTags = array()){
        if( $this->oConfig->serwer->cache->enabled ){
			return $this->oCache->clean($method, $aTags);
		}
	}
    
    /**
     * get cache option
     * @param string $sOptionName
     * @return null
     */
    protected function getOption($sOptionName){
        if(isset($this->aCacheOptions[$sOptionName])){
            return $this->aCacheOptions[$sOptionName];
        }
        return null;
    }
    
    /**
     * prepare key to save in cache
     * @param string $key
     * @return string
     */
    protected function prepareKey($key){
        $key = $this->filterKeyName($key);
        $key = self::CACHE_PREFIX_NAME.$key;
        return $key;
    }

    /**
     * update cache index
     *
     * @param string $newKey
     * @param boolean $bDelete if set to true, key will be deleted
     *
     * @return boolean
     */
    protected function updateIndex($newKey, $bDelete=false){
        $aIndex = $this->getIndex();
        $newKey = $this->prepareKey($newKey);
        
        if(! isset($aIndex[$newKey])){
            if(! $bDelete){
                $aIndex[$newKey] = 1;
            }
            else {
                unset($aIndex[$newKey]);
            }
			$this->oCache->save($aIndex, $this->prepareKey(self::CACHE_INDEX_UNIQUE_KEY), array(), $this->getOption('index_lifetime'));
        }
        return true;
    }
    
    /**
     * key must be filtered
     * @param string $key
     * @return string
     */
    protected function filterKeyName($key){
        return preg_replace('/[^A-Za-z0-9_]/D','_',$key);
        //return str_replace(array(',', ';', '.', '<', '>','/','\\','+','-','(',')','=','{','}','|',':','&'), '_', $key);
    }
	
}
