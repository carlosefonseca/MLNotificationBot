<?php
set_time_limit(10);
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_COMPILE_ERROR);

// Setup
$debug=true;
$lastChangeFPath = "last.html";
$url = "http://app.metrolisboa.pt/status/estado_Linhas.php";
$isRunningPath = "running";
$isRunningFP;

// LOGGING
#$ob_file = fopen('logs/'.date("Ymd"),'a');
ob_start('ob_file_callback');


// TEST IF SCRIPT IS RUNNING BY LOCKING THE RUNNINGFILE
$isRunningFP = fopen($isRunningPath, "w");
if (!flock($isRunningFP, LOCK_EX)) { // do an exclusive lock
    echo "D";
}

// LOAD PREVIOUS PAGE
$oldpage = loadLocalData($lastChangeFPath);

// DOWNLOAD NEW PAGE
$page = downloadPage($url);

// EXIT IF NO CHANGE
if (strcmp($page, $oldpage) == 0) perish(".");
echo "\n";
// echo "OLD:".$oldpage;
// echo "NEW:".$page;
// SAVE NEW PAGE
file_put_contents($lastChangeFPath, $page);

// EXTRACT CHANGED DATA
$changedData = findChanges($page, $oldpage);

// ACT ON CHANGED DATA
foreach($changedData as $data) {
    actOnChangedData($data);
}
perish("\n");



//////// FUNCTIONS ////////
function actOnChangedData($data) {
    $text = str_replace("LINHA ","",strtoupper($data["line"])).":".$data["descr"];
    $sorry = strpos($text," Pedimos desculpa");
    if ($sorry > -1) {
        $text = substr($text, 0, $sorry);
    }
    $text = str_replace(" minutos", "min", $text);
    $text = str_replace("num período inferior a", "dentro de", $text);
    $text = str_replace("Não é possível prever a duração da interrupção, que poderá ser prolongada.", "Interrupção poderá ser prolongada.", $text);
    $text = str_replace("Esperamos retomar a circulação dentro de.", "Normalização dentro de.", $text);
    $text = str_replace("O tempo de reposição poderá ser superior a", "Pode demorar mais de", $text);
    $text = str_replace("Serviço encerrado", "", $text);
                                                             
    $text = trim(html_entity_decode($text, ENT_COMPAT | ENT_HTML401, "UTF-8"));

    if (strlen($text) == 0) { echo " "; return; }

    if (strlen($text)>280) {
        $spacepos = strrpos($text, " ", -(strlen($text)-280));
        dotweet(substr($text, 0, $spacepos)."…");
        dotweet("…".substr($text, $spacepos+1));
    } else {
        dotweet($text);
    }
    sleep(3)
}

function dotweet($text) {
    echo "\n\n".$text." [".post_tweet_11($text)."]";
    // echo "\nt>".$text."<<";
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $f = curl_exec($ch);
    $info = curl_getinfo($ch);
    $cerror = curl_error($ch);
    curl_close($ch);
    
    if ($f === false || $info['http_code'] != 200) {
        if ($cerror != '')
            perish($cerror."\n");
        else
            perish("#");
    } else {
        return $f;
    }
}


function findChanges($text, $oldtxt) {
    $debug = false;
    if ((strlen(trim($text)) < 2) ||  (strlen(trim($oldtxt)) < 2)) {
        // if they are empty, ignore
        return array();
    }

    // OLD
    $first = strpos($oldtxt, "<tr>");
    $a = preg_match_all("|\<td\\s+class=\"linha_(\\w+)\"[^>]*>\<ul[^>]+><li>(.*)\</li>+\</ul>|", $oldtxt, $matches, PREG_SET_ORDER, $first);
    if (count($matches) == 0)
        perish("h");
    $old = array();
    foreach($matches as $v) {
//        $descr = preg_replace("|\</li>\s*<li[^>]*>|", "; ", $v[3]);
        $old[] = array("line"=>$v[1] , "descr" => $v[2]);
    }
    if ($debug) var_dump($old);

    // NEW
    $first = strpos($text, "<tr>");
    if ($debug) print "---------------------\n$text\n---------------------";
    $a = preg_match_all("|\<td\\s+class=\"linha_(\\w+)\"[^>]*>\\s*\<ul[^>]+><li>(.*)\</li>+\</ul>|", $text, $matches, PREG_SET_ORDER, $first);
    $changes = array();
    foreach($matches as $k=>$v) {
        if ($debug) print "-----\n";
        $descr = $v[2];//preg_replace("|\</li>\s*<li[^>]*>|", "; ", $v[3]);
        if ($debug) print "NEW: {$v[1]} $descr\n";
        if ($debug) print "OLD: {$old[$k]['line']} {$old[$k]["descr"]}\n";
        if (strcmp($descr,$old[$k]["descr"]) != 0) {
            echo $v[1]."\n old:[".strlen($old[$k]["descr"])."]".$old[$k]["descr"]."\n new:[".strlen($descr)."]".$descr;
            if (strlen($descr) == 0) perish("H");
            $changes[] = array("line"=>$v[1] , "descr" => $descr);
        }
    }
    return $changes;
}


$tmhOAuth = null;

function post_tweet($tweet_text) {
    global $tmhOAuth;
    if ($tmhOAuth == null) {
        require_once 'tmhOAuth/tmhOAuth.php';
        require_once 'tmhOAuth/tmhUtilities.php';
        require_once 'config.php';
        $tmhOAuth = new tmhOAuth($tmhOAuthConfig);
    }
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

$twitter = null;
function post_tweet_11($tweet_text) {
    global $twitter;
    if ($twitter == null) {
        require_once('TwitterAPIExchange.php');
        require_once('TwitterAuth.php');
        $twitter = new TwitterAPIExchange($settings);
    }

    $url = "https://api.twitter.com/1.1/statuses/update.json";
    $requestMethod = "POST";

    $postfields = array(
        'status' => $tweet_text, 
        'skip_status' => '1'
    );

    echo $twitter->buildOauth($url, $requestMethod)
                 ->setPostfields($postfields)
                 ->performRequest();
}


// ENDING

function ob_file_callback($buffer)
{
    file_put_contents('logs/'.date("Ymd"), $buffer, FILE_APPEND);
}


function perish($msg = "") {
    global $isRunningFP;
    if (strlen($msg) > 1) {
        echo "[X] ".$msg;
    } else {
        echo $msg;
    }
    ob_end_flush();
    flock($isRunningFP, LOCK_UN);
    fclose($isRunningFP);
    die();
}