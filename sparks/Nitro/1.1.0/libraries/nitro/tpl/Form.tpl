<form action="<?=site_url({ACTION})?>" method="{METHOD}"{ATTR}>

{FIELDS}

	{HIDDEN}
	<input type="hidden" name="{NAME}" value="{VALUE}"{ATTR}/>
	{/HIDDEN}
	
	{BOOLEAN}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="checkbox" name="{NAME}" value="1"<?=({CHECKED}?' checked':'')?>{ATTR}/>
	<br/>
	{/BOOLEAN}
	
	** Same as TEXT for now =)
	{NUMERIC}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="text" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/NUMERIC}
	
	{TEXT}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="text" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/TEXT}
	
	{PASSWORD}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="password" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/PASSWORD}
	
	{TEXTAREA}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<textarea name="{NAME}" maxlength="{LENGTH}"{ATTR}>{VALUE}</textarea>
	<br/>
	{/TEXTAREA}
	
	** Same as TEXT for now =)
	{DATETIME}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="text" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/DATETIME}
	
	** Same as TEXT for now =)
	{DATE}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="text" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/DATE}
	
	** Same as TEXT for now =)
	{TIME}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="text" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/TIME}
	
	** Same as TEXT for now =)
	{YEAR}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<input type="text" name="{NAME}" value="{VALUE}" maxlength="{LENGTH}"{ATTR}/>
	<br/>
	{/YEAR}
	
	** We want only one value to be selected from declared ENUM values..
		..so we use a radio for each ENUM type (all with the same name="{NAME}")
	{ENUM}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<? foreach ( (array)explode(",","{VALUES}") as $val ) { ?>
	<span><?=_t($val)?></span> <input type="radio" name="{NAME}" value="<?=$val?>"<?=($val == {CHECKED}?' checked':'')?>{ATTR}/>
	<? } ?>
	<br/>
	{/ENUM}
	
	** Multiple values could be selected from declared SET values..
		.. so we use a checkbox for each SET type (name="{NAME}[]" so its array)
	{SET}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<? foreach ( (array)explode(",","{VALUES}") as $val ) { ?>
	<span><?=$val?></span> <input type="checkbox" name="{NAME}[]" value="<?=$val?>"<?=($val == {CHECKED}?' checked':'')?>{ATTR}/>
	<? } ?>
	<br/>
	{/SET}	

	** This select could act as a select multiple if proper {ATTR} is set!
	{SELECT}
	<label{LABEL_ATTR}><?=_t("{LABEL}")?></label>
	<select name="{NAME}"{ATTR}>
		<option>- select -</option>
		<? foreach ( {COLL} ) { ?>
		<option value="{COLL_VALUE}"<?=({COLL_SELECTED}?' selected':'')?>>{COLL_TEXT}</option>
		<? } ?>
	</select>
	<br/>
	{/SELECT}

{/FIELDS}

{SUBMIT}
	<button type="submit" name="{NAME}"{ATTR}><?=_t("{VALUE}")?></button>
{/SUBMIT}

{CANCEL}
	<button type="button" class="history-back"{ATTR}><?=_t("{VALUE}")?></button>
{/CANCEL}

</form>