<?php

class NitroCoreRelation_ManyToMany extends NitroRelation_OneToMany
{	
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
			
			if ( ( $pks = $this->_main->PK() ) )
			{
				$main = $this->_main;
				$junction = $this->_map[ $meth_name ]['junction'];
				if ( class_exists($junction) && is_subclass_of( $junction, 'NitroModel' ) )
					$junction = $junction::DB()->database.".".$junction::table();
				$fields = (array) $this->_map[ $meth_name ]['fields'];
				$dbname = $entity::DB()->database;
				$table = $entity::table();
				$pks = (array) $pks;

				// Build where for PKs
				$pkefs = (array) $main::PKfields();
				foreach( (array)$fields[ $this->_main_class ] as $idx => $pkjef )
					$pk_where["$junction.$pkjef"] = $pks[$idx];
				
				$pkefs = (array) $entity::PKfields();
				foreach( (array)$fields[ $entity ] as $idx => $pkjef )
					$join[ ] = "$junction.$pkjef = $dbname.$table.{$pkefs[$idx]}";
				$join = implode( " AND ", $join );
				
				foreach ( $pkefs as $pkef )
					$select[ ] = "$table.$pkef";
				
				$q = $entity::DB()
					->select( $select )
					->join( $junction, $join )
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
	 * Saves related entity/ies BEFORE main entity save()
	 * 
	 * @return Bool
	 */
    public function savePre( $force_insert = FALSE )
	{
		foreach ( (array)$this->_related as $meth_name => $col )
			$col->save();
	}
	
	/**
	 * Saves related entity/ies AFTER main entity save()
	 * 
	 * @return Bool
	 */
	public function savePost( $force_insert = FALSE )
	{
		$main = $this->_main;
		
		foreach ( (array)$this->_related as $meth_name => $col )
		{
			$entity = $this->_map[ $meth_name ]['entity'];
			$junction = $this->_map[ $meth_name ]['junction'];
			if ( class_exists($junction) && is_subclass_of( $junction, 'NitroModel' ) )
				$junction = $junction::DB()->database.".".$junction::table();
			$fields = (array) $this->_map[ $meth_name ]['fields'];
			$dbname = $entity::DB()->database;
			$table = $entity::table();
			$where = $not_in = $_not_in = $unions = array();


			$pks = (array) $main->PK();
			$pkefs = (array) $main::PKfields();
			$pkeefs = (array) $entity::PKfields();

			foreach( (array)$fields[ $this->_main_class ] as $idx => $pkjef )
				$where["$junction.$pkjef"] = $pks[$idx];
			
			foreach( $col as $i => $e )
			{
				foreach( (array)$fields[ $entity ] as $idx => $pkjef )
				{
					$pk = $entity::DB()->escape( $e->get( $pkeefs[$idx] ) );
					$ases[ $idx ] = "$pk AS _$idx";
					$_not_in[ $pkjef ][ ] = $pk;
				}
				
				$unions[ ] = "SELECT " . implode( ",", $ases );
				
			}
			
			foreach( $_not_in as $pkjef => $pks )
				$not_in[ ] = "$junction.$pkjef NOT IN (". implode( ",", $pks ) .")";
			$not_in = implode(' AND ', $not_in );
			
			// Delete links of removed entities
			$q = $entity::DB()
				->where( $where )
				->where( $not_in )
			->delete( $junction );
			
			if ( $unions )
			{
				// Insert new relation link
				$pkfs_A = implode(',', (array)$fields[ $this->_main_class ] );
				$pkfs_B = implode(',', (array)$fields[ $entity ] );

				$sq_where = array();
				array_walk( $where, function ( $val, $idx, $arr ) {
					$arr[0][] = "$idx = " . $arr[1]::DB()->escape($val);
				}, array( &$sq_where, $entity ) );

				$sq = "SELECT $pkfs_B FROM $junction WHERE " . implode( ',', $sq_where );

				foreach ( array_keys($ases) as $idx )
					$pkwhere[ ] = "p._$idx NOT IN ( $sq )";

				$sql = "INSERT INTO $junction ( $pkfs_B, $pkfs_A )
					SELECT *, '" . implode( "','", (array) $main->PK() ) . "'
					FROM ( ". implode( "\nUNION\n", $unions ) ." ) p
					WHERE " . implode( " AND ", $pkwhere );

				$q = $entity::DB()->query( $sql );//echo "WTF ";
			}
		}
	}
	
	public function add( $meth_name, NitroModel $ent )
	{
		$this->getRelated( $meth_name )->add( $ent );
		
		return $this->_main;
	}
	
	public function remove( $meth_name, NitroModel $ent )
	{
		$this->getRelated( $meth_name )->remove( $ent );
		
		return $this->_main;
	}
}
