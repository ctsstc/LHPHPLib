<?

// http://stackoverflow.com/questions/834303/php-startswith-and-endswith-functions
function startsWith($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

// http://stackoverflow.com/questions/834303/php-startswith-and-endswith-functions
function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

// http://www.plus2net.com/php_tutorial/script-self.php
function getCurrentExecutingFile()
{
	$file = $_SERVER["SCRIPT_NAME"];
	$break = Explode('/', $file);
	$pfile = $break[count($break) - 1]; 
	return $pfile;
}

// Cody Swartz
// Doesn't count "0" as empty
function myEmpty($value)
{
	// if I need to incorporate more of the values listed here:
	// 	http://php.net/manual/en/function.empty.php
	// I could have them in an array and try to serach the array using some kinda
	// 	of "array contains" function from PHP
	return (empty($value) && $value != "0");
}

// Cody Swartz
// Generates the current timestamp that mysql will recognize
function mysqlTimeStamp()
{
	return date("Y-m-d H:i:s");
}
// alias
function mysqlDateTime()
{
	return mysqlTimeStamp();
}
?>
