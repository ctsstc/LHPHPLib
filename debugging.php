<?php

	function debug($str)
	{
		echo '<pre>';
		echo var_dump($str);
		echo '</pre>';
	}
	
	// really just an alias more than anything
	function stackTrace()
	{
		debug(debug_backtrace());
	}
	
?>
