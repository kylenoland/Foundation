<?php

abstract class NitroCoreRelation
{
	protected $_main;
	protected $_main_class;
	protected $_related;
	protected $_map;
	
	public function __construct( NitroModel $ent )
	{
		$this->_main = $ent;
		$this->_main_class = get_class( $ent );
		
		// Get main entity relations mapping
		$map =& $ent::mapping('relations');
		$this->_map =& $map[ $this->_getRelName() ];
	}
	
	/**
	 * Gets the related entity/ies
	 * 
	 * @param $meth_name	The relationship method name to get
	 * @return NitroModel|NitroCollection
	 */
	public function getRelated( $meth_name )
	{
		return $this->_related[ $meth_name ];
	}
	
	/**
	 * Sets the related entity/ies
	 * 
	 * @param String $meth_name		The relationship method name to set
	 * @param NitroModel|NitroCollection $related
	 */
	public function setRelated( $meth_name, $related )
	{		
		$this->_related[ $meth_name ] = $related;
		return $this->_main;
	}
	
	/**
	 * Clears the related entity/ies
	 * 
	 * @param String $meth_name		The relationship method name to clear
	 */
	public function clearRelated( $meth_name )
	{		
		$this->_related[ $meth_name ] = NULL;
	}
	
	/**
	 * Saves related entity/ies BEFORE main entity save()
	 * 
	 * @return Bool
	 */
    public function savePre( $force_insert = FALSE ) {
		return TRUE;
	}
	
	/**
	 * Saves related entity/ies AFTER main entity save()
	 * 
	 * @return Bool
	 */
	public function savePost( $force_insert = FALSE ) {
		return TRUE;
	}
	
	/**
	 * Deletes related entity/ies BEFORE main entity delete()
	 * 
	 * @return Bool
	 */
    public function deletePre() {
		return TRUE;
	}
	
	/**
	 * Deletes related entity/ies AFTER main entity delete()
	 * 
	 * @return Bool
	 */
	public function deletePost() {
		return TRUE;
	}
	
	protected function _getRelName()
	{
		return str_replace( "NitroRelation_", "", get_class($this) );
	}
	
	public static function getRelName( $rel_type )
	{
		return "NitroRelation_$rel_type";
	}
	
	public static function isInheritance( $rel_type )
	{
		return is_subclass_of( "NitroRelation_$rel_type", "NitroCoreRelation_Inheritance" );
	}
	
	public static function isOne( $rel_type )
	{
		return
			is_subclass_of( "NitroRelation_$rel_type", "NitroCoreRelation_OneToOne" )
			||
			is_subclass_of( "NitroRelation_$rel_type", "NitroCoreRelation_ManyToOne" );
	}
	
	public static function isMany( $rel_type )
	{
		return is_subclass_of( "NitroRelation_$rel_type", "NitroCoreRelation_OneToMany" );
	}
}