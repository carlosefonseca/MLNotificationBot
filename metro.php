<?php
// Setup
$debug=false;
$lastChangeFPath = "last.html";
$url = "http://app.metrolisboa.pt/status/estadoLinhas.php";

// LOAD PREVIOUS PAGE
$oldpage = loadLocalData($lastChangeFPath);

// DOWNLOAD NEW PAGE
$page = downloadPage($url);

// EXIT IF NO CHANGE
if (strcmp($page, $oldpage) == 0) die("");

// SAVE NEW PAGE
file_put_contents($lastChangeFPath, $page);

// EXTRACT CHANGED DATA
$changedData = findChanges($page, $oldpage);

// print_r($changedData);

// ACT ON CHANGED DATA
foreach($changedData as $data) {
	actOnChangedData($data);
}
die("\n");



//////// FUNCTIONS ////////

function actOnChangedData($data) {
    $text = strtoupper($data["line"]).":\n".$data["descr"];
    echo "New Tweet: ".$text;
}



function loadLocalData($file, $json=false) {
    if (!file_exists($file))
        return "";

	$localdatatext = file_get_contents($file);

	if ($json)
	    return json_decode($localdatatext);
	else
	    return $localdatatext;
}

function downloadPage($url) {
    global $debug;
    global $template;
	
	if ($debug) {
	    return loadLocalData("example.html");
	}
	
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	$f = curl_exec($ch);
	curl_close($ch);
	
	$fp = fopen("example.html","w+");
    fwrite($fp, $f);
    fclose($fp);
	
	return $f;
}


function findChanges($text, $oldtxt) {
    // OLD
	$first = strpos($oldtxt, "<tr>");
    $a = preg_match_all("|\<tr>\<td[^>]+>\<b>([^\<]+)\</b>\</td>\<td[^>]*>\s*\<ul class=\"([^\"]*)\">(\<li>(.*)\</li>)+\</ul>|", $oldtxt, $matches, PREG_SET_ORDER, $first);
    $old = array();
    foreach($matches as $v) {
        $descr = preg_replace("|\</li>\s*<li[^>]*>|", "; ", $v[4]);
        // echo $v[4]."\n—— ".$descr."\n\n\n";
        $old[] = array("line"=>$v[1] , "state" => $v[2] , "descr" => $descr);
    }

    // NEW
	$first = strpos($text, "<tr>");
    $a = preg_match_all("|\<tr>\<td[^>]+>\<b>([^\<]+)\</b>\</td>\<td[^>]*>\s*\<ul class=\"([^\"]*)\">(\<li>(.*)\</li>)+\</ul>|", $text, $matches, PREG_SET_ORDER, $first);
    $changes = array();
    foreach($matches as $k=>$v) {
        $descr = preg_replace("|\</li>\s*<li[^>]*>|", "; ", $v[4]);
        if (strcmp($descr,$old[$k]["descr"]) != 0) {
            $changes[] = array("line"=>$v[1] , "state" => $v[2] , "descr" => $descr);
        }
    }
    
    // print_r($output);
    return $changes;
}