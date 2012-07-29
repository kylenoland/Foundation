<?php

class NitroCoreGen
{
	protected $_CI; // The CI instance
	
    protected $_config = array( // Default config params
		"skip_CI_session_table" => TRUE, // Skip the CI_Session table (just for mapping generation)
		"skip_PK_tables" => TRUE, // Skip tables where all its fields are PKs (usually, 'link' tables for relationships)
		"permissions" => 0664
	);
	protected $_overwrite = FALSE; // Bool to indicate if it must overwrite the generated file
	protected $_inputs = array(); // An array containing all input template files
	protected $_output = NULL; // The output file
	public $replaces = array(); // An indexed array with all the replacements: array( str_to_replace => str_replacement )
	
	protected $_cfg; // The CI config
	protected $_db = array(); // The DB connections: array( active_record_name => DB_obj )
	protected $_mappings = array(); // The DB mappings array
	protected $_forms = array(); // The DB forms array
	protected $_method_path = array(); // Path to model variable used at forms
	protected $_forms_parsed = array(); // Parsed forms indexed by class name
	protected $_attr_name_prefix; // Prefix to form field name
	
	protected $_fields = array(); // Cache for table fields
	protected $_labels = array(); // Cache of writen labels
	
	public function __construct ( $conf = array() )
    {
		// Set the CI instance
		$this->_CI =& get_instance();
        // Merge user config arrays
        $this->_config = Nitro::mergeArrays( $this->_config, $conf );
		// Gets the CI config
		$this->_cfg =& $this->_CI->config;
    }
	
	// @todo Just write classes that weren't already parsed at mapping (using regex or something)
	// @todo Implement parsing of 'field_prefix', detecting if a prefix string is present in all table names
	// @todo Implement parsing of entity relationships using detected FKs
	public function parseMapping ( $ar_name, $path_file=NULL, $write=TRUE )
	{
		// Set mapping and default DB connection
		$this->_mappings = NitroConfig::mapping();
		
		// Get DB connection for given AR name
		$DB = $this->_addDB( $ar_name );

		foreach( $this->listTables($ar_name,$DB) as $table ) // Go through each DB table
		{
			if ( $this->_config["skip_PK_tables"] ) // Skip tables where all its fields are PKs!
			{
				$all_pks = array();
				foreach ( $this->_tableFields($ar_name,$table) as $field )
					$all_pks[ ] = $field->primary_key;
				if ( ! in_array(0, $all_pks) )
					continue;
			}
			
			if ( $DB->dbprefix ) // Table name without prefix
				$table = substr_replace( $table, "", 0, strlen($DB->dbprefix) );
			$class = Nitro::camelize( $table ); // Class name
			
			$classes[ $class ] = "" // Write the resulting map array for this table
				."    '{$class}' => array(\n"
				//."        'ar_name' => '{$ar_name}',\n"
				//."        'db' => '{$DB->database}',\n"
				."        'table' => '{$table}',\n"
				."        'field_prefix' => '',\n"
				."        'row_version' => FALSE,\n"
				."        'relations' => array(\n"
				."            // Add table/entity relationships here!\n"
				."        )\n"
				."    )";
		}

		// Set the complete mapping array
		$map_array = "\$nitro['$ar_name'] = array(\n\n" . implode( ",\n\n", $classes ) . "\n\n);";

		// Sets the template replacements for DB mapping
		$this->replaces = array(
			"{DB_NAME}" => $DB->database,
			"{AR_NAME}" => $ar_name,
			"{GEN_DATETIME}" => date("Y-m-d H:i:s"),
			"{MAPPING_ARRAY}" => $map_array
		);
		
		// Adds the DBMappingConfig template
		$tpl_path = NITRO_PATH."tpl/DBMappingConfig.tpl";
		if( ! file_exists( $tpl_path ) )
			$tpl_path = NITRO_SPARK_PATH."tpl/DBMappingConfig.tpl";

		$this->_addInput( $tpl_path );
		
		// Sets the output file to write the DB mapping
		$this->_setOutput( $path_file ? $path_file : APPPATH."config/nitro_mapping_{$ar_name}_".date("Y-m-d").EXT );

		// Parse the file
		return array(
			"source" => $this->parse( $write, $write ),
			"ar_name" => $ar_name,
			"database" => $DB->database,
			"classes" => array_keys( $classes ),
			"output" => $this->_output
		);
	}
	
	public function parseEntities ( $ar_name, $entity=NULL, $write=TRUE )
	{
		// Set mapping and default DB connection
		$this->_mappings = NitroConfig::mapping();
		
		if ( ! ($mapping = @$this->_mappings[$ar_name]) )
			throw new NitroException("No mapping loaded for Active Record name '$ar_name'.");
		if ( $entity ) // Specific entity given => Remove all other entities from mapping
			$mapping = array( $entity => $mapping[ $entity ] );
		
		// Check output paths
		$this->_checkPath( NITRO_MODELS_PATH, TRUE );
		$this->_checkPath( NITRO_BASE_MODELS_PATH, TRUE );
		
		// Get DB connection for given AR name
		$DB = $this->_addDB( $ar_name );
		
		// Sets the template replacements for BaseModel class
		$this->replaces = array(
			"{AR_NAME}" => $ar_name,
			"{GEN_DATETIME}" => date("Y-m-d H:i:s")
		);
		
		// Adds the BaseClass template
		$basetpl_path = NITRO_PATH."tpl/BaseClass.tpl";
		if( ! file_exists( $basetpl_path ) )
			$basetpl_path = NITRO_SPARK_PATH."tpl/BaseClass.tpl";
		
		$this->_addInput( $basetpl_path );
		$input = $this->_getInput();
		$methods = array(
			"M_GETTER", "M_SETTER",
			"R_INH_GETTER", "R_INH_SETTER",
			"R_ONE_GETTER", "R_ONE_SETTER",
			"R_MANY_GETTER", "R_MANY_SETTER", "R_MANY_CLEANER",
			"R_MANY_ADDER", "R_MANY_REMOVER"
		);
		
		foreach ( $methods as $method )
		{
			preg_match_all( "/{".$method."}(.*){\/".$method."}/uims", $input , $matches, PREG_SET_ORDER );
			$meths[ $method ] = $matches[0][1];
		}
		$methods = $meths;

		preg_match_all( "/(.*){METHODS}.*{\/METHODS}(.*)/uims", $input, $matches, PREG_SET_ORDER );
		$inputBaseClass = $matches[0][1] . "{METHODS}" . $matches[0][2];
		
		foreach( $mapping as $class => $conf ) // Go through each entity definition
		{
			$parsed = $this->_parseClassMethods( $ar_name, $class, $methods );
			if ( ! $parsed ) // No methods parsed => Skip this class
				continue;
			
			$parsedMethods = "";

			if ( $parsed["own"] )
			{
				$parsedMethods .= "\n\n    /*********** Getters/Setters for table fields  ***********/\n\n";
				foreach ( $parsed["own"] as $method => $parses )
					$parsedMethods .= implode( "", $parses );
			}

			if ( $parsed["rels"] )
			{
				$parsedMethods .= "\n\n    /*********** Getters/Setters for relationships  ***********/\n\n";
				foreach ( $parsed["rels"] as $method => $parses )
					$parsedMethods .= implode( "\n", $parses );
			}
			
			$this->replaces["{CLASS_NAME}"] = $class;
			$this->replaces["{METHODS}"] = $parsedMethods;
			$this->replaces["{EXTENDS}"] = "NitroModel";
			foreach ( (array)$conf["relations"] as $rel_type => $rel_confs )
				if ( is_subclass_of( "NitroRelation_$rel_type", "NitroCoreRelation_Inheritance" ) )
					foreach ( (array)$rel_confs as $rel_name => $rel_conf )
						$this->replaces["{EXTENDS}"] = $rel_name;
			
			// Set the Base class template as input
			$this->_inputs = $inputBaseClass;
			
			// Parse Base class content
			$files[ $class ][ NITRO_BASE_MODELS_PATH."Base$class".EXT ] = $this->parse( FALSE );
			
			// Skip existent Final classes!
			if ( file_exists(NITRO_MODELS_PATH."$class".EXT) )
				continue;
			
			// Adds the FinalClass template
			$finaltpl_path = NITRO_PATH."tpl/FinalClass.tpl";
			if( ! file_exists( $finaltpl_path ) )
				$finaltpl_path = NITRO_SPARK_PATH."tpl/FinalClass.tpl";
			
			$this->_inputs = array();
			$this->_addInput( $finaltpl_path );
			
			// Parse Final class content
			$files[ $class ][ NITRO_MODELS_PATH."$class".EXT ] = $this->parse( FALSE );
		}
		
		// Write files
		foreach ( $files as $class => $cfiles )
		{
			foreach ( $cfiles as $file => $contents )
			{
				$this->_inputs = $contents;
				$this->_output = $file;
				$generated["files"][ $file ] = $this->parse( $write, TRUE );
				$generated["classes"][ $class ][ ] = $file;
			}
		}
		
		// Return parsing results
		return array(
			"ar_name" => $ar_name,
			"database" => $DB->database,
			"files" => $generated["files"],
			"classes" => $generated["classes"]
		);
	}
	
	public function parseForms ( $ar_name, $entity=NULL, $write=TRUE, $lang=TRUE )
	{
		// Set mapping and default DB connection
		$this->_forms_conf = NitroConfig::forms( $ar_name );
		$this->_forms = $this->_forms_conf["forms"];
		$this->_forms_conf = $this->_forms_conf["conf"];
		if ( ! $this->_forms )
			throw new NitroException("No forms loaded for Active Record name '$ar_name'.");

		// Check output path
		$this->_checkPath( ( $path = APPPATH."views/nitro/forms/" ) );
		
		// Adds the Form template
		$formtpl_path = NITRO_PATH."tpl/Form.tpl";
		if( ! file_exists( $formtpl_path ) )
			$formtpl_path = NITRO_SPARK_PATH."tpl/Form.tpl";
		$this->_addInput( $formtpl_path );
		$input = $this->_getInput();
		$fields = array(
			"HIDDEN",
			"BOOLEAN",
			"NUMERIC",
			"TEXT",
			"PASSWORD",
			"TEXTAREA",
			"DATETIME",
			"DATE",
			"TIME",
			"YEAR",
			"ENUM",
			"SET",
			"SELECT",
			"SUBMIT",
			"CANCEL"
		);
		
		foreach ( $fields as $key => $field )
		{
			preg_match_all( "/{".$field."}(.*){\/".$field."}/uims", $input , $matches, PREG_SET_ORDER );
			$flds[ $field ] = $matches[0][1];
		}
		$fields = $flds;

		preg_match_all( "/(.*){FIELDS}.*{\/CANCEL}(.*)/uims", $input, $matches, PREG_SET_ORDER );
		$inputBaseClass = $matches[0][1] . "{FIELDS}" . $matches[0][2];
		
		foreach( $this->_forms as $class => $conf ) // Go through each entity definition
		{
			if ( $entity && $class != $entity ) // Just run for given entity
				continue;
			
			$controller = @$conf["controller"] ? $conf["controller"] : strtolower(Nitro::plural($class));
			$this->_method_path = "$".strtolower( $class );
			$parse = $this->_parseClassForms( $class, $conf, $fields );
			$submit = $this->_parseFormSubmit( $conf, $fields["SUBMIT"] );
			$cancel = $this->_parseFormCancel( $conf, $fields["CANCEL"] );
			
			// Sets the template replacements for Form class
			$this->replaces = array(
				"{ACTION}" => "\$action",
				"{METHOD}" => "POST",
				"{ATTR}" => "",
				"{FIELDS}" => $parse . $submit . $cancel
			);
			
			// Set the Form class template as input
			$this->_inputs = $inputBaseClass;
			
			// Parse Form class content
			$files[ $class ][ $path."Form$class".EXT ] = $this->parse( FALSE );
		}
		
		// Write files
		foreach ( $files as $class => $cfiles )
		{
			foreach ( $cfiles as $file => $contents )
			{
				$this->_inputs = $contents;
				$this->_output = $file;
				$generated["files"][ $file ] = $this->parse( $write, TRUE );
				$generated["classes"][ $class ][ ] = $file;
			}
		}
		
		if ( $lang )
			$lang = $this->parseLang( $ar_name );
		
		// Return parsing results
		return array(
			"ar_name" => $ar_name,
			"files" => $generated["files"],
			"classes" => $generated["classes"],
			"lang" => $lang
		);
	}
	
	public function parseLang ( $ar_name, $labels=array() )
	{
		// Check output path
		$this->_checkPath( ( $path = APPPATH."views/nitro/lang/" ) );

		// Adds the Lang template
		$langtpl_path = NITRO_PATH."tpl/Lang.tpl";
		if( ! file_exists( $langtpl_path ) )
			$langtpl_path = NITRO_SPARK_PATH."tpl/Lang.tpl";
		$this->_inputs = array();
		$this->_addInput( $langtpl_path );
		$this->_inputs = $this->_getInput();
		$this->_output = $path.$ar_name."_lang".EXT;
		
		// Set labels
		foreach ( Nitro::mergeArrays( $this->_labels, $labels ) as $label => $value )
			$lang[ ] = "\$lang['$label'] = '".NitroCore::camelize($value)."';";

		// Sets the template replacements for BaseModel class
		$this->replaces = array(
			"{AR_NAME}" => $ar_name,
			"{GEN_DATETIME}" => date("Y-m-d H:i:s"),
			"{LANG_ARRAY}" => implode( "\n", (array)$lang )
		);

		// Parse Lang class content
		$files[ $this->_output ] = $this->parse( TRUE, TRUE );
		
		// Return parsing results
		return array(
			"ar_name" => $ar_name,
			"files" => $files
		);
	}
	
	/**
	 * Parse the content from inputs, applies the replacements and writes all to output
	 * 
	 * @param Bool $write		Whether to write the contents to $this->_output or not
	 * @param type $overwrite	Whether to overwrite the mapping file if found
	 * @return String			The parsed content
	 */
	public function parse ( $write=TRUE, $overwrite=FALSE )
    {
		if ( ! $this->_inputs )
			throw new NitroException("There are no input files to parse.");

		if ( ! $overwrite && file_exists($this->_output) )
			throw new NitroException("The file '$this->_output' already exists and it won't be overwritten.");

		// Get all the current inputs together
		$parsed = $this->_getInput();
		if ( $this->replaces ) // Apply replacements
			$parsed = str_replace( array_keys($this->replaces), array_values($this->replaces), $parsed );

		if ( $write )
		{
			$fp = @fopen( $this->_output, 'w+b' );
			if ( ! $fp )
				throw new NitroException("Couldn't create or open file '$this->_output'.");
			$rs = @fwrite( $fp , $parsed );
			if ( ! $rs )
				throw new NitroException("Couldn't write to file '$this->_output'.");

			@chmod( $this->_output, $this->_config["permissions"] );
		}
		
		return $parsed;
    }
	
	public function listTables ( $ar_name, $DB=NULL )
	{
		if ( ! $DB ) // Get DB connection for given AR name
			$DB = $this->_addDB( $ar_name );
		
		$tables = $DB->list_tables();
		if ( ! $tables )
			throw new NitroException("Cannot get tables from DB '{$DB->database}'.");

		// Should we skip CI sessions table..?
		if ( $this->_config["skip_CI_session_table"] && ($pos = array_search($this->_cfg->item("sess_table_name"),$tables)) )
			unset( $tables[ $pos ] ); // CI sessions table found => Remove it!
		
		return $tables;
	}
	
	protected function _parseClassMethods ( $ar_name, $class, $input )
	{
		// Get entity mapping conf, set the table and its fields
		$map = $this->_mappings[ $ar_name ][ $class ];
		$table = $map['table'];
		$fields = $this->_tableFields( $ar_name, $table );
		
		foreach ( $fields as $field )
		{
            // Don't generate methods for row version field!
            if ( ($fname = $field->name) == '__RV__' )
                continue;

			if ( @$map["field_prefix"] ) // Remove field prefix..
				$fname = preg_replace( "/^".$map["field_prefix"]."/i", "", $fname );
			// ..and set the method name
			$method = Nitro::camelize( $fname );

			$rep = array( // Replacements for 'own' class methods
				"{FIELD}" => $field->name,
				"{METHOD}" => $method,
				"{FIELD_NAME}" => $fname
			);

			
			$methods["own"][ $method ]["get"] = str_replace( array_keys($rep), array_values($rep), $input["M_GETTER"] );
			$methods["own"][ $method ]["set"] = str_replace( array_keys($rep), array_values($rep), $input["M_SETTER"] );
		}

		if ( @$map["relations"] ) // There are relationships to parse!
		{
			foreach ( (array)$map["relations"] as $rel_type => $rels )
			{
				$is_inh = NitroRelation::isInheritance( $rel_type );
				$is_many = NitroRelation::isMany( $rel_type );
				$is_one = NitroRelation::isOne( $rel_type );

				foreach ( $rels as $rel_name => $rel_data )
				{
					$method = Nitro::camelize( $rel_name );

					$rep = array( // Replacements for relationship methods
						"{REL_TYPE}" => $rel_type,
						"{REL_NAME}" => $rel_name,
						"{ENTITY}" => $rel_data["entity"],
						"{METHOD}" => $method
					);

					if ( $is_inh )
					{
						$methods["rels"][ $method ]["get"] = str_replace( array_keys($rep), array_values($rep), $input["R_INH_GETTER"] );
						$methods["rels"][ $method ]["set"] = str_replace( array_keys($rep), array_values($rep), $input["R_INH_SETTER"] );
					}
					else if ( $is_one )
					{
						$methods["rels"][ $method ]["get"] = str_replace( array_keys($rep), array_values($rep), $input["R_ONE_GETTER"] );
						$methods["rels"][ $method ]["set"] = str_replace( array_keys($rep), array_values($rep), $input["R_ONE_SETTER"] );
					}
					else if ( $is_many )
					{
						$rep["{METHOD_SINGULAR}"] = Nitro::singular( $method );
						$methods["rels"][ $method ]["get"] = str_replace( array_keys($rep), array_values($rep), $input["R_MANY_GETTER"] );
						$methods["rels"][ $method ]["set"] = str_replace( array_keys($rep), array_values($rep), $input["R_MANY_SETTER"] );
						$methods["rels"][ $method ]["clear"] = str_replace( array_keys($rep), array_values($rep), $input["R_MANY_CLEANER"] );
						$methods["rels"][ $method ]["add"] = str_replace( array_keys($rep), array_values($rep), $input["R_MANY_ADDER"] );
						$methods["rels"][ $method ]["remove"] = str_replace( array_keys($rep), array_values($rep), $input["R_MANY_REMOVER"] );
					}
				}
			}
		}
		
		// Return all parsed methods
		return $methods;
	}
	
	protected function _parseClassForms( $class, $form_conf, $fields_source )
	{
		// Parse entity fields and relationships
		foreach ( $class::fields() as $field => $conf )
		{
			$field_conf = @$form_conf["fields"][ $field ]; // Shortcut ;)
			
			if ( // Weird 'if' to know if we got to ignore the field
				@$field_conf["ignore"] // Ignored by specific field config
					||
				$this->_forms_conf["fields"][ $field ]["ignore"] // Globally ignored by field name
					|| // ..or ( NOT specific field config given AND field is PK AND PKs are globally ignored )
				(!$field_conf && $conf->primary_key && @$this->_forms_conf["pks"]["ignore"]) )
				continue; // OK, ignored!
			
			// Get entity mapping to parse its relationships
			$mapping = $class::mapping();
			
			// Override name of PK fields
			if ( $conf->primary_key && @$this->_forms_conf["pks"]["name"] )
				@$field_conf["name"] = $this->_forms_conf["pks"]["name"];
			// Override hidden of PK fields
			if ( $conf->primary_key && @$this->_forms_conf["pks"]["hidden"] )
				@$field_conf["hidden"] = $this->_forms_conf["pks"]["hidden"];
			// Override type of PK fields
			else if ( $conf->primary_key && @$this->_forms_conf["pks"]["type"] )
				@$field_conf["type"] = $this->_forms_conf["pks"]["type"];
			
			// Override field type by mapping or take default from DB table info
			$type = @$field_conf["type"] ? $field_conf["type"] : $conf->type;
			
			// OK, enough overriding.. start parsing!
			
			// Relation fields
			if ( @$form_conf["fields"][ $field ]["relation"] )
				$fields[ $field ] = $this->_parseFormRelation( $mapping, $form_conf, $class, $field, $field_conf, $fields_source );
			
			// Hidden fields
			else if ( @$field_conf["hidden"] )
				$fields[ $field ] = $this->_parseFormHidden( $class, $field, $field_conf, $fields_source["HIDDEN"] );
			
			// Boolean fields
			else if ( preg_match( "/bit|boolean/i", $type ) || ( $type=="tinyint" && $conf->max_length==1 ) )
				$fields[ $field ] = $this->_parseFormBoolean( $class, $field, $field_conf, $fields_source["BOOLEAN"] );
			
			// Numeric fields
			else if ( preg_match( "/int|decimal|float|double|real|serial/i", $type ) )
				$fields[ $field ] = $this->_parseFormNumeric( $class, $field, $field_conf, $conf, $fields_source["NUMERIC"] );
			
			// Text fields
			else if ( preg_match( "/char|binary/i", $type ) )
				$fields[ $field ] = $this->_parseFormText( $class, $field, $field_conf, $conf, $fields_source["TEXT"] );
			
			// Textarea fields
			else if ( preg_match( "/text|blob/i", $type ) )
				$fields[ $field ] = $this->_parseFormTextArea( $class, $field, $field_conf, $conf, $fields_source["TEXTAREA"] );
			
			// Datetime fields
			else if ( preg_match( "/datetime|timestamp/i", $type ) )
				$fields[ $field ] = $this->_parseFormDateTime( $class, $field, $field_conf, $conf, $fields_source["DATETIME"] );
			
			// Date fields
			else if ( preg_match( "/date/i", $type ) )
				$fields[ $field ] = $this->_parseFormDate( $class, $field, $field_conf, $conf, $fields_source["DATE"] );
			
			// Time fields
			else if ( preg_match( "/time/i", $type ) )
				$fields[ $field ] = $this->_parseFormTime( $class, $field, $field_conf, $conf, $fields_source["TIME"] );
			
			// Year fields
			else if ( preg_match( "/year/i", $type ) )
				$fields[ $field ] = $this->_parseFormYear( $class, $field, $field_conf, $conf, $fields_source["YEAR"] );
			
			// Enum fields
			else if ( preg_match( "/enum/i", $type ) )
				$fields[ $field ] = $this->_parseFormEnum( $class, $field, $field_conf, $conf, $fields_source["ENUM"] );
			
			// Set fields
			else if ( preg_match( "/set/i", $type ) )
				$fields[ $field ] = $this->_parseFormSet( $class, $field, $field_conf, $conf, $fields_source["SET"] );
			
			// Password fields
			else if ( $type == "password" )
				$fields[ $field ] = $this->_parseFormPassword( $class, $field, $field_conf, $conf, $fields_source["PASSWORD"] );
				
		}
		
		
		if ( @$this->_forms_parsed[ $class ] ) // Avoid infinite loops =)
			return "";
		$this->_forms_parsed[ $class ] = TRUE;
		
		// Parse entity relations
		foreach ( $mapping["relations"] as $rel_type => $rels )
		{
			if ( $rel_type == "ManyToOne" ) // Should be already parsed as class field!
				continue;
			
			foreach ( $rels as $rel_name => $rel_conf )
			{
				if ( @$form_conf["relations"][ $rel_name ]["ignore"] ) // Ignored by relation name at form conf
					continue;

				$fields[ $rel_name ] = $this->_parseFormRelation( $mapping, $form_conf, $class, strtolower($rel_name), @$form_conf["relations"][ $rel_name ], $fields_source, $rel_type, $rel_name, $rel_conf );
			}
		}
		
		$this->_forms_parsed[ $class ] = FALSE;
		
		return implode( "\n", (array)$fields );
	}
	
	protected function _parseFormRelation ( $mapping, $form_conf, $class, $field, $field_conf, $fields_source, $rel_type=NULL, $rel_name=NULL, $rel_conf=NULL )
	{
		if ( ! ($rel_type && $rel_name && $rel_conf) ) // Find the relation config that matches given name
			foreach ( @$mapping["relations"] as $rel_type => $rels )
				foreach ( $rels as $rel_name => $rel_conf )
					if ( $rel_name == $field_conf["relation"]["name"] )
						break 2;
		
		// Entity not found!
		if ( ! ($entity = $rel_conf["entity"]) )
			return "";

		$method_path = $this->_method_path;
		$this->_method_path .= "->relation(\"$rel_name\")";
		$attr_name_prefix = $this->_attr_name_prefix;
		$this->_attr_name_prefix .= "$rel_name-";
		
		if ( $rel_type == "Inheritance" ) // Recursive call
			$ret = $this->_parseClassForms( $entity, $this->_forms[$entity], $fields_source );
		
		else if ( $rel_type == "OneToOne" )  // Recursive call
			$ret = $this->_parseClassForms( $entity, $this->_forms[$entity], $fields_source );
		
		else if ( $rel_type == "ManyToOne" )
			$ret = $this->_parseFormRelationManyToOne( $rel_name, $entity, $class, $field, $field_conf, $fields_source );
		
		else if ( $rel_type == "OneToMany" )
			$ret = $this->_parseFormRelationOneToMany( $rel_name, $entity, $class, $field, $field_conf, $fields_source );
		
		else if ( $rel_type == "ManyToMany" )
			$ret = $this->_parseFormRelationManyToMany( $rel_name, $entity, $class, $field, $field_conf, $fields_source );
		
		$this->_method_path = $method_path;
		$this->_attr_name_prefix = $attr_name_prefix;
		
		return $ret;
	}
	
	protected function _parseFormRelationManyToOne ( $rel_name, $entity, $class, $field, $field_conf, $fields_source )
	{
		// Parse relation as a select
		$var = "$".strtolower( $entity );
		foreach ( (array) $field_conf["relation"]["fields"] as $r_field )
			$text[ ] = $var."->get(\"$r_field\")";		
		$text = "<?=implode( \", \", array(".implode(",",$text).") )?>";
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $rel_name;
		
		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
			"{COLL}" => $entity."::all() as ".$var,
			"{COLL_VALUE}" => "<?=implode( \",\", (array)".$var."->PK() )?>",
			"{COLL_SELECTED}" => $var."->PK() == \$".strtolower($class)."->relation(\"".$rel_name."\",TRUE)->PK()",
			"{COLL_TEXT}" => $text
		);
		
		return str_replace( array_keys($replace), array_values($replace), $fields_source["SELECT"] );
	}
	
	protected function _parseFormRelationOneToMany ( $rel_name, $entity, $class, $field, $field_conf, $fields_source )
	{
		// Parse relation as a select
		$var = "$".strtolower( $entity );
		foreach ( (array) $field_conf["relation"]["fields"] as $r_field )
			$text[ ] = $var."->get(\"$r_field\")";		
		$text = "<?=implode( \", \", array(".implode(",",$text).") )?>";
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $rel_name;
		
		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name."[]",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
			"{COLL}" => $entity."::all() as ".$var,
			"{COLL_VALUE}" => "<?=implode( \",\", (array)".$var."->PK() )?>",
			"{COLL_SELECTED}" => $var."->PK() == \$".strtolower($class)."->relation(\"".$rel_name."\",TRUE)->PK()",
			"{COLL_TEXT}" => $text
		);
		
		return str_replace( array_keys($replace), array_values($replace), $fields_source["SELECT"] );
	}
	
	protected function _parseFormRelationManyToMany ( $rel_name, $entity, $class, $field, $field_conf, $fields_source )
	{
		// Parse relation as a select
		return $this->_parseFormRelationOneToMany( $rel_name, $entity, $class, $field, $field_conf, $fields_source );
	}
	
	protected function _parseFormHidden ( $class, $field, $field_conf, $field_source )
	{
		$name = $field_conf["name"] ? $field_conf["name"] : $field;
		
		$replace = array(
			"{NAME}" => $this->_attr_name_prefix.name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] )
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormBoolean ( $class, $field, $field_conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;
		
		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{CHECKED}" => $this->_method_path."->get(\"$field\")",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormNumeric ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}

	protected function _parseFormText ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;
		
		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormTextArea ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormDateTime ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormDate ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormTime ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormYear ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormEnum ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$values = @$field_conf["values"] ? implode(",",(array)$field_conf["values"]) : $conf->values;
		$this->_labels[ $label ] = $name;
		
		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUES}" => $values,
			"{CHECKED}" => $this->_method_path."->get(\"$field\")",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormSet ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUES}" => $conf->values,
			"{CHECKED}" => $this->_method_path."->get(\"$field\")",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormPassword ( $class, $field, $field_conf, $conf, $field_source )
	{
		$name = @$field_conf["name"] ? $field_conf["name"] : $field;
		$label = @$field_conf["label"]["value"] ? $field_conf["label"]["value"] : "LBL_".strtoupper($class)."_".strtoupper($name);
		$this->_labels[ $label ] = $name;

		$replace = array(
			"{LABEL_ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["label"]["attr"] ),
			"{LABEL}" => $label,
			"{NAME}" => $this->_attr_name_prefix.$name,
			"{VALUE}" => "<?=".$this->_method_path."->get(\"$field\")?>",
			"{LENGTH}" => $conf->max_length,
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormSubmit ( $field_conf, $field_source )
	{
		$replace = array(
			"{NAME}" => $field_conf["submit"]["name"] ? $field_conf["submit"]["name"] : "",
			"{VALUE}" => @$field_conf["submit"]["value"] ? $field_conf["submit"]["value"] : "LBL_SUBMIT",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["submit"]["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormCancel ( $field_conf, $field_source )
	{
		$replace = array(
			"{VALUE}" => @$field_conf["cancel"]["value"] ? $field_conf["cancel"]["value"] : "LBL_CANCEL",
			"{ATTR}" => $this->_parseFormFieldAttributes( @$field_conf["cancel"]["attr"] ),
		);

		return str_replace( array_keys($replace), array_values($replace), $field_source );
	}
	
	protected function _parseFormFieldAttributes ( $attributes )
	{
		foreach ( (array) $attributes as $attr => $value )
			$attrs[ ] = $attr .'="'.$value.'"';
		
		return ($attrs ? " " : "") . implode( " ", (array)$attrs );
	}
	
	/**
	 * Adds a new database connection from config/database.php defined connections
	 * 
	 * @param String $ar_name	Active Record name with DB settings
	 * @return CI_DB			CI DB object
	 */
	protected function _addDB ( $ar_name )
	{
		if ( ! $this->_db[$ar_name] )
		{
			$this->_db[ $ar_name ] = $this->_CI->load->database( $ar_name, TRUE );
			if ( ! $this->_db[$ar_name] )
				throw new NitroException("Could not load DB under Active Record name '$ar_name'");
		}
		
		return $this->_db[ $ar_name ];
	}
	
	protected function _addInput ( $file )
	{
		$this->_inputs[ ] = $this->_checkFile( $file );
	}
	
	public function _getInput ( )
	{
		if ( is_array($this->_inputs) )
			foreach ( $this->_inputs as $file )
				$input .= file_get_contents( $file );
		else
			return $this->_inputs;
		
		return $input;
	}
	
	protected function _setOutput ( $file )
	{
		if ( ! preg_match("/(\/?.*\/)?(.*)\.\w*$/i", $file, $matches) )
			throw new NitroException("Wrong output file '$file' given.");
		
		$this->_checkPath( $matches[1] );
		
		$this->_output = $file;
	}
	
	/**
	 * Gets all fields for given $table, located at given $ar_name
	 * 
	 * @param String $table		Table name to get its field
	 * @param String $ar_name	Active Record name for DB where the table is located
	 * @return Array			All table fields data
	 */
	protected function _tableFields ( $ar_name, $table )
	{
		// Get DB connection for given AR name
		$DB = $this->_addDB( $ar_name );
		
		if ( ! $this->_fields[$ar_name][$table] ) // Cache the table fields
		{
			if ( $DB->table_exists($table) )
				$this->_fields[ $ar_name ][ $table ] = $this->_db[ $ar_name ]->field_data( $table );
			else
				throw new NitroException("The table '$table' doesn't exist in DB '{$DB->database}' on AR name '$ar_name'");
		}

		return $this->_fields[ $ar_name ][ $table ];
	}
	
	protected function _checkPath ( $path, $create = FALSE )
	{
		if( $create && ! file_exists( $path ) )
		{
			// Find parent path
			$dirs = explode('/', $path);
			$dirs = array_slice( $dirs, 0, count($dirs)-2 );
			$parent_path = implode('/', $dirs).'/';

			if( ! is_writeable( $parent_path ) )
				throw new NitroException("The application's 'models' directory isn't writeable!");
			
			mkdir( $path, 0755 );
		}

		if ( ! is_writeable($path) )
			throw new NitroException("The output directory '$path' doesn't exist or isn't writeable.");

		return $path;
	}

	protected function _checkFile ( $file )
	{
		if ( ! is_readable($file) )
			throw new NitroException("The file '$file' doesn't exist.");

		return $file;
	}
}