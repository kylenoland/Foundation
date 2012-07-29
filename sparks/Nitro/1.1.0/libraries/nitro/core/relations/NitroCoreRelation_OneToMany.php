<?php

class NitroCoreRelation_OneToMany extends NitroRelation
{
	protected $_old_related = array();
	protected $_added = array();
	protected $_removed = array();
	
	/**
	 * Gets the related entity/ies
	 * 
	 * @param $meth_name	The relationship method name to get
	 * @param $params		Optionally sets where, order_by, group_by, limit_value and limit_offset to filter collection
	 * @return NitroCollection
	 */
    public function getRelated( $meth_name, $params = array() )
	{
		$custom = ! empty( $params );
		
		if ( ! @$this->_related[ $meth_name ] || $custom )
		{
			$entity = $this->_map[ $meth_name ]['entity'];
			$fields = (array) $this->_map[ $meth_name ]['fields'];
			$dbname = $entity::DB()->database;
			$table = $entity::table();
			
			if ( ( $pks = $this->_main->PK() ) )
			{
				// Build where for PKs
				$pks = (array) $pks;
				foreach( $fields as $idx => $field )
					$pk_where[ "$dbname.$table.$field" ] = $pks[ $idx ];
				
				$q = $entity::DB()
					->select( $entity::PKFields() )
					->where( $pk_where );
				
				// Apply where from mapping
				if ( $this->_map[ $meth_name ]['params']['where'] )
					foreach ( (array)$this->_map[ $meth_name ]['params']['where'] as $key => $val )
						if ( is_string($key) )
							$q->where( array( $key => $val ) );
						else
							$q->where( $val, NULL, FALSE );
				// Apply where from method call
				if ( $params["where"] )
					foreach ( (array)$params["where"] as $key => $val )
						if ( is_string($key) )
							$q->where( array( $key => $val ) );
						else
							$q->where( $val, NULL, FALSE );
				
				// Apply order by from mapping
				if ( @$this->_map[ $meth_name ]['params']['order_by'] )
					$q->order_by( $this->_map[ $meth_name ]['params']['order_by'] );
				// Apply order by from method call
				if ( $params["order_by"] )
					$q->order_by( $params["order_by"] );
				
				// Apply group by from mapping
				if ( $this->_map[ $meth_name ]['params']['group_by'] )
					$q->group_by( $this->_map[ $meth_name ]['params']['group_by'] );
				// Apply group by from method call
				if ( $params["group_by"] )
					$q->group_by( $params['group_by'] );
				
				// Apply limit from mapping
				if ( @$this->_map[ $meth_name ]['params']['limit_value'] )
					$q->limit( $this->_map[ $meth_name ]['params']['limit_value'], @$this->_map[ $meth_name ]['params']['limit_offset'] );
				// Apply limit from method call
				if ( $params["limit_value"] )
					$q->limit( $params["limit_value"], @$params["limit_offset"] );
				
				if ( ( $data = $q->get( $table ) ) )
					$data = $data->result_array();
				
				$data = array_map( 'array_values', (array)$data );
			}
			
			$col = new NitroCollection( $entity, $data );
			if ( $custom )
				return $col;
			
			$this->_related[ $meth_name ] = $col;
		}
		
		return $this->_related[ $meth_name ];
	}
	
	/**
	 * Sets the related entity/ies
	 * 
	 * @param String $meth_name		The relationship method name to get
	 * @param NitroCollection $related
	 */
	public function setRelated( $meth_name, NitroCollection $related )
	{
		if ( $related->className() != $this->_main_class )
			throw new NitroException("The collection must be of '{$this->_main_class}' entity.");
		
		// Keep track of old related collections
		$this->_old_related[ $meth_name ] = TRUE;
			
		return parent::setRelated( $meth_name, $related );
	}
	
	public function clearRelated( $meth_name )
	{
		$this->_old_related[ $meth_name ] = TRUE;
		$this->_related[ $meth_name ] = new NitroCollection( $this->_map[ $meth_name ]['entity'] );
		
		return $this->_main;
	}
	
	/**
	 * Saves related entity/ies BEFORE main entity save()
	 * 
	 * @return Bool
	 */
    public function savePre( $force_insert = FALSE )
	{
		if ( ( $pks = $this->_main->PK() ) )
		{
			$main =& $this->_main;
			
			if ( ! empty($this->_old_related) )
			{
				foreach ( $this->_old_related as $meth_name => $dummy )
				{
					$set = array();
					$where = array();
					$entity = $this->_map[ $meth_name ]['entity'];
					$fields = (array) $this->_map[ $meth_name ]['fields'];
					$dbname = $entity::DB()->database;
					$table = $entity::table();
					$pks = (array) $pks;

					foreach( $fields as $idx => $field )
					{
						$set[ "$dbname.$table.$field" ] = NULL;
						$where[ "$dbname.$table.$field" ] = $pks[ $idx ];
					}

					$main::DB()->where( $where );

					if ( @$this->_map[ $meth_name ]['loose'] )
						$main::DB()->set( $set )->update( $table );
					else
						$main::DB()->delete( $table );
				}
			}
			
			if ( ! empty($this->_removed) )
			{
				
				foreach ( (array)$this->_removed as $meth_name => $entities )
				{
					$set = array();
					$many_class = $this->_map[ $meth_name ]['entity'];
					$dbname = $many_class::DB()->database;
					$table = $many_class::table();
					
					foreach( (array)$this->_map[ $meth_name ]['fields'] as $idx => $field )
						$set[ "$dbname.$table.$field" ] = NULL;
					
					foreach ( $entities as $entity )
					{
						$where = array();
						$pk = (array) $entity->PK();
						
						foreach( $entity::PKFields() as $idx => $field )
							$where[ "$dbname.$table.$field" ] = $pk[ $idx ];

						$main::DB()->where( $where );

						if ( @$this->_map[ $meth_name ]['loose'] )
							$main::DB()->set( $set )->update( $table );
						else
							$main::DB()->delete( $table );
					}
				}
			}
		}
		
		return $this->_old_related = array();
	}
	
	/**
	 * Saves related entity/ies AFTER main entity save()
	 * 
	 * @return Bool
	 */
	public function savePost( $force_insert = FALSE )
	{
		$pks = (array) $this->_main->PK();
		
		foreach ( (array)$this->_related as $meth_name => $col )
		{
			$fields = (array) $this->_map[ $meth_name ]['fields'];
			
			foreach ( (array) $this->_added[ $meth_name ] as $ent )
				foreach( $fields as $idx => $field )
					$ent->set( $field, $pks[ $idx ] );
			
			$col->save();
		}
	}
	
	public function add( $meth_name, NitroModel $ent )
	{
		$this->_added[ $meth_name ][ ] = $this->getRelated( $meth_name )->add( $ent );
		
		return $this->_main;
	}
	
	public function remove( $meth_name, NitroModel $ent )
	{
		if ( $this->getRelated( $meth_name )->remove( $ent ) )
			$this->_removed[ $meth_name ][ ] = $ent;
		
		return $this->_main;
	}
}
