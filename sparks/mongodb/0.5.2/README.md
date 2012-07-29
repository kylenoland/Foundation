# Version 2 of this library is being actively developed

### Please visit [https://github.com/alexbilbie/codeigniter-mongodb-library/tree/v2](https://github.com/alexbilbie/codeigniter-mongodb-library/tree/v2)

### Please ensure any new bugs have been tested on this new version

---

# CodeIgniter MongoDB Library

This is an active record inspired CodeIgniter library to integrate a MongoDB database into your application.

1. Add the file Mongo_db.php to your /application/libraries folder.
2. Add the file mongodb.php to your /application/config folder.
3. Update the config file.

You can now autoload the library or include it in one of your controllers at run time.

You can interact with the database using many of the active record functions that CodeIgniter provides.

	$this->mongo_db
		->where_gte('age', 18)
		->where_in('country', array(
			'England',
			'Scotland',
			'Wales',
			'Ireland'
		))
		->where(array(
			'likes_whisky' => TRUE
		))
		->get('people');
	
The result will be an object containing matching documents.

## Select Functions

* `select`					Get select fields from returned documents
* `where`						Where section of the query
* `where_in`					Where something is in an array of something
* `where_in_all`			Where something is in all of an array of * something
* `where_not_in`			Where something is not in array of something
* `where_gt`					Where something is greater than something
* `where_gte`				Where something is greater than or equal to something
* `where_lt`					Where something is less than something
* `where_lte`				Where something is less than or equal to something
* `where_not_equal`		Where something is not equal to something
* `where_near``				Where something is near to something (2d geospatial search)
* `order_by`					Order the results
* `limit`						Limit the number of returned results

## Insert Function

* `insert`					Insert a new document into a collection

## Update Functions

* `inc`						Increments the value of a field
* `dec`						Decrements the value of a field
* `set`						Sets a field to a value
* `unset_field`				Unsets a field
* `addtoset`					Adds a value to an array if doesn't exist
* `push`						Pushes a value into an array field
* `pop`						Pops a value from an array field
* `pull`						Removes an array by the value of a field
* `rename_field`			Rename a field
* `update`					Update a single document in a collection
* `update_all`				Update all documents in a collection

## Delete Functions

* `delete`					Delete a single document in a collection
* `delete_all`				Delete all documents in a collection

## Index Functions

* `set_index`				Creates a new index
* `delete_index`			Deletes an index

## Database Functions

* `drop_db`					Drops a database
* `switch_db`				Switch to a different database

## Collection Functions

* `drop_collection`		Drops a collection