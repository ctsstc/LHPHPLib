<?php
/*
	Logic Happens - Database Class
	Author: 	Cody Swartz 
	Copyright:	2012
*/

require_once("debugging.php");

$db = new DBC(
	'host',
	'username',
	'password',
	'database'
);

class DBC
{	
	private $host;
	private $user;
	private $pass;
	private $db;
	private $DBC;
	
	private $lastResult;
	
	public function __construct($host, $user, $pass, $db) {
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->db = $db;
	}
	
	// Chainable
	public function connect()
	{
		// connect only if not yet connected...
		// maybe look into :p prefix... "pconnect" persistent connection
		if (!$this->connected())
		{
			$this->DBC = new mysqli($this->host, $this->user, $this->pass, $this->db);
			if ($this->DBC->connect_errno)
				echo "Failed to connect to MySQL: " . $this->DBC->connect_error();
		}
		
		return $this;
	}
	
	public function connected()
	{
		return isset($this->DBC);
	}
	
	// Chainable
	public function query($query)
	{
		//echo "query = $query <br>"; // Watch every querry
		$this->lastResult = $this->DBC->query($query);
		return $this;
	}
	
	public function escape($value)
	{
		return $this->DBC->real_escape_string($value);
	}
	
	public function getRow()
	{
		return  $this->lastResult->fetch_array();
	}
	
	public function getRowAssoc()
	{
		return  $this->lastResult->fetch_array();
	}
	
	public function getRows()
	{
		$ret = array();
		while($row = $this->lastResult->fetch_array())
		{
			$ret[] = $row;
	
		}
		return $ret;
	}
	
	public function getRowsAssoc()
	{
		global $DBC;
		//return mysqli_fetch_all($result, MYSQLI_BOTH); //requires PHP 5.3
		$ret = array();
		while($row = $this->lastResult->fetch_array(MYSQLI_ASSOC))
		{
			$ret[] = $row;
		}
		return $ret;
	}
	
	public function getRowCount()
	{
		return $this->lastResult->num_rows;
	}
	
	public function lastID()
	{
		return $this->DBC->insert_id;
	}
	
	// Chainable
	public function debugDB()
	{
		debug($this->DBC);
		return $this;
	}
	
	// Chainable
	public function disconnect()
	{
		echo "disconnected";
		$this->DBC->close();
		unset($this->DBC);
		return $this;
	}
	
}

?>
