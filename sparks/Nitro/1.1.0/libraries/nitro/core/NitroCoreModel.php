<?php

class NitroCoreModel extends CI_Model
{
	/**
     * The connection values as specified in CI docs, beign a DSN string, array or active record group name.
     *
     * @tutorial http://codeigniter.com/user_guide/database/connecting.html
     */
    protected static $_db; // This can be a DB instance too
	protected static $_ar_name; // Active Record Name
    protected static $_table; // Can be overriden by harcoding it at user model class
    protected static $_prefix; // Can be overriden by harcoding it at user model class
	
	protected static $_class_; // Late state binding class ( get_called_class() cache )
	
	protected static $_fields_data = array();
    protected static $_ai_field;
    protected static $_pk = array();
	protected static $_map = array(); // Entity mapping
	protected static $_trans = 0; // Number of nested running transactions
	protected static $_trans_shutdown;
	
	protected $_fields = array(); // Current fields values
	protected $_modified_fields = array(); // Modified fields since last save()
	protected $_unescaped_fields = array(); // Unescaped fields since last save()
	protected $_history = array(); // Revision repository
	protected $_relations = array(); // Relationships
	protected $_relations_names = array(); // Relationships index by name
	protected $_saving = FALSE; // If the model is being saved
	protected $_deleting = FALSE; // If the model is being deleted
	
    public function __construct( $db = NULL )
	{
        // Init CI Model
		parent::__construct();

		// Get the DB to work with
        if ( $db ) // Execution-time instance-only custom database
			$this->_db = $db;
		else if ( ! $this->_db ) // Get the default DB
			$this->_db = static::DB();

        // Autodiscovery
        static::_autodiscover();

        // Init relationships
		$this->_initRelations();

        // Concurrency Control
        if( @static::$_map['row_version'] )
            $this->_implementRV();
    }
	
	/**
     * Entity table field getter
     *
     * @param string $field		Field name to get
     * @return mixed
     */
    public function get( $field )
    {
        if( $field == '__RV__' )
            throw new NitroException("Row Version field is unaccessible! Do not try to modify it in any way!");

        $field_wp = static::field( $field );

		if ( ! @static::$_fields_data[ $field_wp ] )
		{
			foreach ( (array)$this->_relations as $rel ) // Check if field is inherited
				if ( $rel instanceof NitroCoreRelation_Inheritance )
					return $rel->getRelated()->get( $field );
			
            throw new Exception("The field '".$field_wp."' is not defined on '".static::table()."' table!");
		}

		return $this->_fields[ $field_wp ];
    }
	
	/**
     * Entity table field setter
     *
     * @param string $field		Field name to set
     * @param mixed $data		Value to set
	 * @param bool $escape		Whether to `escape` $data or not
     * @return $this
     */
    public function set( $field, $data, $escape=TRUE )
    {
        $this->get( $field ); // Field check

		$field_wp = static::field( $field );
		
		if ( ! @static::$_fields_data[ $field_wp ] ) // Check if field is inherited
		{
			foreach ( (array)$this->_relations as $rel )
			{
				if ( $rel instanceof NitroCoreRelation_Inheritance )
				{
					$rel->getRelated()->set( $field, $data, $escape );
					return $this;
				}
			}
		}
		
		// Field is NOT inherited
		if ( ! isset( $this->_modified_fields[$field] ) ) // Keep old value
			$this->_modified_fields[ $field ] = @$this->_fields[ $field ];
		
		if ( ! $escape ) // Mark this field to unescape its value
			$this->_unescaped_fields[ $field ] = TRUE;
		
		$this->_fields[ $field ] = $data;
		
		return $this;
    }

	/**
     * Getter/Setter for primary key value
     *
     * @return mixed PK value or an array with column values if PK is multi-column/composite
     */
    public function PK()
    {
        $args = func_get_args();
        $num_args = func_num_args();

        $fields = static::PKFields();
        $pks = array();
		$nullcount = 0;

        if( $num_args && $num_args < count( $fields ) )
                throw new Fuel_LengthError( sprintf("Primary Key has %d fields, only %d provided.", count( $fields ), $num_args ) );

        foreach( $fields as $i => $f )
        {
            if( $num_args )
				$this->set( $f, $args[$i] );
			else if( ! ( $pks[ ] = $this->get( $f ) ) )
				$nullcount++;
        }

		if ( $num_args )
			return $this;
		
        return ($count=count( $fields )) == $nullcount ? NULL : ( $count == 1 ? $pks[0] : $pks );
    }
	
	/**
	 * Set this entity fields data with given $row array
	 * 
	 * @param array $row A DB result as row, array( field => value )
	 * @param bool $clear_relations
	 * @return $this 
	 */
	public function hydrate( $row, $clear_relations = FALSE )
	{
		// Clear old instance data
		$this->clear( $clear_relations );
		
		$is_array = is_array( $row );
		
		foreach ( static::$_fields_data as $field => $data )
			if ( $is_array )
				$this->_fields[ $field ] = $row[ $field ];
			else
				$this->_fields[ $field ] = $row->{ $field };
		
		return $this;
	}
	
	/**
	 * Returns this entity fields data
	 * 
	 * @return array 
	 */
	public function row()
	{
		$fields = $this->_fields;

		foreach( $this->_relations as $type => $rel )
			if( $rel instanceof NitroCoreRelation_Inheritance )
				if( ( $parent = $rel->getRelated( FALSE ) ) )
					$fields = array_merge ( $fields, $parent->row() );

		return $fields;
	}
	
	/**
	 * Reload this entity data from DB, rehydrate by current PK
	 * 
	 * @return $this 
	 */
	public function reload()
	{
		foreach( static::PKFields() as $field )
			$where[ $field ] = @$this->_modified_fields[ $field ] ? $this->_modified_fields[ $field ] : $this->_fields[ $field ];
		
		$q = $this->_db->get_where( static::table(), $where );
		
		if( ! ( $q && $q->num_rows() ) )
			return FALSE;
		
		return $this->hydrate( $q->row() );
	}
    
	/**
	 * Clears this entity instance, intended to be used to reutilize this instance for other table row
	 * 
	 * @param bool $relations	Whether to clear entity relationships too or not
	 * @return $this
	 */
    public function clear( $relations = FALSE )
    {
        // Clear modified fields
        $this->_modified_fields = array();
        
        // Clear fields values
        $this->_fields = array();
		
		// Clear escape fields settings
		$this->_unescaped_fields = array();
		
		// Init relationships
		if ( $relations )
			$this->_initRelations();
		
		return $this;
    }
	
	/**
	 * Saves this entity instance into DB
	 * 
	 * @param bool $force_insert	To force insertion even if entity already has PK
	 * @param bool $test_mode		Whether to COMMIT or not (test = TRUE, so REALLY SAVES by default =)
	 * @return bool
	 */
	public function save( $force_insert = FALSE, $test_mode = FALSE )
	{
		if( $this->_saving )
			return TRUE;
		$this->_saving = TRUE;
		
		// Start transaction
		static::transBegin( $test_mode );
		
		// Pre-insert/update ORM save
        foreach ( $this->_relations as $type => $rel )
			$rel->savePre();
		
		// Query updates only modified fields
		foreach( $this->_modified_fields as $field => $oldval )
			$this->_db->set( $field, $this->_fields[ $field ], !(bool)@$this->_unescaped_fields[ $field ] );

		// Piece up 'where' statement
		foreach( static::PKFields() as $field )
			$where[ $field ] = @$this->_modified_fields[ $field ] ? $this->_modified_fields[ $field ] : $this->_fields[ $field ];

        if( $force_insert || ! static::exists( $where ) ) // Insert
        {
            // Add row version
            if( @$this->_fields['__RV__'] )
                $this->_fields['__RV__'] = time();
			
			if ( empty($this->_modified_fields) ) // CI Active Record forces to use 'set' method
				$this->_db->set( (array)$where );
            $q = $this->_db->insert( static::table() );
			
			if ( $q ) // Updates the entity fields
			{
				if ( static::$_ai_field )
					$this->_fields[ static::$_ai_field ] = $this->_db->insert_id();
				
				$this->reload();
			}
        }
        else // Update
        {
            // Update only if there are local field changes
            if( $this->_modified_fields )
            {
                /*
				$pk = $this->PK();
				$logid = static::$_class_.":" . ( is_array( $pk ) ? join(',', $pk) : $pk );

                if( static::$concurrency_control && $this->session->userdata("w:$logid") )
                {
                    // Row version checks
                    $readts = (int)$this->session->userdata("r:$logid");
                    $rv = (int)$this->_fields['__RV__'];

					//var_dump( str_repeat( '-RV-', 50 ) );
                    //var_dump( $readts, $rv, $readts < $rv );
                    //var_dump( str_repeat( '-RV-', 50 ) );

                    if( $readts < $rv )
                        throw new Fuel_ConcurrencyError("The row for '$logid' was modified by someone else after your read but before your write.");

                    // Update row version
                    if( array_key_exists('__RV__', $this->_fields ) )
                        $this->_db->set( '__RV__', time() );
                }
				*/
				
                // Filter update by PK values
                $q = $this->_db->where( $where )->update( static::table() );
				if ( $q ) // Reload entity fields
					$this->reload();
            }
			else // No update was made, we assume TRUE status
				$q = TRUE;
        }

		if( $q && $this->_modified_fields )
		{
			// Save history and clear modified fields
			$this->_history[ ] = $this->_modified_fields;
			$this->_modified_fields = array();
		}
		
        // Post-insert/update ORM save
        foreach ( $this->_relations as $type => $rel )
			$rel->savePost();
		
		// Push into Instance Pool
		NitroPool::push( $this );

		$this->_saving = FALSE;
		
		// End transaction
		static::transCommit();
		
        return $q;
	}
	
	/**
	 * Deletes this entity instance from DB
	 * 
	 * @param bool $test_mode	Whether to COMMIT or not (test = TRUE, so REALLY DELETES by default =)
	 * @return bool
	 */
	public function delete( $test_mode = FALSE )
    {
		if( $this->_deleting )
			return TRUE;
		$this->_deleting = TRUE;
		
		// Start transaction
		static::transBegin( $test_mode );
		
		// Pre-delete ORM call
        foreach ( $this->_relations as $type => $rel )
			$rel->deletePre();

        // Make a where array
		foreach( static::PKFields() as $field )
			$where[ $field ] = @$this->_modified_fields[ $field ] ? $this->_modified_fields[ $field ] : $this->_fields[ $field ];

        $rs = $this->_db->where( $where )->delete( static::table() );
		
		// Post-delete ORM call
        foreach ( $this->_relations as $type => $rel )
			$rel->deletePost();
		
		$this->_deleting = FALSE;
		
		// End transaction
		static::transCommit();
		
		return $rs;
    }
	
	/**
	 * Restore modified field/s to original values since last hydration
	 * 
	 * @param String $field		Optional field name to restore (all fields if not given)
	 * @return $this 
	 */
	public function restore( $field = NULL )
    {
		if ( ! $field )
			foreach ( (array)$this->_modified_fields as $field => $data )
				$this->restore( $field );
		
		else if ( $field && isset($this->_modified_fields[$field]) )
		{
			$this->_fields[ $field ] = $this->_modified_fields[$field];
			unset( $this->_modified_fields[$field] );
			if ( isset($this->_unescaped_fields[$field]) )
				unset( $this->_unescaped_fields[$field] );
		}
		
		return $this;
    }
	
	/**
	 * Getter for this entity relationships
	 * 
	 * @param string $rel_name	The relation name to get, ALL relations if none given
	 * @param array $params		..toOne: Instanciate the related entity if isn't yet assigned
	 *							..toMany: Optionally sets where, order_by, group_by, limit_value and limit_offset to filter collection
	 * @return array|NitroModel|NitroCollection
	 */
	public function relation( $meth_name, $params = array() )
	{
		if ( ! @$this->_relations_names[ $meth_name ] )
		{
			foreach ( (array)$this->_relations as $rel ) // Check if relation is inherited
			{
				if ( $rel instanceof NitroCoreRelation_Inheritance )
					if ( func_num_args() > 1 )
						return $rel->getRelated()->relation( $meth_name, $params );
					else
						return $rel->getRelated()->relation( $meth_name );
				
			}
			
            throw new Exception("The relation '".$meth_name."' is not defined on '".static::className()."' entity!");
		}

		if ( func_num_args() > 1 ) // GET related
			return $this->_relations[ $this->_relations_names[$meth_name] ]->getRelated( $meth_name, $params );
		
		// GET relationship
		return $this->_relations[ $this->_relations_names[$meth_name] ];
	}
	
	/**
	 * Getter for this entity relationship type
	 * 
	 * @param string $rel_type	The relation type to get
	 * @return NitroRelation 
	 */
	public function relationByType( $rel_type )
	{
		return $this->_relations[ $rel_type ];
	}
	
	/**
	 * Initialize entity relationships
	 */
	protected function _initRelations()
	{
		// Init relationships
		foreach ( (array) static::$_map['relations'] as $type => $rels )
		{
			$rel = "NitroRelation_" . $type;
			$this->_relations[ $type ] = new $rel( $this );
			
			foreach ( $rels as $rel_name => $rel_conf )
				$this->_relations_names[ $rel_name ] = $type;
		}
	}
	
	/**
	 * Implement table row version field
	 */
	protected function _implementRV()
	{
		// Search __RV__ field
        if( ! @$this->_fields['__RV__'] )
        {
            Nitro::debug( "Concurrency control not implemented. Creating `__RV__` field on table " . static::table() );

            // Add __RV__ field
            if( ! static::DB()->query("ALTER TABLE `".static::table()."` ADD `__RV__` INT UNSIGNED NULL") )
                throw new NitroException( "Couldn't add `__RV__` column to table " . static::table() );
        }
		
		Nitro::debug("Concurrency control enabled");
	}

	/**
	 * Gets/Sets the mapping config for this entity class
	 * 
	 * @param string $key	The mapping key to return, ALL mapping if none given
	 * @param mixed $value	A value to be set for given $key
	 * @return array|mixed	Full mapping array or given $key corresponding value
	 */
	public static function &mapping( $key = NULL, $value = NULL )
	{
		if( ! static::$_class_ )
            static::_autodiscover();
		if ( ! static::$_map )
			static::$_map =& NitroConfig::mapping( static::$_ar_name, static::$_class_ );
		
		if ( $key )
		{
			if ( func_num_args() > 1 )
				static::$_map[ $key ] = $value;
			
			return static::$_map[ $key ];
		}
		
		return static::$_map;
	}
	
	/**
	 * Gets this class name (late state binding class, as get_called_class() cache)
	 * 
	 * @return string 
	 */
	public static function className()
	{
		if( ! static::$_class_ )
            static::_autodiscover();
		
		return static::$_class_;
	}
	
	/**
	 * Gets the Active Record name where this instance is defined
	 * 
	 * @return string 
	 */
	public static function ARname()
	{
		return static::$_ar_name;
	}
	
	/**
     * Fetches the DB object for this entity model
     *
     * @return object
     */
    public static function DB()
    {
		if ( ! is_object(static::$_db) )
		{
			$CI = get_instance();

			if ( static::$_db ) // Custom DB was specified as DSN string or array
				static::$_db = $CI->load->database( static::$_db, TRUE );
			else if ( static::$_ar_name != NitroConfig::getActiveGroup() ) // Not default AR name
				static::$_db = $CI->load->database( static::$_ar_name, TRUE );
			else if ( ! $CI->db ) // No DB connection at all!
				static::$_db = $CI->load->database( static::$_ar_name, TRUE );
			else // The CI DB connection
				static::$_db = $CI->db;

			if ( ! ( static::$_db && method_exists(static::$_db,"query") ) )
				throw new NitroException("Couldn't get a DB instance for '".get_called_class()."'.");
			
			// http://dev.mysql.com/doc/refman/5.5/en/server-system-variables.html#sysvar_sql_auto_is_null
			static::$_db->query("SET sql_auto_is_null = 0");
		}

		return static::$_db;
    }
	
	/**
     * Gets full table name (prefix + table)
     *
     * @return string;
     */
    public static function table()
    {
		if( ! static::$_table )
            static::_autodiscover();
		
        if( preg_match('/^'.static::DB()->dbprefix.'/i', static::$_table) )
			return static::$_table;
		
        return static::DB()->dbprefix( static::$_table );
    }
	
	/**
	 * Gets table name prefix
	 * 
	 * @return string
	 */
	public static function tablePrefix()
	{
		return static::DB()->dbprefix;
	}
	
	/**
	 * Gets table name without prefix
	 * 
	 * @return string
	 */
	public static function tableNoPrefix()
	{
		if( ! static::$_table )
            static::_autodiscover();
		
		return preg_replace( '/^' . preg_quote( static::DB()->dbprefix, '/' ) . '/i', '', static::$_table );
	}
	
	public static function fields()
	{
		if( ! static::$_fields_data )
            static::_autodiscover();
		
		return static::$_fields_data;
	}
	
	/**
     * Gets the full field name (prefix + $field)
     *
     * @return string
     */
    public static function field( $field )
    {
		if( ! static::$_prefix )
            static::_autodiscover();
		
        if( preg_match('/^'.static::$_prefix.'/i', $field) )
			return $field;
		
        return static::$_prefix . $field;
    }
	
	/**
	 * Gets table field prefix
	 * 
	 * @return string 
	 */
	public static function fieldPrefix()
    {
		if( ! static::$_prefix )
            static::_autodiscover();
		
        return static::$_prefix;
    }
	
	/**
	 * Gets table field name without prefix
	 * 
	 * @param string $field		Field name to trim its prefix (if any)
	 * @return string 
	 */
	public static function fieldNoPrefix( $field )
	{
		if( ! static::$_prefix )
            static::_autodiscover();
		
		return preg_replace( '/^' . preg_quote( static::$_prefix, '/' ) . '/i', '', $field );
	}
	
	/**
	 * Gets the primary key field names
	 *
	 * @param bool $prefix	If set to FALSE, returns the PK fields without the prefix
	 * @return array|string
	 */
    public static function PKFields( $prefix = TRUE )
    {
		if ( ! static::$_pk ) // Autodiscovery
			static::_autodiscover();

		if ( $prefix )
			return static::$_pk;

		// Return PK field names without prefix
		foreach ( static::$_pk as $pk )
			$pks[ ] = static::fieldNoPrefix( $pk );
		
		return $pks;
    }
	
	/**
	 * Begin new transaction
	 * 
	 * @param bool $test_mode	Whether to COMMIT or not (test = TRUE)
	 */
	public static function transBegin( $test_mode = FALSE )
	{
		if( ! static::$_class_ )
            static::_autodiscover();
		
		static::DB()->trans_begin( $test_mode );
		static::$_trans++;

		if( ! static::$_trans_shutdown ) // We want to ensure rollback if script is cancelled!
			register_shutdown_function( array(static::$_class_,"transRollback") );
		static::$_trans_shutdown = TRUE;
	}
	
	/**
	 * Rollback current open transaction
	 */
	public static function transRollback()
	{
		if( static::$_trans == 0 ) // No transaction is running
			return FALSE;
		
		static::DB()->trans_rollback();
		static::$_trans--;
	}
	
	/**
	 * Commit current open transaction
	 */
	public static function transCommit()
	{
		static::DB()->trans_complete();
		static::$_trans = 0;
		
		if( static::DB()->trans_status() === FALSE )
			throw new NitroException( static::DB()->_error_message(), static::DB()->_error_number() );
		
		return static::DB()->trans_status();
	}
	
	/**
     * Creates a join with parent entity table if any
     * 
     * @return void
     */
    public static function parentJoin()
    {
		$hasParent = FALSE;
		
        foreach( (array)static::$_map['relations'] as $relName => $related )
        {
            $rel = "NitroRelation_$relName";
            if( is_subclass_of( $rel, "NitroCoreRelation_Inheritance" ) )
            {
                foreach( $related as $methodName => $relConf )
                {
                    $parentEntity = $relConf['entity'];
                    $parent_table = $parentEntity::table();
                    $parent_pkfs = (array)$parentEntity::PKFields();
                }
                
                $table = static::table();
                $pkfs = (array)static::PKFields();
                
                if( count( $pkfs ) != count( $parent_pkfs ) )
                    throw new NitroException("$table PK field count (".count( $pkfs ).") differs from $parent_table's (".count( $parent_pkfs ).")!");
                
                $join_fields = array();
                foreach( $pkfs as $i => $pkf )
                    $join_fields[] = "$table.$pkf = $parent_table.{$parent_pkfs[$i]}";
                
				static::DB()->join( $parent_table, implode( ' AND ', $join_fields ), 'left' );
                
                $parentEntity::parentJoin();
				
				$hasParent = TRUE;
            }
        }
		
		return $hasParent;
    }
	
	/**
	 * Search rows of this class with given $where criteria
	 * 
	 * @param mixed $where	As CI ActiveRecord where parameter
	 * @param $params		Optionally sets order_by, group_by, limit_value and limit_offset to filter collection
	 * @return NitroCollection 
	 */
    public static function search( $where, $params = array() )
    {
        if( ! static::$_class_ )
            static::_autodiscover();
		
		// Piece joins to parent entities if any
        $hasParent = static::parentJoin();
        
        // Prefix every field name with the current entity table if there's none
        if( $hasParent && ! empty( $where ) )
        {
            $where_fields = array_keys( $where );
            $where_values = array_values( $where );
			$table = static::table();
			
            foreach( $where_fields as $i => $field )
				$where_fields[ $i ] = str_replace('#TABLE#', $table,
					preg_replace_callback(
						'/(?<!\.)`([\w]+)`(?!\.)|(?<=\()\s*([\w]+)\s*(?=\))|(^\w+$)/i',
						function( $m ) { return "`#TABLE#`.`". $m[count($m)-1] .'`'; },
						$field
					)
				);
			
            $where = array_combine( $where_fields, $where_values );
        }
        
        $q = static::DB()->where( $where ); // Apply where
		if ( $params["order_by"] ) // Apply order by
			$q->order_by( $params["order_by"] );
		if ( $params["group_by"] ) // Apply group by
			$q->group_by( $params["group_by"] );
		if ( $params["limit_value"] ) // Apply limit
			$q->limit( $params["limit_value"], @$params["limit_offset"] );
		
		$q = $q->get( static::table() );
		
        if( ! ( $q && $q->num_rows() ) )
			return FALSE;
		
        return $q->result();
    }
	
	/**
	 * Search a single row of this class by its PK
	 * 
	 * @param mixed $pk_values	PK value to search for (array if composite PK)
	 * @return array|NULL
	 */
	public static function searchByPK( $pk_values )
    {
		$pk_values = (array) $pk_values;
		$pk_fields = static::PKFields();
		
		// If no PK values are passed don't throw exception
        if( empty( $pk_values ) )
            return NULL;
		
		if ( count($pk_values) < count($pk_fields) )
			throw new NitroException("There are ".count($pk_fields)." Primary Key columns, only ".count($pk_values)." given!");
		
        foreach ( $pk_fields as $i => $pkf )
		{
			$pkv = @$pk_values[ $i ];
			$where[ $pkf ] = $pkv ? $pkv : $pk_values[ $pkf ];
		}
		
        if( FALSE == ( $rs = static::search( $where ) ) )
            return NULL;
        
        return $rs[0];
    }
	
	/**
	 * Search rows of this class by multiple PKs
	 * 
	 * @param array $pk_values	PK values to search for (array of arrays if composite PK)
	 * @return array|NULL 
	 */
	public static function searchByPKs( array $pk_values )
    {
		// Pre-filter: remove not found
		foreach( $pk_values as $pk )
			if( FALSE !== ($row = static::searchByPK($pk) ) )
				$rows[ ] = $row;

		return $rows;
    }
	
	/**
	 * Find instances of this class with given $where criteria
	 * 
	 * @param mixed $where	As CI ActiveRecord where parameter
	 * @param $params		Optionally sets order_by, group_by, limit_value and limit_offset to filter collection
	 * @return NitroCollection 
	 */
	public static function find( $where, $params = array() )
    {
        $rs = static::search( $where, $params );
        if( $rs )
            $pkfs = static::_extractPKs( $rs );
		
		return new NitroCollection( static::$_class_, $pkfs );
    }
	
	/**
	 * Find a single instance of this class by its PK
	 * 
	 * @param mixed $pk_values	PK value to search for (array if composite PK)
	 * @return $this
	 */
	public static function findByPK( $pk_values )
    {
		if ( ( $obj = NitroPool::pull( static::$_class_, $pk_values ) ) )
			return $obj;
		
		$rs = static::searchByPK( $pk_values );
		if ( ! $rs )
			return NULL;
		
		$obj = new static;
		$obj->hydrate( $rs );
		
		// Push into Instance Pool
		NitroPool::push( $obj );
		
		return $obj;
	}
	
	/**
	 * Find instances of this class by multiple PKs
	 * 
	 * @param array $pk_values	PK values to search for (array of arrays if composite PK)
	 * @return NitroCollection 
	 */
	public static function findByPKs( array $pk_values )
    {
		$rs = static::searchByPKs( $pk_values );
        if( $rs )
            $pkfs = static::_extractPKs( $rs );
		
		return new NitroCollection( static::$_class_, $pkfs );
	}
	
	/**
	 * Returns all instances of this class
	 * 
	 * @param $params	Optionally sets order_by, group_by, limit_value and limit_offset to filter collection
	 * @return NitroCollection
	 */
	public static function all( $params = array() )
	{
		return static::find( array(), $params );
	}
	
	/**
	 * Checks how many instances exist for given $where criteria
	 * 
	 * @param mixed $where	As CI ActiveRecord where parameter
	 * @return int			The matched records num
	 */
	public static function exists( $where )
    {
        if( ! static::$_class_ )
            static::_autodiscover();
		
        return (int)static::DB()->select("COUNT(*) AS c")
				->get_where( static::table(), $where )
				->row()->c;
    }
	
	/**
	 * Debug DB level messages, relyes on Nitro::debug()
	 * 
	 * @param string $msg	Message to be loged 
	 */
	public static function debug( $msg )
	{
		if ( ! static::$_class_ )
			static::_autodiscover();
		
		Nitro::debug( static::$_class_." :: $msg", 'model' );
	}
	
	/**
     * Autodiscovery of DB data for this table
     *
     * @param bool $force	Only run if no data is loaded, unless TRUE
     */
    protected static function _autodiscover( $force = FALSE )
    {
        if( ! static::$_fields_data || $force )
        {
			// Set the called class (late state binding)
			static::$_class_ = get_called_class();
            static::debug("Executing _autodiscover");
			
			// Init entity mapping
			static::mapping();
			
			// Set default values for table name and field prefix
			// only if the values are not previously set (overriden)
			if( ! static::$_table )
				static::$_table = static::$_map['table'];
			
			if( ! static::$_prefix )
				static::$_prefix = static::$_map['prefix'];
			
			// Check for table existance on the database
			if ( ! static::DB()->table_exists( static::table() ) )
				throw new NitroException("The table '".static::table()."' doesn't exist!");

			// Init arrays
			static::$_pk = array();
			static::$_fields_data = array();
			
			// Get field data
            $data = static::DB()->query("EXPLAIN ".static::table());
			foreach( $data->result() as $f )
			{
				$_f = new stdClass;
				$_f->name = $f->Field;
				preg_match('/^([a-z_]+)(?:\((\d+)\))?/i', $f->Type, $r);
				$_f->type = $r[1];
				$_f->max_length = $r[2];
				$_f->unsigned = (bool) preg_match('/unsigned/i', $f->Type) ;
				if ( preg_match('/^([a-z_]+)(?:\(\'(.*)\'\))?/i', $f->Type, $r) )
				$_f->values = str_replace( "','", ",", $r[2] );
				$_f->primary_key = $f->Key == 'PRI';
				$_f->unique = $f->Key == 'UNI';
				$_f->null = $f->Null == 'YES';
				$_f->default = $f->Default;
				$_f->auto_increment = $f->Extra == 'auto_increment';
				
				if( $_f->primary_key )
					static::$_pk[ ] = $_f->name;
				
				if( $_f->auto_increment )
					static::$_ai_field = $_f->name;

				static::$_fields_data[ $_f->name ] = $_f;
			}
			
			// All tables MUST have a primary key! No "Exception"s ;)
			if( ! static::$_pk )
				throw new NitroException("The table '".static::table()."' doesn't have a primary key defined!");
        }
    }
	
	/**
	 * Returns an array of PKs for given query object resultset
	 * 
	 * @param CI query result $rs	The result to process
	 * @return array 
	 */
	private static function _extractPKs( $rs )
	{
		$pk_fields = static::PKFields();
		
        foreach ( (array)$rs as $obj )
		{
			$pkf = array();
			foreach ( $pk_fields as $pk )
				$pkf[ ] = $obj->{$pk};
			
			$pkfs[ ] = $pkf;
		}
		
		return $pkfs;
	}
	
	/**
	 * Dynamic method call handler
	 * 
	 * @param string $name		The method to be called
	 * @param array $arguments	Arguments to pass as method parameters
	 * @return mixed			Result of called method (getter/setter)
	 */
	public function __call( $name, $arguments )
    {
        $colname = preg_replace('/^(g|s)et/i', '', $name);
        
        // If this happens, it means that the name does not contain get/set
        // prefixes and we only want to allow getters/setters to be overloaded
        if( $colname == $name )
            throw new NitroException("Call to undefined function '$name' on " . static::$_class_);
        
        // We need to try to find the correct field name
        
        // First try: $col is the exact name of the filed
        if( ! array_key_exists( $colname, static::$_fields_data ) )
        {
            // Second try: $colname is the camelized version of the field name
            $colname = Nitro::uncamelize( $colname );
            if( ! array_key_exists( $colname, static::$_fields_data ) )
            {
                throw new NitroException("Call to undefined function '$name' on " . static::$_class_);
            }
        }
        
        // NOTE: No more tries, if you need more cases you can extend this in
        // NitroModel to adjust the column name matching your needs!
        
        // If we are here, it means we found it!
        
        // So, if we have arguments (only one!!) it's a setter
        return empty( $arguments ) ?
            $this->get( $colname ) :
            $this->set( $colname, $arguments[1] );
    }
	
	/**
	 * Entity string conversion, displaying current entity field's values
	 */
	public function __toString( )
	{
		foreach ( $this->row() as $fld => $data )
			$fld_data[] = "`".static::fieldNoPrefix($fld)."` = ".$data;

		return static::$_class_." :: ( PKs: ".implode(" , ",static::PKFields(FALSE))." ) :: [ \n\t".implode(", \n\t",$fld_data)." \n]";
	}
}
