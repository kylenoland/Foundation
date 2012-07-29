<?php

class NitroCoreRelation_ManyToOne extends NitroRelation
{
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
			$entity = $this->_map[ $meth_name ]['entity'];
			$fields = (array) $this->_map[ $meth_name ]['fields'];
			
			foreach( $fields as $pkf )
				$pk[ ] = $main->get( $pkf );
			
			if( $pk )
				$this->_related[ $meth_name ] = $entity::findByPK( $pk );
			
			if ( $instance && ! $this->_related[ $meth_name ] )
			{
				$o = new $entity;
				if ( $pk )
					$o->PK( $pk );
				
				$this->_related[ $meth_name ] = $o;
			}
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
		foreach ( (array)$this->_related as $meth_name => $ent )
		{
			$ent->save();
			$pks = (array) $ent->PK();
			foreach( (array) $this->_map[ $meth_name ]['fields'] as $idx => $pkf )
				$this->_main->set( $pkf, $pks[ $idx ] );
		}
		
		return TRUE;
	}
	
	/**
	 * Deletes related entity/ies AFTER main entity delete()
	 * 
	 * @return Bool
	 */
	public function deletePost()
	{
		foreach ( (array)$this->_map as $meth_name => $conf )
		{
			if( ! ( @$conf['empty_delete'] && @$conf['fields'] && ( $ent = $this->getRelated( $meth_name ) ) ) )
				continue;
			
			// Delete the related entity if it's not assigned to any other main class
			$pks = (array) $ent->PK();
			foreach( (array) @$conf['fields'] as $idx => $pkf )
				$where[ $pkf ] = $pks[ $idx ];
			
			if( $where )
			{
				$main = $this->_main;
				if ( ! $main::exists( $where ) ) // Not other instance ofMany has thisOne assigned
					$ent->delete(); // Delete theOne related entity!
			}
		}
		
		return TRUE;
	}
}