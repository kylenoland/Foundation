<?php

class NitroCoreRelation_Inheritance extends NitroRelation_OneToOne
{
	public function __construct( NitroModel $ent )
	{
		NitroRelation::__construct( $ent );
		
		foreach( $this->_map as $method_name => $conf )
			$this->_master = $method_name; // If there are more than one, the user is going apeshit
	}
	
	/**
	 * Gets the parent entity
	 * 
	 * @return NitroModel
	 */
	public function getRelated( $instance = TRUE )
	{
		if ( @$this->_related[ $this->_master ] )
			return $this->_related[ $this->_master ];
		
		if ( ! parent::getRelated( $this->_master ) )
		{
			if( ! $instance )
				return NULL;
			
			$class = $this->_map[ $this->_master ]['entity'];
			$this->setRelated( new $class );
		}
		
		return $this->_related[ $this->_master ];
	}
	
	/**
	 * Sets the parent entity
	 * 
	 * @param NitroModel $related
	 * @return NitroModel
	 */
    public function setRelated( NitroModel $related )
	{
		return parent::setRelated( $this->_master, $related );
	}
	
	/**
	 * Saves related entity/ies AFTER main entity save()
	 * 
	 * @return Bool
	 */
	public function savePost( $force_insert = FALSE )
	{
		return TRUE;
	}
	
	/**
	 * Deletes related entity/ies BEFORE main entity delete()
	 * 
	 * @return Bool
	 */
    public function deletePre()
	{
		// Remove the parent entity since its existance depends on this child existance
		if ( $this->_map[ $this->_master ]['delete'] )
			return $this->getRelated()->delete();
			
		return TRUE;
	}
}