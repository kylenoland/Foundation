<?php

class NitroCoreConfig
{
    protected static $_cfgs = array(
        'nitro' => array(
			'debug' => FALSE,
			'debug_default_level' => 1,
			'debug_default_context' => '',
			'debug_level_GET_var' => '__v',
			'debug_context_GET_var' => '__c',
			'mapping_configs' => array(),
			'mapping_configs_prefix' => 'nitro_mapping',
			'hooks_configs' => array(),
			'hooks_configs_prefix' => 'nitro_hooks',
			'forms_configs' => array(),
			'forms_configs_prefix' => 'nitro_forms',
			'use_pool' => TRUE
		),
        'mapping' => array(),
		'forms' => array(),
        'hooks' => array()
    );
	protected static $_active_group;
	
	
	private function __construct() {}
    
    public static function &init()
    {
        static::$_cfgs['nitro'] = static::_mergeConfigs( static::$_cfgs['nitro'], static::_loadCfg() );

		static::_initCfg('forms');
		static::_initCfg('hooks');
		static::_initCfg('mapping');
		
		// Get the default Active Record group name
		static::getActiveGroup();
		
		return static::$_cfgs;
    }
	
	protected static function _initCfg ( $key )
	{
		if ( empty( static::$_cfgs['nitro'][$key.'_configs'] ) )
        {
            get_instance()->load->helper('directory');
            $dir = directory_map( APPPATH.'config', TRUE );
			sort( $dir ); // Order by file name
			
            foreach ( $dir as $file )
            {
                if ( ! preg_match('/^'.static::$_cfgs['nitro'][$key.'_configs_prefix'].'/i', $file) )
                    continue; // Just keep files with given prefix

                // Remove file prefix and extension
                $file = preg_replace( '/(^'.static::$_cfgs['nitro'][$key.'_configs_prefix'].'|\\'.EXT.'$)/i', '', $file );
				
                // Load the mapping file
                $cfg = static::_loadCfg( $file, static::$_cfgs['nitro'][$key.'_configs_prefix'] );
				static::$_cfgs[ $key ] = static::_mergeConfigs( static::$_cfgs[$key], $cfg );
            }
        }
        else
        {
            foreach ( static::$_cfgs['nitro'][$key.'_configs'] as $file )
            {
                $cfg = static::_loadCfg( $file, static::$_cfgs['nitro'][$key.'_configs_prefix'] );
                static::$_cfgs[ $key ] = static::_mergeConfigs( static::$_cfgs[$key], $cfg );
            }
        }
		
		return $cfg;
	}
	
	protected static function _mergeConfigs ( $arr1, $arr2 )
	{
		return Nitro::mergeArrays( $arr1, $arr2 );
	}
    
    public static function get ( $name, $key=NULL )
    {
        if ( $key )
			return static::$_cfgs[ $name ][ $key ];
		
		return static::$_cfgs[ $name ];
    }
    
    public static function set ( $name, $key, $val )
    {
		static::$_cfgs[ $name ][ $key ] = $val;
    }
	
	public static function config ( $key=NULL, $val=NULL )
	{
		if ( func_num_args() < 2 )
			return static::get( 'nitro', $key );
		
		static::set( 'nitro', $key, $val );
	}
	
	public static function mapping ( $ar_name=NULL, $key=NULL, $val=NULL )
	{
        $map = static::get( 'mapping', $ar_name );
        
		if ( func_num_args() < 3 )
            return $key ? $map[$key] : $map;
                
        
        $map[$key] = $val;
        
		static::set( 'mapping', $ar_name, $map );
	}
    
	public static function mappingDefault ( $key=NULL, $val=NULL )
    {
        return static::mapping( 'default', $key, $val );
    }
	
	public static function forms ( $ar_name=NULL, $key=NULL, $val=NULL )
	{
        $map = static::get( 'forms', $ar_name );
        
		if ( func_num_args() < 3 )
            return $key ? $map[$key] : $map;
                
        
        $map[$key] = $val;
        
		static::set( 'forms', $ar_name, $map );
	}
    
	public static function formDefault ( $key=NULL, $val=NULL )
    {
        return static::forms( 'default', $key, $val );
    }
	
	public static function hooks ( $key=NULL, $val=NULL )
	{
		if ( func_num_args() < 2 )
			return static::get( 'hooks', $key );
		
		static::set( 'hooks', $key, $val );
	}
	
	/**
	 * Get the default Active Record group name
	 * 
	 * @return String
	 */
	public static function getActiveGroup ( )
	{
		if ( ! static::$_active_group )
		{
			// Is the config file in the environment folder?
			if ( ! defined('ENVIRONMENT') OR ! file_exists($file_path = APPPATH.'config/'.ENVIRONMENT.'/database'.EXT))
			{
				if ( ! file_exists($file_path = APPPATH.'config/database'.EXT))
				{
					show_error('The configuration file database'.EXT.' does not exist.');
				}
			}

			include( $file_path );
			
			static::$_active_group = $active_group ? $active_group : 'default';
		}
		
		return static::$_active_group;
	}
	
	protected static function _loadCfg ( $name='', $prefix='nitro' )
	{
		$file = $prefix . $name;
		if( file_exists( APPPATH . "config/$file".EXT ) )
            $cfg = require_once APPPATH . "config/$file".EXT;
		if( ! is_array($cfg) )
            $cfg = $nitro;
        
		return (array) $cfg;
	}
}