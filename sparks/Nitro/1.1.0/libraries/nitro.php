<?php

if ( ! defined('NITRO_PATH') )
	define('NITRO_PATH', APPPATH . 'libraries/nitro/');

// Spark path - Core paths depend on this one for sparks compatibility
// It should be exactly the same as NITRO_PATH in a stand-alone installation
define('NITRO_SPARK_PATH', dirname(__FILE__) . '/nitro/');

// Core paths
define('NITRO_CORE_PATH', NITRO_SPARK_PATH . 'core/' );
define('NITRO_CORE_LIB_PATH', NITRO_CORE_PATH . 'lib/' );
define('NITRO_CORE_EXCEPTIONS_PATH', NITRO_CORE_PATH . 'exceptions/' );
define('NITRO_CORE_RELATIONS_PATH', NITRO_CORE_PATH . 'relations/' );
define('NITRO_CORE_VIEWS_PATH', NITRO_CORE_PATH . 'views/' );
// User paths
define('NITRO_LIB_PATH', NITRO_PATH . 'lib/' );
define('NITRO_EXCEPTIONS_PATH', NITRO_PATH . 'exceptions/' );
define('NITRO_RELATIONS_PATH', NITRO_PATH . 'relations/' );
//define('NITRO_VIEWS_PATH', NITRO_PATH . 'views/' );
// Spark paths
define('NITRO_SPARK_LIB_PATH', NITRO_SPARK_PATH . 'lib/' );
define('NITRO_SPARK_EXCEPTIONS_PATH', NITRO_SPARK_PATH . 'exceptions/' );
define('NITRO_SPARK_RELATIONS_PATH', NITRO_SPARK_PATH . 'relations/' );

// Models
define('NITRO_MODELS_PATH', APPPATH . 'models/nitro/' );
define('NITRO_BASE_MODELS_PATH', NITRO_MODELS_PATH . 'base/' );


// Load Core
require_once NITRO_CORE_PATH . 'NitroCore.php';
// Load user core
if ( file_exists( NITRO_PATH.'Nitro.php' ) ) // Search in user's app libraries/nitro/
	require_once NITRO_PATH . 'Nitro.php';
else if ( file_exists( NITRO_SPARK_PATH.'Nitro.php' ) ) // Search in spark's libraries/nitro/
	require_once NITRO_SPARK_PATH . 'Nitro.php';
else
	show_error("Could not find 'Nitro' class.");

// Load CI_Model
if ( ! class_exists('CI_Model') )
	load_class('Model', 'core');

// Load NitroCoreModel
require_once NITRO_CORE_PATH . 'NitroCoreModel.php';

// Load user's NitroModel
if( file_exists( NITRO_PATH . 'NitroModel.php' ) ) // Search in user's app libraries/nitro folder
	require_once NITRO_PATH . 'NitroModel.php';
elseif( file_exists( NITRO_MODELS_PATH.'../NitroModel.php' ) ) // Search in user's app models/ folder
	require_once NITRO_MODELS_PATH.'../NitroModel.php';
	elseif( file_exists( NITRO_MODELS_PATH.'NitroModel.php' ) ) // Search in user's app models/nitro/ folder
	require_once NITRO_MODELS_PATH.'NitroModel.php';
else
	require_once NITRO_SPARK_PATH . 'NitroModel.php'; // Fallback to spark/nitro path