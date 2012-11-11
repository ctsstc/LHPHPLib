<?php
/*
	Logic Happens - Database Class
	Author: 	Cody Swartz 
	Copyright:	2012
*/

require_once("debugging.php");

$db = new DBC(
	'localhost',
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
	
	public function connect()
	{
		$this->DBC = mysqli_connect($this->host, $this->user, $this->pass, $this->db);
		return $this;
	}
	
	public function query($query, $clean = true)
	{
		if ($clean)
			$query = $this->Escape($query);
		
		echo "query = $query <br>";
		
		$this->lastResult = mysqli_query($this->DBC, $query);
		return $this;
	}
	
	public function escape($value)
	{
		return mysqli_real_escape_string($this->DBC, $value);
	}
	
	public function getRow()
	{
		return  mysqli_fetch_array($this->lastResult);
	}
	
	public function getRowAssoc()
	{
		return  mysqli_fetch_array($this->lastResult, MYSQLI_ASSOC);
	}
	
	public function getRows()
	{
		$ret = array();
		while($row = mysqli_fetch_array($this->lastResult))
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
		while($row = mysqli_fetch_array($this->lastResult, MYSQLI_ASSOC))
		{
			$ret[] = $row;
		}
		return $ret;
	}
	
	public function getRowCount()
	{
		return mysqli_num_rows($this->lastResult);
	}
	
	public function lastID()
	{
		return mysqli_insert_id($this->DBC);
	}
	
	public function debugDB()
	{
		debug($this->DBC);
		return $this;
	}
	
	public function disconnect()
	{
		mysqli_close($this->DBC);
		return $this;
	}
	
}

?>
