<?php

class NitroCoreCollection implements SeekableIterator, Countable
{
	protected $_class;
	protected $_data = array();
	protected $_pointer = 0;
	protected $_instance;
	protected $_use_pool = 0;

	public function  __construct( $classname, array $data = NULL )
    {
        $this->_class = $classname;
		$this->_instance = new $classname;
		
        $this->data( $data );
    }
	
	public function data( array $data = NULL )
    {
        if( ! is_null( $data ) )
        {
            if( Nitro::isAssoc( $data ) )
                throw new Exception("The array must be a simple list!");

            $this->_data = $data;
			return $this;
        }

        return $this->_data;
    }
	
	public function save()
    {
		$class = $this->_class;
		
        foreach( $this->_data as $pk_or_inst )
		{
			if( $pk_or_inst instanceof NitroModel )
				$rs[] = $pk_or_inst->save();
			elseif( ( $inst = NitroPool::pull ( $class, $pk_or_inst ) ) )
				$rs[] = $inst->save();
		}
		
		return $rs ? ! in_array( FALSE, $rs ) : TRUE;
    }
	
	public function add ( $pk_or_inst )
	{
		$this->_data[ ] = $pk_or_inst;
		
		return $pk_or_inst;
	}
	
	public function remove ( $pk_or_inst )
	{
		if( $pk_or_inst instanceof NitroModel )
			$str = (string)$pk_or_inst;
		
		foreach ( $this->_data as $i => $data )
		{
			if ( $data instanceof NitroModel )
			{
				if( $str && (string)$data != $str )
					continue;
				elseif( ! $str && array_map('strval',(array)$data->PK()) !== array_map('strval',(array)$pk_or_inst) )
					continue;
			}
			else
			{
				if( ! $str && array_map('strval',(array)$data) !== array_map('strval',(array)$pk_or_inst) )
					continue;
				elseif( $str && array_map('strval',(array)$data) !== array_map('strval',(array)$pk_or_inst->PK() ) )
					continue;
			}
			
			// Remove the element off from array
			array_splice( $this->_data , $i, 1 );
			return TRUE;
		}
		
		return FALSE;
	}

    public function get( $pos, $clone=TRUE )
    {
		$p = $this->_pointer;
		$ret = $this->seek( $pos );
		if ( ! $ret )
			return NULL;
		
		$ret = $ret->current();
		$this->_pointer = $p;
		
		return $clone ? clone $ret : $ret;
    }
	
	public function first( $clone = TRUE )
    {
		return $this->get( 0, $clone );
    }

    public function last( $clone = TRUE )
    {
		return $this->get( $this->count()-1, $clone );
    }
	
	public function delete()
    {
        foreach( $this as $ent )
            $ent->delete();
    }
	
	public function rows()
    {
		foreach( $this as $ent )
			$ret[ ] = $ent->row();

		return $ret;
    }
	
	public function length( )
	{
		return $this->count();
	}
	
	public function len( )
	{
		return $this->count();
	}
	
	public function className( )
	{
		return $this->_class;
	}
	
	public function usePool( )
	{
		$this->_use_pool = $this->count();
		
		return $this;
	}
	
	
	/***************** I N T E R F A C E S *****************/
	
	
	public function count( )
	{
		return count( $this->_data );
	}
	
	public function seek( $position )
	{
		if ( ! isset( $this->_data[ $position ] ) )
			throw new OutOfBoundsException("Position '$position' out of bounds.");
		
		$p = $this->_pointer;
		$this->_pointer = $position;
		
		if ( $this->valid() )
			return $this;
		
		$this->_pointer = $p;
		return NULL;
	}
	
    public function current()
	{
		return $this->_instance;
	}
	
	public function key()
	{
		return $this->_pointer;
	}
	
	public function next()
	{
		++ $this->_pointer;

		return $this;
	}
	
	public function rewind()
	{
		$this->_pointer = 0;
		
		return $this;
	}
	
	public function valid()
	{
		if ( ! isset( $this->_data[ $this->_pointer ] ) )
			return FALSE;
		
		$class = $this->_class;
		$pk_or_inst = $this->_data[ $this->_pointer ];
		
		// A model instance given
		if ( $pk_or_inst instanceof NitroModel )
			$row = $pk_or_inst->row();
		else if ( $this->_use_pool-- <= 0 && FALSE != ( $row = $class::searchByPK( $pk_or_inst ) ) )
			;
		// Find instance by PK (uses NitroPool internally)
		else if ( FALSE != ( $inst = $class::findByPK( $pk_or_inst ) ) )
			$row = $inst->row();
		// Not a valid element!
		else
			return FALSE;
		
		$this->_instance->hydrate( $row, TRUE );

		return TRUE;
	}
	
	
	/***************** M A G I C   M E T H O D S *****************/
	
	
	public function __toString ( )
	{
		foreach ( $this as $ent )
			$fld_data[ ] = (string) $ent;

		return $this->_class ." :: ( count: ".count($fld_data)." ) :: [ \n\t".implode(", \n\t",(array)$fld_data)." \n]";
	}
}