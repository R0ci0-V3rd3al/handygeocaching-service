<?php
require_once dirname(__FILE__) . '/../old/class.SimpleHttpRequest.php';


$cookie = (isset($_POST['cookie'])) ? $_POST['cookie'] : $_GET['cookie'];
$fieldnotes = (isset($_POST['fieldnotes'])) ? $_POST['fieldnotes'] : $_GET['fieldnotes'];
$incremental = (int)(isset($_POST['incremental'])) ? $_POST['incremental'] : ((isset($_GET['incremental'])) ? $_GET['incremental'] : 0);
$url = "https://www.geocaching.com/my/uploadfieldnotes.aspx";

function saveContent($data, $prefix = '') {
    $filename = md5(uniqid(rand(), true));
    $fp = @fopen('./errors/' . $prefix . $filename . '.html', 'w');
    if ($fp) {
        fwrite($fp, $data);
        fclose($fp);
    }
}

/**
 * @param $req SimpleHttpRequest
 * @param $arrData mixed
 */
function setFileUpload($req, $arrData) {
    $strContents = "";

    // Generate unique ID
    $strUniqueId = substr(md5(uniqid(rand(), true)), 0, 15);
    $strBoundary = "---------------------------" . $strUniqueId;

    $req->setMethod(SimpleHttpRequest::METHOD_POST);

    // Overwrite Content-Type header for multipart/form-data - First Boundary
    $req->addHeader('Content-Type', 'multipart/form-data; boundary=' . $strBoundary);

    foreach ($arrData as $strName => $strData) {
        // Add Boundary
        $strContents .= "--" . $strBoundary . "\r\n";

        // Check if is file and if contents content type tag
        if ($strName[0] == '@') {
            $strName = substr($strName, 1);

            $strContents .= 'Content-Disposition: form-data; name="' . $strName . '"; filename="geocache_visits.txt"' . "\r\n";
            $strContents .= 'Content-Type: text/plain' . "\r\n";
            $strContents .= "\r\n";
            $strContents .= $strData;
            $strContents .= "\r\n";
        } else {
            $strContents .= 'Content-Disposition: form-data; name="' . $strName . '"' . "\r\n";
            $strContents .= "\r\n";
            $strContents .= $strData . "\r\n";
        }
    }

    // Last Boundary
    $strContents .= "--" . $strBoundary . "--\r\n";
    $req->setPostData($strContents);
}

function getAllInput($content) {
    if (preg_match_all('#(<input[^>]*>)#is', $content, $match))
        return $match[1];

    return Array();
}

function getFieldName($content, $type) {
    $arr = getAllInput($content);

    foreach ($arr AS $item) {
        if (strpos($item, 'type="' . $type . '"') !== false)
            if (preg_match('#name="([^"]+)"#is', $item, $name))
                return $name[1];
    }

    return false;
}

function getFieldValue($content, $name) {
    $arr = getAllInput($content);

    foreach ($arr AS $item) {
        if (strpos($item, 'name="' . $name . '"') !== false) {
            if (preg_match('#value="([^"]+)"#is', $item, $value))
                return $value[1];
        }
    }

    return false;
}

header('Content-Type: text/plain; charset=utf-8');

//nejprve zjistit viewstate
$req = new SimpleHttpRequest($url);
UserSessionHandler::prepareHTTPCookies($req);

$req->sendRequest();
UserSessionHandler::handleHTTPCookies($req);

$content = $req->getResponseBody();

if (strpos($content, 'Object moved to <a href="https://www.geocaching.com/login/') !== false) {
    echo "ERR_YOU_ARE_NOT_LOGGED";
    saveContent($content, 'login-');
    exit;
}

if (strpos($content, 'ctl00_ContentBody_LoginPanel') !== false) {
    echo "ERR_YOU_ARE_NOT_LOGGED";
    saveContent($content, 'login-');
    exit;
}

//detekce viewstate
if (($viewstateGenerator = getFieldValue($content, '__VIEWSTATEGENERATOR')) === false) {
    echo "ERR_FIELD_NOTES_FAILED";
    saveContent($content, 'viewstateGenerator-');
    exit;
}

if (($viewstate = getFieldValue($content, '__VIEWSTATE')) === false) {
    echo "ERR_FIELD_NOTES_FAILED";
    saveContent($content, 'viewstate-');
    exit;
}

//if (($viewstate1 = getFieldValue($content, '__VIEWSTATE1')) === false) {
//	saveContent($content,'viewstate1-');
//}


//nazev input file
if (($fileName = getFieldName($content, 'file')) === false) {
    echo "ERR_FIELD_NOTES_FAILED";
    saveContent($content, 'fname-');
    exit;
}

//nazev checkboxu
if (($chkName = getFieldName($content, 'checkbox')) === false) {
    $chkName = '';
    //echo "ERR_FIELD_NOTES_FAILED";
    //saveContent($content,'chname-');
    //exit;
}

//hodnota checkboxu
if (($chkValue = getFieldValue($content, $chkName)) === false) {
    $chkValue = "on";
}

//nazev submitu
if (($submitName = getFieldName($content, 'submit')) === false) {
    echo "ERR_FIELD_NOTES_FAILED";
    saveContent($content, 'sname-');
    exit;
}

//hodnota submitu
if (($submitValue = getFieldValue($content, $submitName)) === false) {
    echo "ERR_FIELD_NOTES_FAILED";
    saveContent($content, 'svalue-');
    exit;
}

//pridame byte order mark, bez toho to nefuguje
//$fieldnotes = chr(0xEF).chr(0xBB).chr(0xBF).$fieldnotes;
//$fieldnotes = chr(0xFF) . chr(0xFE) . iconv('UTF-8', 'UTF-16LE', $fieldnotes);

$vals = array(
    '__VIEWSTATE' => $viewstate,
    '__VIEWSTATEGENERATOR' => $viewstateGenerator,
    //'__VIEWSTATE1' => $viewstate1,
    '@' . $fileName => $fieldnotes,
    //$chkName => $chkValue,
    $submitName => $submitValue
);

if ($incremental == 1 && $chkName)
    $vals[$chkName] = $chkValue;

$req = new SimpleHttpRequest($url);
UserSessionHandler::prepareHTTPCookies($req);
setFileUpload($req, $vals);

$req->sendRequest();
UserSessionHandler::handleHTTPCookies($req);

$content = $req->getResponseBody();

$count = 0;
if (preg_match('#(\d+) (records were successfully uploaded|záznamů bylo úspěně nahráno).#isu', $content, $match)) {
    $count = $match[1];
} else {
    saveContent($content, 'fncount-');
}

echo $count;
