<?php
define("DOC_ROOT",__DIR__);

/* Cookie path, make sure it's writable! */
$cookiePath = DOC_ROOT."/ctemp";
$cookie_file_path = $cookiePath."/cookie.txt";

/* Forum timezone so posting time matches */
date_default_timezone_set('Europe/Paris');

//username and password of the account
$values['email'] = $_GET['email'];
$values['password'] = $_GET['password'];

/* Login and save cookie for later use */
$loginUrl="https://braterstwo.eu/profil/?dame=login";
$username = trim($values["email"]);
$password = trim($values["password"]);
$postinfo = "email=".$username."&password=".$password."&frm_action=login-user&redirect_to=";

$ch = curl_init();
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");

curl_setopt($ch, CURLOPT_USERAGENT,
    "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_REFERER, $loginUrl);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);
curl_exec($ch);

/* Grab the target site */
$urlSuffix = $_GET['forum'];
$urlToConvert = 'https://braterstwo.eu/tforum/'.ltrim($urlSuffix,'/');
curl_setopt($ch, CURLOPT_URL, $urlToConvert);
$html = curl_exec($ch);
curl_close($ch);

/* Convert HTML to Array of information */
libxml_use_internal_errors(true);
$dom = new DomDocument;
$dom->loadHTML($html);
$xpath = new DomXPath($dom);

$title = $xpath->query('//title')->item(0)->textContent;
$title = str_replace(' | Braterstwo','',$title);

$nodes = $xpath->query('//*[@id="content"]//table[2]');
if($nodes->length==0){
    $nodes = $xpath->query('//*[@id="content"]//table[1]');
}

foreach ($nodes as $i => $node) {
    /* @var $node DOMNode */
    if($node->nodeName=='table'){
        if(!$node->hasChildNodes()){
            continue;
        }
        foreach($node->childNodes as $tableTR){
            /* @var $tableTR DOMElement */
            if($tableTR->nodeName=='tr'){
                if(!$tableTR->hasChildNodes()){
                    continue;
                }
                /**
                 * Columns:
                 * 0 - title with the link
                 * 1 - number of comments
                 * 2 - time posted (time ago)
                 */
                $colNumber = 0;
                $rowInfo = [];
                foreach($tableTR->childNodes as $tableTD){
                    //var_dump($tableTD);
                    /* @var $tableTD DOMNode */
                    if($colNumber==0){
                        $rowInfo['topic'] = $tableTD->nodeValue;
                        foreach($tableTD->childNodes as $topicDetail){
                            /* @var $topicDetail DOMNode */
                            if($topicDetail->localName=='a'){
                                $href = $topicDetail->attributes->item(0);
                                /* Just a safety check it's really href */
                                if($href->localName=='href'){
                                    $rowInfo['link'] = 'https://braterstwo.eu'.$href->textContent;
                                }
                            }
                        }
                    } elseif ($colNumber==1){
                        $rowInfo['comments'] = $tableTD->nodeValue;
                    } elseif ($colNumber==2){
                        $postedAgo = $tableTD->nodeValue;
                        $pubDate = 'now -'.str_replace(['d','h','m'],['days -','hours -','minutes '],$postedAgo);
                        $rowInfo['pubDate'] = date(DATE_RSS, strtotime($pubDate));
                    }
                    $colNumber++;
                }
                $rows[] = $rowInfo;
            }
        }
    }
}

/* Print the feed using the $rows array */
header('Content-Type: application/rss+xml; charset=utf-8');
?>
<rss version="2.0">
    <channel>
        <title>Braterstwo.eu - <?php echo $title ?></title>
        <description>
            Braterstwo.eu, podforum <?php echo $urlSuffix ?>
        </description>
        <link><?php echo $urlToConvert ?></link>
        <language>pl-pl</language>
        <lastBuildDate><?php echo date(DATE_RSS,time()); ?></lastBuildDate>
        <managingEditor>jan@codingmice.com</managingEditor>
        <pubDate><?php echo date(DATE_RSS,time()); ?></pubDate>
        <webMaster>jan@codingmice.com</webMaster>
        <generator>CodingMice HTML2RSS converter</generator>
        <image>
            <url>https://braterstwo.eu/wp-content/uploads/2016/04/cropped-favicon-180x180.png</url>
            <title>Braterstwo.eu</title>
            <link>https://braterstwo.eu/</link>
            <description>Ogólnopolskie Stowarzyszenie Strzelecko Kolekcjonerskie</description>
            <width>180</width>
            <height>180</height>
        </image>
        <?php foreach ($rows as $row): ?>
            <item>
                <title><?php echo $row['topic'] ?></title>
                <description>
                    <?php echo $row['topic'] ?>
                </description>
                <link><?php echo $row['link'] ?></link>
                <pubDate><?php echo $row['pubDate']?></pubDate>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
