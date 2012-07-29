Nitro ORM
=========

Nitro is a really easy to use, and yet very customizable and extensible, ORM
(Object-relational Mapper) for CodeIgniter.

It's 100% pure PHP, this means you won't have to install any command line tools
to run it, just one mapping file (or as many as you like), which are as simple
as defining a PHP array, and let Nitro work it's magic creating the classes and
managing the relations.  
It can even create the base mapping for you by parsing it directly from the
database!

Another of the many cool features is that it can manage several databases at the
same time and even maintain relations across them!

Requirements
------------

* **CodeIgniter 2+**
* **PHP 5.3+** Why would you be using an older version anyways? ;)
* **MySQL 5+** It should work with version 4

Installation
------------

Nitro ORM can be installed manually or as a spark.

### Manual Installation

1. Download from <https://bitbucket.org/___flatline___/nitro/downloads/>
2. Copy all the files to your CI project inside the "application" folder, with
   the exception of `config/autoload.php`
3. Autoload Nitro by editing your `config/autoload.php` and adding 'nitro' to
   the list of libraries:
    
    `$autoload['libraries'] = array('nitro');`


### Spark Installation

1. Go to http://getsparks.org and install it in your project.
2. Open the terminal, go to your CI+Sparks project root and type
    
    `php tools/spark install -v1.1.0 Nitro`

3. Autoload Nitro spark by editing config/autoload.php and adding 'Nitro/1.1.0'
   to the list of sparks:
    
    `$autoload['sparks'] = array('Nitro/1.1.0');`


### One last step!

Copy the nitro_conf.php controller from the spark to your app controllers folder
to be able to use the Generator. The Generator does what it's name promises, it
generates the entity mapping and the entities.

**Just remember to remove this controller in production!!**

Documentation
-------------

Read the full documentation at <https://bitbucket.org/___flatline___/nitro/wiki/Home>.