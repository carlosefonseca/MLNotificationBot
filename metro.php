<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_COMPILE_ERROR);

// Setup
$debug=false;
$lastChangeFPath = "last.html";
$url = "http://app.metrolisboa.pt/status/estadoLinhas.php";

// LOAD PREVIOUS PAGE
$oldpage = loadLocalData($lastChangeFPath);

// DOWNLOAD NEW PAGE
$page = downloadPage($url);

// EXIT IF NO CHANGE
if (strcmp($page, $oldpage) == 0) die("#");
echo "\n";

// SAVE NEW PAGE
file_put_contents($lastChangeFPath, $page);

// EXTRACT CHANGED DATA
$changedData = findChanges($page, $oldpage);

// ACT ON CHANGED DATA
foreach($changedData as $data) {
	actOnChangedData($data);
}
die("\n");



//////// FUNCTIONS ////////
function actOnChangedData($data) {
    $text = strtoupper($data["line"]).": ".$data["descr"];
    $sorry = strpos($text," Pedimos desculpa pelo i");
    if ($sorry != -1) {
        $text = substr($text, 0, $sorry);
    }
    $text = substr($text, 0, 140);

    $text = utf8_encode(html_entity_decode($text));
    echo "New Tweet: ".$text;

    echo " Twitter: ".post_tweet($text);
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
	
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_REFERER, "http://www.metrolisboa.pt/");
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/535.18.5 (KHTML, like Gecko) Version/5.2 Safari/535.18.5");

	$f = curl_exec($ch);
	curl_close($ch);
	
	return $f;
}


function findChanges($text, $oldtxt) {
    // OLD
	$first = strpos($oldtxt, "<tr>");
    $a = preg_match_all("|\<tr>\<td[^>]+>\<b>([^\<]+)\</b>\</td>\<td[^>]*>\s*\<ul[^>]+>(\<li>(.*)\</li>)+\</ul>|", $oldtxt, $matches, PREG_SET_ORDER, $first);
    $old = array();
    foreach($matches as $v) {
        $descr = preg_replace("|\</li>\s*<li[^>]*>|", "; ", $v[3]);
        $old[] = array("line"=>$v[1] , "descr" => $descr);
    }

    // NEW
	$first = strpos($text, "<tr>");
    $a = preg_match_all("|\<tr>\<td[^>]+>\<b>([^\<]+)\</b>\</td>\<td[^>]*>\s*\<ul[^>]+>(\<li>(.*)\</li>)+\</ul>|", $text, $matches, PREG_SET_ORDER, $first);
    $changes = array();
    foreach($matches as $k=>$v) {
        $descr = preg_replace("|\</li>\s*<li[^>]*>|", "; ", $v[3]);
        if (strcmp($descr,$old[$k]["descr"]) != 0) {
            $changes[] = array("line"=>$v[1] , "descr" => $descr);
        }
    }
    return $changes;
}

function post_tweet($tweet_text) {
    require 'tmhOAuth/tmhOAuth.php';
    require 'tmhOAuth/tmhUtilities.php';
    $tmhOAuth = new tmhOAuth(array(
      'consumer_key'    => '',
      'consumer_secret' => '',
      'user_token'      => '',
      'user_secret'     => '',
    ));

    $code = $tmhOAuth->request('POST', $tmhOAuth->url('1/statuses/update'), array(
      'status' => $tweet_text
    ));

    if ($code == 200) {
        $tmhOAuth->response['response'];
        return "OK";
      // tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
    } else {
      tmhUtilities::pr($tmhOAuth->response['response']);
    }
}