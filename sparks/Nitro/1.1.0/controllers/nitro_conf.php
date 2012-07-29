<?php

class Nitro_Conf extends CI_Controller
{
	protected $_gen; // NitroGen instance
	
	public function __construct ( )
	{
		parent::__construct();

		// Loads the Nitro library
		$this->load->library("nitro");
		// Loads URL helper
		$this->load->helper("url");
		// Instanciate NitroGen
		$this->_gen = new NitroGen;
		
		// Load the config/database.php file/s
		$varnames = array('db','active_group','active_record');
        include APPPATH . 'config/database.php';
        
        foreach( $varnames as $varname )
            $vars[$varname] = ${$varname};
?>
<style>
.float-left, .float-right {
	float:left;
	width:50%;
}
.float-right {
	float:right;
}
</style>
<div class="float-left">
<h1>Welcome to Nitro Generator Utility</h1>


<h2>Loaded settings from config/database.php</h2>
<b>Active Record status:</b> <?=$vars['active_record']?"en":"dis"?>abled (from <i>$active_record</i> variable)<br/>
<? if ( ! $vars['active_record'] ) $this->_error("You must enable Active Record in order to use Nitro!"); ?>
<b>Default Active Record group:</b> <?=$vars['active_group']?> (from <i>$active_group</i> variable)<br/>
<b>Defined Active Record groups:</b> (from <i>$db</i> array)<br/>
<ul>
	<li><?=implode( '</li><li>', array_keys($vars['db']) )?></li>
</ul>


<h2>NitroGen Command Center</h2>
<h3>Generate Mappings for loaded Active Record names</h3>
<ul>
<? foreach ( $vars['db'] as $ar_name => $db_conf ) { ?>
	<li><a href="<?=$this->_get_url("mapping/$ar_name")?>"><?=$ar_name?></a></li>
<? } ?>
</ul>

<h3>Generate Entity Classes for loaded Active Record mappings</h3>
<ul>
<? foreach ( NitroConfig::mapping() as $ar_name => $mapping ) { ?>
	<li><a href="<?=$this->_get_url("entities/$ar_name")?>"><?=$ar_name?></a></li>
	<? if ( ! $mapping ) continue; ?>
	<label>Mapped Tables (<?=count($mapping)?>) for <?=$ar_name?></label>
	<ul>
	<? foreach ( $mapping as $entity => $ent_conf ) { ?>
		<li><a href="<?=$this->_get_url("entities/$ar_name/$entity")?>"><?=$entity?> (<?=$ent_conf["table"]?>)</a></li>
	<? } ?>
	</ul>
<? } ?>
</ul>

<h3>Generate Forms for loaded Active Record mappings</h3>
<ul>
<? foreach ( NitroConfig::forms() as $ar_name => $forms ) { ?>
	<li><a href="<?=$this->_get_url("forms/$ar_name")?>"><?=$ar_name?></a></li>
	<? if ( ! ($forms=@$forms["forms"]) ) continue; ?>
	<label>Mapped Forms (<?=count($forms)?>) for <?=$ar_name?></label>
	<ul>
	<? foreach ( $forms as $entity => $ent_conf ) { ?>
		<li><a href="<?=$this->_get_url("forms/$ar_name/$entity")?>"><?=$entity?></a></li>
	<? } ?>
	</ul>
<? } ?>
</ul>

</div>
<?
	}
	
	public function index ( )
	{
		?><div class="float-right"><h2>Please select an item from the list!<h2></div><?
		exit;
	}
	
	public function mapping ( $ar_name )
	{
		try
		{
			
			$rs = $this->_gen->parseMapping( $ar_name );
?>
<div class="float-right">
<h2>Nitro has successfully mapped '<?=count($rs["classes"])?>' entities!</h2>
<b>Output File:</b> <?=$rs["output"]?></br>
<b>Active Record Name:</b> <?=$rs["ar_name"]?></br>
<b>Database Name:</b> <?=$rs["database"]?></br>
<b>Entities:</b> <ul><li><?=implode( "</li><li>", $rs["classes"] )?></li></ul>
<h3>Generated Mapping Source</h3>
<?
			die("<pre>".htmlentities($rs["source"])."</pre></div>");
		}
		catch ( NitroException $e ) {
			$this->_error( $e->getMessage() );
		}
	}
	
	public function entities ( $ar_name, $entity=NULL )
	{
		try
		{
			$rs = $this->_gen->parseEntities( $ar_name, $entity );
?>
<div class="float-right">
<h2>Nitro has successfully parsed '<?=count($rs["files"])?>' files for '<?=count($rs["classes"])?>' entities!</h2>
<b>Active Record Name:</b> <?=$rs["ar_name"]?></br>
<b>Database Name:</b> <?=$rs["database"]?></br>
<b>Processed Model Entities:</b> <ul><li><?=implode( "</li><li>", array_keys($rs["classes"]) )?></li></ul>
<b>Written Model Files:</b> <ul><li><?=implode( "</li><li>", array_keys($rs["files"]) )?></li></ul>
</div>
<?
			exit;
		}
		catch ( NitroException $e ) {
			$this->_error( $e->getMessage() );
		}
	}
	
	public function forms ( $ar_name, $table=NULL )
	{
		try
		{
			$rs = $this->_gen->parseForms( $ar_name, $table );
?>
<div class="float-right">
<h2>Nitro has successfully parsed '<?=count($rs["files"])?>' forms for '<?=count($rs["classes"])?>' entities!</h2>
<b>Active Record Name:</b> <?=$rs["ar_name"]?></br>
<b>Processed Model Entities:</b> <ul><li><?=implode( "</li><li>", array_keys($rs["classes"]) )?></li></ul>
<b>Written Forms Files:</b> <ul><li><?=implode( "</li><li>", array_keys($rs["files"]) )?></li></ul>
<b>Written Labels Language File:</b> <ul><li><?=( ! $rs["lang"] ? "NO" : implode( "</li><li>", array_keys($rs["lang"]["files"]) ) )?></li></ul>
</div>
<?
			exit;
		}
		catch ( NitroException $e ) {
			$this->_error( $e->getMessage() );
		}
	}
	
	protected function _get_url( $url="" )
    {
        foreach ( $this->uri->segments as $segment )
        {
            $segments[] = $segment;
            
            if ( $segment == $this->uri->rsegments[1] )
                return implode( '/', $segments )."/".$url;
        }

        return "";
    }

	protected function _error ( $msg )
	{
		die('<h2 style="color:red">'.$msg.'</h2>');
	}
}