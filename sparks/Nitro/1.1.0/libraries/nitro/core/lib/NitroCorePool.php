<?php

class NitroCorePool
{
	protected static $_enabled;
	protected static $_pool = array();
	protected static $_map = array();
	
	public static function &init()
    {
        static::$_enabled = NitroConfig::config('use_pool');
    }
	
	public static function push ( NitroModel $obj )
    {
		if ( ! static::$_enabled )
			return FALSE;
		
		$class = get_class( $obj );
		$hash = spl_object_hash( $obj );
		$pk = array_map( 'strval', (array)$obj->PK() );
		
		if ( static::pull( $class, $pk ) )
			return FALSE;
		
        static::$_pool[ $hash ] = $obj;
		static::$_map[ $class ][ $hash ] = $pk;
		
		return TRUE;
    }
	
	public static function pull ( $class, $pks=NULL )
    {
		if ( ! static::$_enabled )
			return FALSE;
		
		if ( func_num_args() == 1 ) // Returns all instances for given class
		{
			foreach ( (array)static::$_map[ $class ] as $hash => $o_pks )
				$pool[ $hash ] = static::$_pool[ $hash ];
			
			return $pool;
		}
		
		$pks = array_map( 'strval', (array)$pks );
		
        foreach ( (array)static::$_map[ $class ] as $hash => $o_pks )
			if ( $pks === $o_pks )
				return static::$_pool[ $hash ];
		
		return NULL;
    }
	
	public static function enable ()
	{
		static::$_enabled = TRUE;
	}
	
	public static function disable ()
	{
		static::$_enabled = FALSE;
	}
}