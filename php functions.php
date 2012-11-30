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
	return (empty($value) && $value != "0");
}
?>
