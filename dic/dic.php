<?php


/*
 /获得结果集  
function doresult($sql){  
   $result=mysqli_query(conn(), $sql);  
   return  $result;  
*/

  
function searchDir($path, &$data)
{
    if (is_dir($path)) {
        $dp = dir($path);
        while ($file = $dp->read()) {
            if ($file != '.' && $file != '..') {
                searchDir($path . '/' . $file, $data);
            }
        }
        $dp->close();
    }
    if (is_file($path)) {
        $data[] = $path;
    }
}
function getDir($dir)
{
    $data = array();
    searchDir($dir, $data);
    return $data;
}
#print_r(getDir('.'));
$alldir      = getDir('.');
#print_r($alldir);
$lenofalldir = count($alldir);
#echo $lenofalldir;
$con         = mysql_connect("localhost", "root", "11111111");
mysql_select_db("try", $con);
#clean the table word
mysql_query("truncate table word");
mysql_query($sql, $con);
$lines = 0;
for ($m = 0; $m < $lenofalldir; ++$m) {
    $file    = $alldir[$m];
    $content = file_get_contents($file);
    preg_match_all("/([a-zA-Z]+)/", $content, $out);
    $length = 1 / 2 * count($out, 1);
    for ($i = 0; $i < $length - 1; ++$i) {
        $lines++;
        $a = $out[0][$i];
        mysql_query("INSERT INTO word (line, word,filename) VALUES ('$lines','$a','$file')");
    }
}
#if you want to see the str
print_r($out);
echo $lines;
?>