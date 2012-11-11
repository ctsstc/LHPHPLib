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
			__construct(&$instance, $table = "")
				&$instance: an instance to the object that is subclassing ORM
				[$table]: optional otherwise will be populated via strtolower( SubClassName.'s' )
		
		How to use, Example:
			class User extends ORM { // extend the ORM class
				public $username; // the first public variable (FPV) will be used for the where clause
				public $password;
				public $email;
				
				public function __construct($username) { 
					// the FPV must be initialized with the object
					$this->username = $username;
					// a call to the parent constructor (ORM class) must be made after the FPV is set
					parent::__construct($this);
					// now all the public vars will be populated
				}
			}
	*/
	class ORM implements Iterator
	{
		// Possibilities
		// 	Ability to generate a DB table, would need to make enums
		// 	Handle an array of objects
		// 	Only update changed vars for gen columns/values
		// TODO 
		// 	clean up subclass property data
		private $instance;
		private $className;
		private $db;
		private $collection;
		private $index;
		
		public $table;
		public $keyName;
		public $keyValue;
		public $customClause;
		public $autoClean;
		
		public function __construct(&$instance, $table = "", $keyValue = "", $keyName = "") {
			$this->instance = $instance;
			
			$this->className = get_class($this->instance);
			
			if(!empty($table))
				$this->table = $table;
			else
				$this->table = $this->genTable();
			
			if(!empty($keyValue))
				$this->keyValue = $keyValue;
			else
				$this->keyValue = $this->genKeyValue();
			
			if(!empty($keyName))
				$this->keyName = $keyName;
			else
				$this->keyName = $this->genKeyName();
			
			global $db; // from dbc.php
			$this->db = &$db;
			
			$this->collection = array();
			$this->index = 0;
			$this->customClause = "";
			$this->autoClean = true;
		}
		
		// Hacky method to get the ObjectVars for the subclassed object
		//	There doesn't seem to be a way to get object vars w/o also obtaining 
		//	 the parent class' variables. So I stop collecting them once I hit the first variable name 
		//	 in the parent class which is "instance" so as long as the subclassed object doesn't have 
		// 	 any vars named "instance" this little hacky method will work.
		public function getObjectVars()
		{
			$vars = array();
			foreach(get_object_vars($this->instance) as $k => $v)
			{
				// break on ORM class first public var
				if ($k == "instance")
					break;
				
				$vars[$k] = $v;
			}
			return $vars;
		}
		
		// appends an 's' to the instance's class name
		private function genTable()
		{
			return strtolower( $this->className.'s' );
		}
		
		// returns the first public variable's name
		private function genKeyName()
		{
			$keys = array_keys($this->getObjectVars());
			return $keys[0];
		}
		
		// returns the first public variable's value
		private function genKeyValue()
		{
			$values = array_values($this->getObjectVars());
			return $values[0];
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
				$ret = substr($ret, 0, strlen($ret) - 2); // remove last ", "
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
					$ret .= "$value, ";
				}
				$ret = substr($ret, 0, strlen($ret) - 2); // remove last ", "
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
			$this->instance->$property = $value;
		}
		
		private function getInstanceData($property, $clean = true)
		{
			// not sure of the overhead on the escaping with 
			if ($this->autoClean)
			{
				$cleaned = $this->db->connect()->escape( $this->instance->$property );
				$this->db->disconnect();
				return $cleaned;
			}
			else
				return $this->instance->$property;
		}
		
		// Queries from table where keyName = keyValue
		public function populateQuery()
		{
			$query = "SELECT * ".
						"FROM ".$this->table." WHERE ";
						
			if (!isset($this->customClause) || empty($this->customClause))
				return $query.$this->keyName."='".$this->keyValue."'";
			else
				return $query.$this->customClause;
		}
		
		// Populates subclassed variables from DB using where $this->key = 
		public function populate()
		{
			if (empty ($this->customClause))
			{
				if (empty($this->keyName))
					$this->keyName = $this->genKeyName();
				if (empty($this->keyValue))
					$this->keyValue = $this->genKeyValue();
					
				// sanity check but it is possible that $this->keyValue may be empty
				if (empty($this->keyName) || empty($this->keyValue))
					throw new Exception("ORM keyName and keyValue must be set to, or the first public variable must be set to call populate()");
			}
			// else don't worry about the query key/value
							
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
			return "INSERT INTO ".$this->table." (".$this->genKeys().") ".
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
			if ($insertID != $this->getInstanceData($this->keyName)) {
				$this->keyValue = $insertID;
				$this->setInstanceData($this->keyName, $insertID);
			}
		}
		
		public function updateQuery()
		{
			return "UPDATE ".$this->table." ".
					"SET ".$this->genKeyValueList()." ".
					"WHERE ".$this->keyName."='".$this->keyValue."'";
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
			return "DELETE FROM ".$this->table." ".
					"WHERE ".$this->keyName."='".$this->keyValue."'";
		}
		
		// Removes object from the database
		public function delete()
		{
			$this->updateObjectVars();
			
			$this->db
			->connect()
			->query($this->deleteQuery(), false)
			->disconnect();
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
	$post->customClause = "id<'10'";
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