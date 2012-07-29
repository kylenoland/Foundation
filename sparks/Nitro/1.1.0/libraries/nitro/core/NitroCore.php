<?php

class NitroCore
{
    protected $_pool;
    protected $_config;
    
    public function __construct()
    {
		// Register nitro autoload
		$this->_autoload();
		
        // Init Config
        $this->_config =& NitroConfig::init();
        
        // Init NitroPool
        $this->_pool =& NitroPool::init();
    }
	
	protected function _autoload ( )
	{
		return spl_autoload_register( function ( $class )
		{
			// Core Libs
			if( file_exists( NITRO_CORE_LIB_PATH . "$class.php" ) )
			{
				require_once NITRO_CORE_LIB_PATH . "$class.php";
			}
			// Core Exceptions
			elseif( file_exists( NITRO_CORE_EXCEPTIONS_PATH . "$class.php" ) )
			{
				require_once NITRO_CORE_EXCEPTIONS_PATH . "$class.php";
			}
			// Core Relations
			elseif( file_exists( NITRO_CORE_RELATIONS_PATH . "$class.php" ) )
			{
				require_once NITRO_CORE_RELATIONS_PATH . "$class.php";
			}
			// User Libs
			elseif( file_exists( NITRO_LIB_PATH . "$class.php" ) )
			{
				require_once NITRO_LIB_PATH . "$class.php";
			}
			// User Exceptions
			elseif( file_exists( NITRO_EXCEPTIONS_PATH . "$class.php" ) )
			{
				require_once NITRO_EXCEPTIONS_PATH . "$class.php";
			}
			// User Relations
			elseif( file_exists( NITRO_RELATIONS_PATH . "$class.php" ) )
			{
				require_once NITRO_RELATIONS_PATH . "$class.php";
			}
			// Spark Libs
			elseif( file_exists( NITRO_SPARK_LIB_PATH . "$class.php" ) )
			{
				require_once NITRO_SPARK_LIB_PATH . "$class.php";
			}
			// Spark Exceptions
			elseif( file_exists( NITRO_SPARK_EXCEPTIONS_PATH . "$class.php" ) )
			{
				require_once NITRO_SPARK_EXCEPTIONS_PATH . "$class.php";
			}
			// Spark Relations
			elseif( file_exists( NITRO_SPARK_RELATIONS_PATH . "$class.php" ) )
			{
				require_once NITRO_SPARK_RELATIONS_PATH . "$class.php";
			}
			// CI Nitro Models
			elseif( file_exists( NITRO_MODELS_PATH . "$class.php" ) )
			{
				require_once NITRO_MODELS_PATH . "$class.php";
			}
			// CI Nitro Base Models
			elseif( file_exists( NITRO_BASE_MODELS_PATH . "$class.php" ) )
			{
				require_once NITRO_BASE_MODELS_PATH . "$class.php";
			}
		}, TRUE ); // Throw exceptions!
	}
	
	public static function camelize ( $str, $pluralize=FALSE )
	{
		$str = preg_replace( '/[\s_]+/', ' ', $str );
		$str = str_replace( ' ', '', ucwords($str) );
		if ( $pluralize )
			$str = static::plural( $str );

		return ucfirst( $str );
	}

	public static function plural ( $str, $force=FALSE )
	{
		$str = trim( $str );
		$end = strtolower( substr($str,-1) );

		if ( $end == 'y' )
		{
			// Y preceded by vowel => regular plural
			$vowels = array('a', 'e', 'i', 'o', 'u');
			$str = in_array(substr(strtolower($str), -2, 1), $vowels) ? $str.'s' : substr($str, 0, -1).'ies';
		}
		else if ( $end == 's' )
		{
			if ( $force == TRUE )
				$str .= 'es';
		}
		else
			$str .= 's';

		return $str;
	}

	public static function singular ( $str )
	{
		$str = trim( $str );
		$end = strtolower( substr($str,-3) );

		if ( $end == 'ies' )
			$str = substr( $str, 0, strlen($str)-3 ).'y';
		else if ( $end == 'ses' ) // UNAVOIDABLE PROBLEM: Can be -1 or -2...
			//$str = substr( $str, 0, strlen($str)-2 );
			$str = substr( $str, 0, strlen($str)-1 );
		else if ( substr($str,-1) == 's' )
			$str = substr( $str, 0, strlen($str)-1 );

		return $str;
	}
	
	public static function mergeArrays ( )
	{
		$arrays = func_get_args();
		$count = func_num_args();
		
		for ( $i=0; $i<$count-1; $i++ )
		{
			if( $i == 0 )
				$arr1 = $arrays[$i];
			
			$arr2 = $arrays[$i+1];
			foreach ( (array)$arr2 as $key => $value )
			{
				if ( array_key_exists($key,(array)$arr1) && is_array($value) )
					$arr1[ $key ] = static::mergeArrays( $arr1[$key], $arr2[$key] );
				else
					$arr1[ $key ] = $value;
			}
		}

		return $arr1;
	}
	
    /**
     * Dumps info about a variable (recursive) in a given
     * level and context.
     *
     * @param mixed $params
     * @param int $lvl Default: 1
     * @param string $context
     * @return void
     */
	public static function debug( $params, $lvl = 1, $context = '' )
    {
        if( ! static::canDebug( $lvl, $context ) )
            return;
        
        // Default dump function
        $f = 'var_dump';
        
        // If the server has xdebug available...
        if( function_exists('xdebug_var_dump') )
        {
            // Set var display max depth to 5
            ini_set('xdebug.var_display_max_depth', 5);
            // Set var display max data to 1024 (double than default)
            ini_set('xdebug.var_display_max_data', 1024);

            // We don't really need to call this function
            // as xdebug is supposed to overload var_dump
            // with this one, but just in case...
            $f = 'xdebug_var_dump';
        }
        
        call_user_func_array( $f, (array)$params );
    }
    
    /**
     * Dump the Zend value of a variable using xdebug
     * 
     * If you want to use the navive debug_zval_dump, you
     * should use debugZValDump instead
     * 
     * @uses xdebug_debug_zval
     * 
     * @param string $varname
     * @param int $lvl Default: 3
     * @param string $context
     * 
     * @return void
     */
	public static function debugZVal( $varname, $lvl = 3, $context = '' )
    {
        if( ! static::canDebug( $lvl, $context ) || ! function_exists('xdebug_debug_zval') )
            return;
        
        xdebug_debug_zval( $varname );
    }
    
    /**
     * Dump the Zend value of a variable using native php function debug_zval_dump
     * 
     * @param mixed $var Should be passed by reference
     * @param int $lvl Default: 3
     * @param string $context
     * 
     * @return void
     */
	public static function debugZValDump( $var, $lvl = 3, $context = '' )
    {
        if( ! static::canDebug( $lvl, $context ) )
            return;
        
        debug_zval_dump( $var );
    }
    
    /**
     * Check if debug is enabled and in the correct level and context
     *
     * @param int $lvl
     * @param string $context
     * @return bool
     */
    public static function canDebug( $lvl, $context = '' )
    {
        // Get user-requested context to show
        $req_context = $_GET[NitroConfig::config('debug_context_GET_var')];
        $req_context || $req_context = NitroConfig::config('debug_default_context');
        
        // Debug not enabled or invalid context
        if( ! NitroConfig::config('debug') || ( $req_context && $req_context != $context ) )
			return FALSE;
        
        // Get user-requested debug verbosity level
        $req_level = $_GET[NitroConfig::config('debug_level_GET_var')];
        $req_level || $req_level = NitroConfig::config('debug_default_level');
        
        // Level in maxlevel
        if( $req_level & $lvl )
            return TRUE;
        
        return FALSE;
    }
	
	
	/***************** U T I L S *****************/

	
	public static function hasDecimals( $num )
	{
		if( ! is_numeric( $num ) ) return false;

		return (string)(int)$num !== (string)$num;
	}

	public static function toNumber( $num )
	{
		$num = str_replace(',', '.', $num);
		return is_numeric( $num ) ? ( static::hasDecimals( $num ) ? (float)$num : (int)$num ) : FALSE;
	}

	public static function isAssoc( array $array )
	{
		reset( $array );
		while( list( $k, $v ) = each( $array ) )
			if( ! is_int( $k ) )
				return TRUE;
		return FALSE;
	}

	public static function simplifyArray( $array )
	{
		foreach( $array as $k => $v )
		{
			if( is_array( $v ) && count( $v ) == 1 )
				$array[$k] = $v[0];
		}

		return $array;
	}
    
    public static function uncamelize( $str, $separator = '_', $tolower = TRUE )
    {
        $str = preg_replace('/([a-z])([A-Z])/', "$1$separator$2",$str);
        
        return $tolower ? strtolower( $str ) : $str;
    }
}