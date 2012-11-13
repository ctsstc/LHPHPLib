<?
/*
	Logic Happens - ORM Class
	Author: 	Cody Swartz 
	Copyright:	2012
*/

require_once("debugging.php");
require_once("DBC.php");

	/* Object Relational Mapping
		Description: Populates a subclassed object from a database
		by using public varaibles that match the columns in a DB table, 
		using a where clause on the first public varaible which 
		must be set before calling the parent constructor.  
		The table can be set in the ORM constructor, or it will be parsed from the class name
		by lowercase(ClassName."s") ie: 'User' class would use the 'users' table
		
		Parameters:
			__construct($table = "", $keyValue = "", $keyName = "")
				[$table]: optional otherwise will be populated via strtolower( SubClassName.'s' )
				[$keyValue]: optional otherwise will be generated from subclassed instance's first property
				[$keyName]: optional otherwise will be generated from subclassed instance's first property value
		
		How to use, Example:
			class User extends ORM { // extend the ORM class
				public $username; // the first public variable (FPV) will be used for the where clause
				public $password;
				public $email;
				
				public function __construct() { 
					// REQUIRED!
					parent::__construct();
				}
			}
			
			/// Accessing an existing user
			$user = new User();
			$user->id = 1;	
			$user->populate(); // populates all the public properties of the User class
			$user->username = "CTS_AE"; // change the user's name
			$user->update(); // push changes to the database
			
			/// Creating a New User
			$newUser = new User();
			// set all the data we'll want in the db, you don't have to populate all the properties
			$newUser->email = "John@Doe.net"; 
			$newUser->username = "John Doe";
			$newUser->password = "098f6bcd4621d373cade4e832627b4f6";
			$newUser->insert(); // insert the data into the database as a new row
			
			// The database has an autoincrement on the id column this demonstrates how the current instance's id is automatically updated
			echo "NewUser's ID: ".$newUser->id."<br><br>";
			
			/// Remove an already established user
			$newUser->delete();
			
			/// Remove a user
			// All we need to do is set the first instance's property which will be used to query where key=value ie: id=1
			$userDelete = new User();
			$userDelete->id = 1;
			$userDelete->delete();
			
	*/
	class ORM implements Iterator
	{
		// Possibilities
		// 	Ability to generate a DB table, would need to make enums
		// 	Handle an array of objects
		// 	Only update changed vars for gen columns/values
		// 	Could get crazy and read out the DB meta data such as actual table keys, and structure, 
		//		this could be a whole class of it's own
		// TODO 
		// 	added clause handling, clause("key", "operator", "value"), able to handle an array as well
		// 	make public properties private and add proper mutators & accessors, nearly there already
		//	cleanup - private and public ordering
		//	look into get index and multi column how it's handling/working currently
		// IDEAS
		//	subclass or prefix helper methods such as getClause, and querry stuff
		
		// used to know where the ORM class starts when obtaining the instance's variables
		private $uniqueUnusedFirstProperty = "uniqueUnusedFirstProperty";
		private $className;
		private $db;
		private $collection;
		private $index;
		private $keyName;
		private $keyValue;
		private $customClause;
		
		public $tableName;
		public $autoClean;
		
		public function __construct($tableName = "", $keyValue = "", $keyName = "") 
		{	
			$this->className = get_class($this);	
			
			if (!$this->isExtended())
				throw new ORMMustSubClass();
			
			if(!empty($tableName))
				$this->tableName = $tableName;
			else
				$this->tableName = $this->genTableName();
			
			if(!empty($keyName))
				$this->setKeyName($keyName);
			else
				$this->setKeyName($this->genKeyName());		
					
			if(!empty($keyValue))
				$this->setKeyValue($keyValue);
			
			global $db; // from dbc.php
			$this->db = &$db;
			
			$this->collection = array();
			$this->index = 0;
			$this->setClause("");
			$this->autoClean = true;
		}
		
		private function isExtended()
		{
			return ($this->className != "ORM");
		}
		
		// Hacky method to get the ObjectVars for the subclassed object
		//	There doesn't seem to be a way to get object vars w/o also obtaining 
		//	 the parent class' variables. So I stop collecting them once I hit the first variable name 
		//	 in the parent class which is "uniqueUnusedFirstProperty".
		public function getObjectVars()
		{
			$vars = array();
			foreach(get_object_vars($this) as $k => $v)
			{
				// break on ORM class first public var
				if ($k == $this->uniqueUnusedFirstProperty)
					break;
				
				$vars[$k] = $v;
			}
			return $vars;
		}
		
		// appends an 's' to the instance's class name
		private function genTableName()
		{
			return strtolower( $this->className.'s' );
		}
		
		// returns the first public variable's name
		private function genKeyName()
		{
			$keys = array_keys($this->getObjectVars());
			if (empty($keys[0]))
				throw new ORMUndefinedKeyName($this->className);	
			return $keys[0];
		}
		
		// returns the first public variable's value
		private function genKeyValue()
		{
			$values = array_values($this->getObjectVars());
			if (empty($values[0]))
				throw new ORMUndefinedKeyValue($this->className);	
			return $values[0];
		}
		
		public function getKeyName()
		{
			if (empty($this->keyName))
				$this->keyName = $this->genKeyName();
			
			return $this->keyName;	
		}
		
		public function getKeyValue()
		{
			if (empty($this->keyValue))
				$this->keyValue = $this->genKeyValue();
				
			return $this->keyValue;
		}
		
		public function setKeyName($newKeyName)
		{
			$this->keyName = $newKeyName;
		}
		
		public function setKeyValue($newKeyValue)
		{
			$this->keyValue = $newKeyValue;
		}
		
		private function getInstancePropertyNames()
		{
			return array_keys($this->getObjectVars());
		}
		
		//  $list = true 
		//		returns the column names in a comma separated list
		// 	$list = false 
		//		returns an array of column names
		private function genKeys($list = true)
		{	
			if ($list)
			{
				$ret = "";
				foreach ($this->getObjectVars() as $name => $value) {
					$ret .= "$name, ";
				}
				return substr($ret, 0, strlen($ret) - 2); // remove last ", "
			}
			else
			{
				return $this->getInstancePropertyNames();
			}
		}
		
		//  $list = true 
		//		returns the row values in column order in a comma separated list
		// 	$list = false 
		//		returns an array of column names
		private function genValues($list = true)
		{
			if ($list)
			{
				$ret = "";
				foreach ($this->getObjectVars() as $name => $value) {
					$ret .= "'$value', ";
				}
				return substr($ret, 0, strlen($ret) - 2); // remove last ", "
			}
			else
			{
				return array_values($this->getObjectVars());
			}
		}
		
		private function genKeyValueList()
		{
			$ret = "";
			foreach($this->getObjectVars() as $name => $value) {
					$ret .= "$name='$value', ";
			}
			$ret = substr($ret, 0, strlen($ret) - 2); // remove last ", "
			return $ret;
		}
		
		private function setInstanceData($property, $value)
		{
			$this->$property = $value;
		}
		
		private function getInstanceData($property, $clean = true)
		{
			// not sure of the overhead on the escaping with 
			if ($this->autoClean)
			{
				$cleaned = $this->db->connect()->escape( $this->$property );
				$this->db->disconnect();
				return $cleaned;
			}
			else
				return $this->$property;
		}
		
		public function getClause()
		{
			if (!isset($this->customClause) || empty($this->customClause))
				return $this->getKeyName()."='".$this->getKeyValue()."'";
			else
				return $this->customClause;
		}
		
		public function setClause($newClause)
		{
			$this->customClause = $newClause;
		}
		
		// Queries from table where keyName = keyValue
		public function populateQuery()
		{
			return "SELECT * ".
					"FROM ".$this->tableName.
					" WHERE ".$this->getClause();
		}
		
		// Populates subclassed variables from DB using where $this->key = 
		public function populate()
		{			
			$rows = $this->db
			->connect()
			->query($this->populateQuery(), false)
			->getRowsAssoc();
			
			$this->db->disconnect();
			
			$properties = $this->getInstancePropertyNames();
			
			// set current instance data to first 
			foreach ($properties as $property) {
				$this->setInstanceData($property, $rows[0][$property]);
			}
			
			// clear current contents of collection
			$this->collection = array();
			
			// populate the collection with new instances of the class
			foreach($rows as $row) {
				$instance = new $this->className();
				foreach ($properties as $property) {
					$instance->$property = $row[$property];
				}
				$this->collection[] = $instance;
			}
		}
		
		public function insertQuery()
		{
			return "INSERT INTO ".$this->tableName." (".$this->genKeys().") ".
					"VALUES (".$this->genValues().")";
		}
		
		// Insert subclassed object into DB
		public function insert()
		{
			$this->db
			->connect()
			->query($this->insertQuery(), false);
			
			$insertID = $this->db->lastID();
			$this->db->disconnect();
			
			// update key for auto increment key columns
			if ($insertID != $this->getInstanceData($this->getKeyName())) {
				$this->setKeyValue($insertID);
				$this->setInstanceData($this->getKeyName(), $insertID);
			}
		}
		
		public function updateQuery()
		{
			return "UPDATE ".$this->tableName." ".
					"SET ".$this->genKeyValueList()." ".
					"WHERE ".$this->getClause();
		}
		
		// Updates subclassed variables into the database
		public function update()
		{
			$this->db
			->connect()
			->query($this->updateQuery(), false)
			->disconnect();
		}
		
		public function deleteQuery()
		{
			return "DELETE FROM ".$this->tableName." ".
					"WHERE ".$this->getClause();
		}
		
		// Removes object from the database
		public function delete()
		{	
			$this->db
			->connect()
			->query($this->deleteQuery(), false)
			->disconnect();
		}
		
		// Checks if the row exists in the DB
		public function exists()
		{
			return 
			$this->db
			->connect()
			->query($this->populateQuery(), false)
			->getRowCount() > 0;
		}
		
		// <Iterator> 
		// http://php.net/manual/en/class.iterator.php
		// Could have used the function calls instead of a direct property lookups, 
		//	but this should have less overhead to it
		public function current()
		{
			return $this->collection[$this->index];
		}
		public function key()
		{
			return $this->index;
		}
		public function next()
		{
			++$this->index;
		}
		public function rewind()
		{
			$this->index = 0;
		}
		public function valid()
		{
			return isset($this->collection[$this->index]);
		}
		// </Iterator>
		
		// sets the index that the ORM should be using
		public function setIndex($newIndex)
		{
			$this->index = $newIndex;
		}
		
		// returns the object at the given index
		public function getIndex($index)
		{
			return $this->collection[$index]; 
		}
		
		public function rowCount()
		{
			return count($this->collection);
		}
	}
	
	// <Exceptions>
	class ORMUndefinedKeyName extends Exception
	{
		public function __construct($className) {
			parent::__construct("ORM keyName was unable to generate, either use setKeyName or 
			there was an error obtaining the name of the first public variable in the 
			instance of the class '$className' that extends ORM");
		}
	}
	
	class ORMUndefinedKeyValue extends Exception
	{
		public function __construct($className) {
			parent::__construct("ORM keyValue was unable to generate, either use setKeyValue or 
			set the first public variable in the instance of the class '".$className."' that extends ORM");
		}
	}
	
	class ORMMustSubClass extends Exception
	{
		public function __construct() {
			parent::__construct("ORM Must be extended to be used.");
		} 
	}
	// </Exceptions>
	
	class User extends ORM
	{
		public $id;
		public $email;
		public $username;
		public $password;
		public $type;
		public $data;
		
		public function __construct()
		{
			parent::__construct($this);
		}
	}
	
	$user = new User();
	$user->id = 1;	
	$user->populate();
	$user->username = "CTS_AE";
	$user->update();
	
	$newUser = new User();
	$newUser->email = "testing@test.com";
	$newUser->username = "Testers";
	$newUser->password = "098f6bcd4621d373cade4e832627b4f6";
	$newUser->insert();
	echo "NewUser's ID: ".$newUser->id."<br><br>";
	echo $newUser->deleteQuery()."<br>";
	$newUser->delete();
		
	class Post extends ORM
	{
		public $id;
		public $title;
		public $body;
		public $created;
		public $modified;
		
		public function __construct()
		{
			parent::__construct($this);
		}
	}
	
	$post = new Post();
	$post->setClause("id<'10'");
	$post->populate();
	
	foreach($post as $p)
	{
		echo "Title: ".$p->title."<br>";
		echo "Body: ".$p->body."<br><br>";
	}
	
	echo "Current...<br>";
	echo "Title: ".$post->title."<br>";
	echo "Body: ".$post->body."<br><br>";
	
?>