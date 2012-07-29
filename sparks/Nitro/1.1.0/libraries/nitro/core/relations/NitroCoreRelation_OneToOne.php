<?php

class NitroCoreRelation_OneToOne extends NitroRelation
{
	protected $_master;
	protected $_slaves = array();
	protected $_setting = FALSE; // If the model is being set

	public function __construct( NitroModel $ent )
	{
		parent::__construct( $ent );
		
		foreach( $this->_map as $method_name => $conf )
		{
			if( $conf['slave'] )
				$this->_slaves[] = $method_name;
			else
				$this->_master = $method_name; // If there are more than one, the user is going apeshit
		}
	}
	
	/**
	 * Gets the related entity/ies
	 * 
	 * @param $meth_name	The relationship method name to get
	 * @return NitroModel
	 */
	public function getRelated( $meth_name, $instance = FALSE )
	{
		if ( ! $this->_related[ $meth_name ] )
		{
			$main = $this->_main;
			$pks = (array)$main->PK();
			$entity = $this->_map[ $meth_name ]['entity'];

			if ( ! ( $fields = (array)$this->_map[ $meth_name ]['fields'] ) )
				$fields = $entity::PKfields(); // FKs are the entity PKs (default)

			foreach( $fields as $idx => $fkf )
				$fk_values[ $fkf ] = @$pks[ $idx ];

			if ( ( $rs = $entity::search( $fk_values ) ) )
			{
				$this->_related[ $meth_name ] = new $entity;
				$this->_related[ $meth_name ]->hydrate( $rs[0] );
			}
			
			if ( $instance && ! $this->_related[ $meth_name ] )
			{
				$o = new $entity;
				if ( $fk_values )
					foreach ( $fk_values as $fkf => $fkv )
						$o->set( $fkf, $fkv );
				
				$this->_related[ $meth_name ] = $o;
			}
		}
		
		return $this->_related[ $meth_name ];
	}
	
	/**
	 * Sets the related entity/ies
	 * 
	 * @param String $meth_name		The relationship method name to get
	 * @param NitroModel $related
	 */
	public function setRelated( $meth_name, NitroModel $related )
	{
		$this->_related[ $meth_name ] = $related;
		
		if ( $related )
		{
			if ( $this->_setting )
				return FALSE;

			$this->_setting = TRUE;
			$entity = $this->_map[ $meth_name ]['entity'];
			$map = $related::mapping("relations");

			foreach ( (array)$map[ $this->_getRelName() ] as $method => $conf )
				if ( $conf["entity"] == $this->_main_class )
					$related->relation( $method )
						->setRelated( $method, $this->_main );

			$this->_setting = FALSE;
		}
			
		return $this->_main;
	}
	
	/**
	 * Saves related entity/ies BEFORE main entity save()
	 * 
	 * @return Bool
	 */
    public function savePre( $force_insert = FALSE )
	{
		if ( FALSE != ( $master = @$this->_related[ $this->_master ] ) )
		{
			$master->save( $force_insert );
			$pks = (array)$master->PK();
			$main = $this->_main;
			if ( ! ( $fields = (array)$this->_map[ $meth_name ]['fields'] ) )
				$fields = $main::PKfields();
			
			// Set PKs values
			foreach( $pks as $idx => $pkf )
				$main->set( $fields[ $idx ], $pkf );
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Saves related entity/ies AFTER main entity save()
	 * 
	 * @return Bool
	 */
	public function savePost( $force_insert = FALSE )
	{
		if ( $this->_slaves )
		{
			$main = $this->_main;
			$pks = (array)$main->PK();
			
			foreach ( $this->_slaves as $meth_name )
			{
				if ( isset( $this->_related[ $meth_name ] ) )
				{
					$slave = $this->_related[ $meth_name ];
					$entity = $this->_map[ $meth_name ]['entity'];
					if ( ! ( $fields = (array)$this->_map[ $meth_name ]['fields'] ) )
						$fields = $entity::PKfields();
					
					// Set PKs values
					foreach( $pks as $idx => $pkf )
						$slave->set( $fields[ $idx ], $pkf );
					
					$slave->save( $force_insert );
				}
			}
			
			return TRUE;
		}
			
		return FALSE;
	}
}