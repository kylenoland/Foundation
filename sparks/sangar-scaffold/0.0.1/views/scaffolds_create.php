<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

  <head>
  <title></title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv='expires' content='1200' />
  <meta http-equiv='content-language' content='es' />

  <base href="<?php echo $this->config->item('base_url') ?>/public/" />
  
  <link rel="stylesheet" href="css/reset.css" type="text/css" media="screen" />
  <link rel="stylesheet" href="css/bstyles.css" type="text/css" media="screen" />
  <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.3/themes/smoothness/jquery-ui.css" type="text/css" media="screen" />

  <script src="http://code.jquery.com/jquery-latest.min.js" type="text/javascript"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.21/jquery-ui.min.js" type="text/javascript"></script>
  

  <script>
    $(function() {$('#examples').accordion({autoHeight: false,navigation: true, collapsible:true, active:false});});
  </script>

  <style>

    body{
        background: #E8E8DD;
        font-family: Helvetica,Arial,Tahoma,sans-serif;
        font-size:11pt;
    }

    .ui-widget-content{font-size:12px;}

    .ui-state-highlight{height:100%;}

    #controller_name, #model_name{
      width:300px !important;
    }

    .scaffold_textarea{
        width:600px !important;
        height:400px !important;
    }

    .error{
      color: red;
    }

    .forminfo{
        color:#444;
        font-size:13px;
        font-style: italic;
    }

  </style>
  
</head>

<body>

  <div id='content-top'>
      <h2>Sangar Scaffolds</h2>
      <span class='clearFix'>&nbsp;</span>
  </div>



<?php

    $message = $this->session->flashdata('message');

    if($message)
    {

      if ( is_array($message['text']))
      {
          echo "<div class='msg_".$message['type']."'>";

                echo "<ul>";

                foreach ($message['text'] as $msg) {
                    echo "<li><span>".$msg."</span></li>";
                }

                echo "<ul>";

          echo "</div>";
      }
      else
      {
          echo "<div class='msg_".$message['type']."'>";

              echo "<span>".$message['text'] . "</span>";

          echo "</div>";
      }

    }
  ?>

  <div style='width:600px;float:left;margin-left:30px;'>

    <?php 
    $attributes = array('class' => 'tform', 'id' => '');
    echo form_open_multipart('scaffolds/scaffolds/create', $attributes); 
    ?>


    <p>
      <label class='labelform' for="controller_name"><?=lang('scaffolds_cont_name')?> <span class="required">*</span></label><br/>

      <input id="controller_name" type="text" name="controller_name" maxlength="256" value="<?php echo set_value('controller_name'); ?>"  />
      
      <br><?php echo form_error('controller_name'); ?>

    </p>

    <p>
      <label class='labelform' for="model_name"><?=lang('scaffolds_mod_name')?> <span class="required">*</span></label><br/>

      <input id="model_name" type="text" name="model_name" maxlength="256" value="<?php echo set_value('model_name'); ?>"  />
      
      <br><?php echo form_error('model_name'); ?>

    </p>


    <p>
      <label class='labelform' for="scaffold_code">Scaffold code <span class="required">*</span><br></label>

      <textarea id="scaffold_code"  name="scaffold_code"  rows='80' class='scaffold_textarea' /><?php echo set_value('scaffold_code', ''); ?></textarea>

      <br><?php echo form_error('scaffold_code'); ?><br>

      <span class='forminfo'><?=lang('scaffolds_code_info')?></span>

    </p>

    <br><br>
    
    <p>
      <?=lang('web_options')?>:<br><br>
      <input type='checkbox' checked name='scaffold_delete_bd' id='scaffold_delete_bd' value="<?php echo set_value('scaffold_delete_bd', '1'); ?>" /> <label class='labelforminline' for="scaffold_delete_bd"><?=lang('scaffolds_delete_bd')?></label><br/>
      <input type='checkbox' checked name='scaffold_bd' id='scaffold_bd' value='<?php echo set_value('scaffold_bd' , '1'); ?>' /> <label class='labelforminline' for="scaffold_bd"><?=lang('scaffolds_create_bd')?></label><br/>
      <input type='checkbox' checked name='scaffold_routes' id='scaffold_routes' value='<?php echo set_value('scaffold_routes' , '1'); ?>' /> <label class='labelforminline' for="scaffold_routes"><?=lang('scaffolds_modify_routes')?></label><br/>

      <br/><br/>

      <input type='checkbox' checked name='create_controller' id='create_controller' value='<?php echo set_value('create_controller', '1'); ?>' /> <label class='labelforminline' for="create_controller"><?=lang('scaffolds_create_controller')?></label><br/>
      <input type='checkbox' checked name='create_model' id='create_model' value='<?php echo set_value('create_model', '1'); ?>' /> <label class='labelforminline' for="create_model"><?=lang('scaffolds_create_model')?></label><br/>
      <input type='checkbox' checked name='create_view_create' id='create_view_create' value='<?php echo set_value('create_view_create', '1'); ?>' /> <label class='labelforminline' for="create_view_create"><?=lang('scaffolds_create_view_create')?></label><br/>
      <input type='checkbox' checked name='create_view_list' id='create_view_list' value='<?php echo set_value('create_view_list', '1'); ?>' /> <label class='labelforminline' for="create_view_list"><?=lang('scaffolds_create_view_list')?></label>
    </p>

    <p>
        <?php echo form_submit( 'submit', 'Scaffold!',  "class='bcreateform'"); ?>
    </p>


    <?php echo form_close();?>

  </div>


  <div id='examples' style='width:300px;float:left;margin-left:30px;'>

  <h3><a href="#">Text</a></h3>
  <div>
  <pre>
  "name":
  {
    "type"          :   "text",
    "minlength"     :   "0",
    "maxlength"     :   "60",
    "required"      :   "FALSE",
    "multilanguage" :   "FALSE",
    "is_unique"     : "FALSE"
  }
  </pre>
  </div>

  <h3><a href="#">Textarea</a></h3>
  <div>
  <pre>
  "description":
  {
    "type"           : "textarea",
    "minlength"      : "0",
    "maxlength"      : "500",      
    "required"       : "FALSE",
    "multilanguage"  : "FALSE"
  }
  </pre>
  </div>  

  <h3><a href="#">Checkbox</a></h3>
  <div>
  <pre>
  "public" :
  {
    "type"      : "checkbox",
    "required"  : "FALSE",
    "checked"   : "FALSE",
    "label"     : "Is public?"    
  }
  </pre>
  </div>

  <h3><a href="#">Select</a></h3>
  <div>
  <pre>
  "language":
  {
    "type"               : "select",
    "size"               : "1", 
    "required"           : "FALSE",
    "option_choose_one"  : "TRUE",
    "with_translations"  : "FALSE",
    "options": 
    {
      "0" : 
      {
        "text"      : "Spanish",                                        
        "selected"  : "TRUE",
        "value"     : "spanish"
      }, 
      "1" : 
      {
        "text"      : "English",                                        
        "selected"  : "FALSE",
        "value"     : "english"
      }
    }
  }
  </pre>
  </div>  


  <h3><a href="#">Select 1:N</a></h3>
  <div>
  <pre>
  "category_id": 
  {                                        
    "type"        : "selectbd",
    "size"        : "1", 
    "required"    : "TRUE",
    "options": 
    {
      "model"         : "Category",
      "field_value"   : "id",
      "field_text"    : "name",
      "order"         : "name ASC"
    }
  } 
  </pre>
  </div>

  <h3><a href="#">Radio Buttons</a></h3>
  <div>
  <pre>
  "gender": 
  {
    "type"        : "radio",
    "required"    : "FALSE",
    "checked"     : "male",
    "options": 
    {
      "0": 
      {
        "label"   : "Male",                                      
        "value"   : "male"
      }, 
      "1": 
      {
        "label"   : "Female",
        "value"   : "female"
      } 
    }
  } 
  </pre>
  </div>


  <h3><a href="#">Image</a></h3>
  <div>
  <pre>
  "user_image": 
  {
    "type"            : "image",
    "required"        : "FALSE",
    "multilanguage"   : "FALSE",
    "upload": 
    {
      "allowed_types"   : "gif|jpg|png",                                      
      "encrypt_name"    : "TRUE",
      "max_width"       : "2000",
      "max_height"      : "1500",
      "max_size"        : "2048"
    },
    "thumbnail":
    {
     "maintain_ratio"   :  "FALSE",
     "master_dim"       : "width", 
     "width"            : "100", 
     "height"           : "100"
    }
  } 
  </pre>
  </div>

  <h3><a href="#">File</a></h3>
  <div>
  <pre>
  "file": 
  {
    "type"            : "file",
    "required"        : "FALSE",
    "multilanguage"   : "FALSE",
    "upload": 
    {
      "allowed_types"  : "pdf",                                      
      "encrypt_name"   : "TRUE",
      "max_size"       : "2048"
    }
  } 
  </pre>
  </div>


  <h3><a href="#">Hidden Relational</a></h3>
  <div>
  <pre>
  "category_id": 
  {
    "type"          : "hidden",
    "controller"    : "name_controller",
    "model"         : "name_model"
  } 
  </pre>
  </div>



  </div>

  <div class='clear'></div>

</body>
</html>