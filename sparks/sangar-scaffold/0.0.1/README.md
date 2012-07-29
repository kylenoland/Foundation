#sangar-scaffold-spark

###A new way to do scaffolding (works with php-activerecord and MySql).

Sangar Scaffolds creates the files for CRUD operations for you! 

It creates the tables on the database, the controllers, the models and the views.

It also modifies the routes.php file.

You can create forms with the followings elements:

- name
- textarea
- radiobuttons
- checkboxes
- select
- select 1:N (populate the form select with a existent Model)
- upload images (with thumbnail creation and uploads rules)
- upload files (with uploads rules)
- hidden relational (It's a special element. Only one hidden relational by scaffolding is allowed. It will produce a form with relation 1:N linked with his parent form automatically)

Each element has validation rules and the possibility to do it multilanguage.

Create also a paginated list view.


##Server Requirements

PHP version 5.3.5 or newer.
This version only works with MySql (postgres in progress..)


##Dependencies

If you install this sparks via Sparks Manager, it will install the dependencies for you

If you want to install the Sangar Scaffold Spark manually, you need to install before the php-activerecord sparks

	php tools/spark install -v0.0.2 php-activerecord


##Sangar-scaffold-spark requirements


###Disable index_page

Sangar-scaffold-spark needs that you don't use 'index.php' in your base_url.

To disable this function, go to the config folder and edit the config.php



Change

    $config['index_page'] = 'index.php';

to

    $config['index_page'] = '';




Create and .htaccess file in your root folder with the following code:


	AddDefaultCharset UTF-8

	RewriteEngine on

	RewriteCond $1 !^(index\.php|public|robots\.txt)

	RewriteRule ^(.*)$ /index.php/$1 [L]



Remove the index.php file on Codeigniter User Guide: http://codeigniter.com/user_guide/general/urls.html


###Access via domain, not via localhost

You must create a virtualhost (and modify your host file) and access your Codeigniter project via domain, don't use localhost. 
For example, you must use www.yourdomain.com instead of http://localhost/yourdomain


##How to use it?

You can install via sparks manager:

	php tools/spark install -v0.0.1 sangar-scaffold 


Or install manually, copy the spark files in your application folder. Each one in its respective folder.



Copy the file controllers/scaffolds.php and views/scaffolds_create.php of this spark in your application directory. Each one in its respective folders.

Add this line to your autoload file (/config/autoload.php)

	$autoload['sparks'] = array('sangar-scaffold/0.0.1', 'php-activerecord/0.0.2');


Go to the scaffold page

	www.yourdomain.com/scaffolds

Follow the instructions and create a new scaffold

This will create the files which you need to do CRUD operations (controller, model, and two views: create and list).



##How create a new scaffold

- Write the Controller name you want produce.
- Write the Model name you want produce.
- Copy the code blocks of elements you need and paste to scaffold code textarea. Each code block must be separated by commas. The scaffold code is a JSON without the first '{' and the last '}'
- Choose the options you want
- Scaffold!



##Multilanguage form inputs

If you need that your inputs could be multilingual, you must create an array of languages at your config.php
    
    $config['languages'] = array("en" => "english","es" => "spanish");


##Folder for uploads

If you want to upload files, you must create a folder in your root folder named public, and inside this, another folder called uploads. Your uploads will go there. :)


##CSS for scaffolds

If you want, you will find a css to link with your scaffold code inside the resources directory. It will look much better. :)



