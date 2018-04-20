<?php
@set_time_limit(120);

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE ^ E_DEPRECATED);
ini_set("display_errors", true);
ini_set("display_startup_errors", true);

require_once 'class.SimpleHttpRequest.php';
require_once 'class.Html2Text.class.php';
require_once 'class.UserSessionHandler.php';

require_once 'inc.AutoCzech.php';
require_once 'inc.Version.php';
require_once 'inc.VipUsers.php';


$protocol_1_1 = (isset($_GET["protocol"]) && $_GET["protocol"] == '1.1');

$content = "";

$part = $_GET["part"];

if (!UserSessionHandler::prepareSession() && $part != 'login') {
    Vypis('ERR_YOU_ARE_NOT_LOGGED');

} else if ($part == "login") {
    $sessid = $_GET["sessid"];
    $version = $_GET["version"];
    $parsed = explode("-", $sessid);

    $name = '';
    for ($i = 0; $i < $parsed[0]; $i++) {
        $name .= chr($parsed[$i + 2]);
    }
    $password = '';
    for ($i = 0; $i < $parsed[1]; $i++) {
        $password .= chr($parsed[$i + $parsed[0] + 2]);
    }

    //logovani
    $req = new SimpleHttpRequest('https://www.geocaching.com/account/login');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req, true);

    $req->sendRequest();
    $response = $req->getResponseBody();

    if (!$req->getResponseBody())
        die('err:TIMEOUT');

    UserSessionHandler::handleHTTPCookies($req);
    unset($req);

    $pozice = strpos($response, '__RequestVerificationToken');
    $token = coJeMezi($response, $pozice, 'value=', '/>');
    $token = trim(str_replace('"', '', $token));

    $req = new SimpleHttpRequest('https://www.geocaching.com/account/login');
    $req->setMethod(SimpleHttpRequest::METHOD_POST);
    addHeaders($req);

    $req->addPostData('__RequestVerificationToken', $token);
    $req->addPostData('Username', $name);
    $req->addPostData('Password', $password);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    if (!$req->getResponseBody())
        die('err:TIMEOUT');

    if (strpos($req->getResponseBody(), 'or password is incorrect') > 0) {
        Vypis("ERR_BAD_PASSWORD");
    } elseif (strpos($req->getResponseBody(), 'Forgot your username or password?')) {
        Vypis("ERR_AUTH_FAILED");
    } else {
        //verze
        if ($version == $actualVersion)
            $versionInfo = "OK";
        else
            $versionInfo = $actualVersion;

        //vip
        if (in_array($name, $vipUsers))
            $vip = 1;
        else
            $vip = 0;

        Vypis(UserSessionHandler::getId() . "}" . $versionInfo . "}" . $vip . "{");
    }
} elseif ($part == "nearest") {
    $longitude = $_GET["longitude"];
    $latitude = $_GET["lattitude"];
    $cookie = $_GET["cookie"];
    $filter = $_GET["filter"];
    $numberCaches = $_GET["numberCaches"];

    //filtrovani vlastnich a nalezenych
    if ($filter[4] == "0") {
        $suffix = "&f=1";
    } else {
        $suffix = "";
    }

    $url = 'https://www.geocaching.com/seek/nearest.aspx?lat=' . $latitude . '&lon=' . $longitude . $suffix;

    $req = new SimpleHttpRequest($url);
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    $pozice = 0;
    $cachenum = 0;
    while ($pozice = strpos($response, '<tr class="', $pozice)) {
        $correctRowCheck = substr($response, $pozice, 19);

        if ($correctRowCheck != '<tr class="SolidRow' &&
            $correctRowCheck != '<tr class="Alternat' &&
            $correctRowCheck != '<tr class="Tertiary' &&
            $correctRowCheck != '<tr class="UserOwne' &&
            $correctRowCheck != '<tr class="Beginner'
        ) {
            $pozice++;
            continue;
        }

        if ($correctRowCheck == '<tr class="UserOwne' && $filter[4] == "0") {
            $pozice++;
            continue;
        }

        $smer = coJeMezi($response, $pozice, '/images/icons/compass/', '.gif"');
        $smer = directionToCzech($smer);

        $vzdalenost = coJeMezi($response, $pozice, '<br />', '</span>');

        $obrazek = coJeMezi($response, $pozice, 'WptTypes/', '.gif"');
        //if ($obrazek == 'check')
        //	$obrazek = coJeMezi($response, $pozice, '<img src="https://www.geocaching.com/images/wpttypes/sm/', '.gif" alt=');

        //filtrovani
        if ($filter[0] == "0" && $obrazek == "2")
            continue; //preskoc traditional
        if ($filter[1] == "0" && $obrazek == "3")
            continue; //preskoc multi
        if ($filter[2] == "0" && $obrazek == "8")
            continue; //preskoc mystery
        if ($filter[3] == "0" && ($obrazek != "2" && $obrazek != "3" && $obrazek != "8"))
            continue; //preskoc ostatni typy
        $nazev = coJeMezi($response, $pozice, '<a', '</a>');
        if ($filter[5] == "0" && strpos($nazev, 'class="lnk  Strike"'))
            continue; //preskoc disabled cache

        //uprava nazvu
        $ctu = 0;
        $nazev2 = "";
        for ($i = 0; $i < strlen($nazev); $i++) {
            $znak = $nazev[$i];
            if ($znak == ">") {
                $ctu = 1;
            } elseif ($znak == "<") {
                $ctu = 0;
            } else {
                if ($ctu == 1) {
                    $nazev2 .= $znak;
                }
            }
        }
        $nazev = replaceBrackets(trim($nazev2));
        $waypoint = "GC" . coJeMezi($response, $pozice, '    GC', "\n");
        Vypis($vzdalenost . ' ' . $smer . ' ' . html_entity_decode_utf8($nazev) . '}' . translateImage($obrazek) . '}' . $waypoint .
            '{');
        $cachenum++;
        if ($cachenum >= $numberCaches)
            break;
    }

} elseif ($part == "keyword") {
    $keyword = $_GET["keyword"];
    $cookie = $_GET["cookie"];
    $numberCaches = $_GET["numberCaches"];
    if (empty ($numberCaches))
        $numberCaches = 10; //kompatibilita

    $url = 'https://www.geocaching.com/seek/nearest.aspx?key=' . urlencode($keyword) . '&submit4=Go';

    $req = new SimpleHttpRequest($url);
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    //TODO tak tohle jiz ve strance neni. Takze to zobrazi prazdny seznam misto chyby.
    if (strpos($response, 'Sorry, no results') || strpos($response, 'Sorry, no results')) {
        Vypis("WRONG_KEYWORD");
    } else {
        $pozice = 0;
        $cachenum = 0;
        while ($pozice = strpos($response, '<tr class="', $pozice)) {
            $correctRowCheck = substr($response, $pozice, 19);

            if ($correctRowCheck != '<tr class="SolidRow' &&
                $correctRowCheck != '<tr class="Alternat' &&
                $correctRowCheck != '<tr class="Tertiary' &&
                $correctRowCheck != '<tr class="UserOwne' &&
                $correctRowCheck != '<tr class="Beginner'
            ) {
                $pozice++;
                continue;
            }
            $obrazek = coJeMezi($response, $pozice, 'WptTypes/', '.gif" alt=');
            $nazev = coJeMezi($response, $pozice, '<a', '</span>');

            //uprava nazvu
            $ctu = 0;
            $nazev2 = "";
            for ($i = 0; $i < strlen($nazev); $i++) {
                $znak = $nazev[$i];
                if ($znak == ">") {
                    $ctu = 1;
                } elseif ($znak == "<") {
                    $ctu = 0;
                } else {
                    if ($ctu == 1) {
                        $nazev2 .= $znak;
                    }
                }
            }
            $nazev = replaceBrackets(trim($nazev2));
            $waypoint = "GC" . coJeMezi($response, $pozice, '    GC', "\n");
            $country = trim(odstranHTML(coJeMezi($response, $pozice, '|', '</span>')));
            Vypis(html_entity_decode_utf8($nazev) . ' (' . $country . ')}' . translateImage($obrazek) . '}' . $waypoint .
                '{');
            $cachenum++;
            if ($cachenum >= $numberCaches)
                break;
        }
    }

} elseif ($part == "overview") {
    //echo "overview";
    $waypoint = trim($_GET["waypoint"]);
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?wp=' . $waypoint .
        '+&Submit6=Go');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    //TODO cestina
    if (strpos($response, 'Cache is Unpublished')) {
        Vypis("ERR_BAD_WAYPOINT");
    } //TODO cestina
    elseif (strpos($response, 'Premium Members only')) {
        Vypis("ERR_PM_ONLY");
    } elseif (strpos($response, '/account/login?returnUrl=')) {
        Vypis('ERR_YOU_ARE_NOT_LOGGED');
    } else {
        $pozice = strpos($response, '<section id="Content">');

        //$pozice = strpos($response, 'Start Cache Title Area');
        $typeNumber = coJeMezi($response, $pozice, 'WptTypes/', '.gif');
        $type = coJeMezi($response, $pozice, 'alt="', '" title');

        $name = replaceBrackets(html_entity_decode_utf8(coJeMezi($response, $pozice, '<span id="ctl00_ContentBody_CacheName">', '</span>')));
        $author = replaceBrackets(coJeMezi($response, $pozice, "ds=2\">", '</a>'));
        $difficulty = coJeMezi($response, $pozice, 'alt="', ' out');
        $terrain = coJeMezi($response, $pozice, 'alt="', ' out');
        $size = coJeMezi($response, $pozice, '<small>(', ')</small>');

        if (strpos($response, '/account/login?returnUrl=', $pozice)) {
            Vypis('ERR_YOU_ARE_NOT_LOGGED');
        } else {

            //archivovana a disablovana
            if (strpos($response, '<ul class="OldWarning">', $pozice)) {
                if (strpos($response, 'archived', $pozice)) {
                    $error = "Archivovaná!";
                } elseif (strpos($response, 'unavailable', $pozice)) {
                    $error = "Disablovaná!";
                } else {
                    $error = "";
                }
            } else {
                $error = "";
            }

            //souradnice
            $coordinates = coJeMezi($response, $pozice, '<span id="uxLatLon">', '</span>');
            //lattitude a longitude ze souradnic
            $k = 0;
            $poz = 0;
            while ($poz = strpos($coordinates, " ", $poz + 1)) {
                $k++;
                if ($k == 3)
                    break;
            }
            $latitude = substr($coordinates, 0, $poz);
            $longitude = substr($coordinates, $poz + 1);

            //vzorce do MultiSolveru
            $info = "";
            if (strpos($response, '<span id="ctl00_ContentBody_ShortDescription">')) {
                $info = coJeMezi($response, $pozice, '<span id="ctl00_ContentBody_ShortDescription">', '</span>');
            }
            $info .= coJeMezi($response, $pozice, '<span id="ctl00_ContentBody_LongDescription">', '<p id="ctl00_ContentBody_hints">');
            if (strpos($info, '<!--Handy Geocaching patterns:'))
                $patterns = 1;
            else
                $patterns = 0;

            //velikost podrobnosti
            $listingSize = round(strlen($info) / 1000);
            if ($listingSize == 0)
                $listingSize = "<1";

            unset($info);
            $info = null;

            //je hint?
            if (strpos($response, 'id="div_hint"'))
                $hint = 1;
            else
                $hint = 0;

            //pridavne waypointy
            if (strpos($response, 'ctl00_ContentBody_Waypoints'))
                $waypoints = 1;
            else
                $waypoints = 0;

            //travelbugy
            $inventory = '';
            /*
            $pozice = strpos($response, 'ctl00_ContentBody_uxTravelBugList_uxInventoryLabel');
            if (strpos($response, '<ul>', $pozice) > strpos($response, 'ctl00_ContentBody_uxTravelBugList_uxWhatIsATravelBug', $pozice) ||
                strpos($response, '<ul>', $pozice) === false
            ) {
                $invertory = "";
            } else {
                $pozice = strpos($response, '<ul>', $pozice);
                $inventory = '';
                $endpozice = strpos($response, '</ul>', $pozice);
                while ($pozice < $endpozice) {
                    if (strpos($response, '<span>', $pozice) < $endpozice)
                        $inventory .= coJeMezi($response, $pozice, '<span>', '</span>') . ', ';
                    else break;
                    //$inventory .= ', ';
                }
            }*/


            $pozice = strpos($response, 'ctl00_ContentBody_CoordInfoLinkControl1_uxCoordInfoCode');
            $waypoint = trim(coJeMezi($response, $pozice, '>', '</span>'));

            $output = html_entity_decode($name, ENT_QUOTES, "UTF-8") . '}' . html_entity_decode($author, ENT_QUOTES, "UTF-8") . '}' . $type . '}' . $size . '}' . $latitude . '}' . $longitude . '}' . $difficulty . '/' . $terrain . '}' .
                $waypoint . '}' . html_entity_decode($inventory, ENT_QUOTES, "UTF-8") . '}' . $error . '}' . translateImage($typeNumber) . '}' . $waypoints . '}' . $hint . '}' . $patterns . '}' . $listingSize . '{';
            // $output;
            if (strlen($output) > 5000) {
                Vypis('PARSING_ERROR');
            } else {
                Vypis($output);
            }
        }
    }
} elseif ($part == "info") {
    $waypoint = $_GET["waypoint"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?wp=' . $waypoint .
        '+&Submit6=Go');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, 'Cache is Unpublished"')) {
        Vypis("ERR_BAD_WAYPOINT");
    } elseif (strpos($response, '/account/login?returnUrl=')) {
        Vypis('ERR_YOU_ARE_NOT_LOGGED');
    } else {
        $pozice = 0;
        $info = "";
        if (strpos($response, '<span id="ctl00_ContentBody_ShortDescription">')) {
            $info = coJeMezi($response, $pozice, '<span id="ctl00_ContentBody_ShortDescription">', '</span>');
        }
        $info .= coJeMezi($response, $pozice, '<span id="ctl00_ContentBody_LongDescription">', '<p id="ctl00_ContentBody_hints">');

        if (trim($info) == "") {
            $info = "Tato cache nemá žádný popis";
        }

        $h2t = new Html2Text(html_entity_decode_utf8($info));
        $info = stripSlashes2($h2t->get_text());

        Vypis($info);
    }
} elseif ($part == "hint") {
    $waypoint = $_GET["waypoint"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?wp=' . $waypoint .
        '+&Submit6=Go');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, 'Cache is Unpublished')) {
        Vypis("ERR_BAD_WAYPOINT");
    } elseif (strpos($response, 'No hints available') || strpos($response, 'Hint není k dispozici')) {
        Vypis("NO_HINT");
    } else {
        $pozice = 0;
        $hint = odstranHTML(coJeMezi($response, $pozice, '<div id="div_hint" class="span-8 WrapFix">', '</div>'), false);
        //decrypt
        $hint = strtr($hint, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 'nopqrstuvwxyzabcdefghijklmNOPQRSTUVWXYZABCDEFGHIJKLM');
        $newhint = "";
        $decrypting = false;
        for ($i = 0; $i < strlen($hint); $i++) {
            if ($hint[$i] == "[") {
                $decrypting = true;
                $newhint .= $hint[$i];
            } elseif ($hint[$i] == "]") {
                $decrypting = false;
                $newhint .= $hint[$i];
            } else {
                if ($decrypting) {
                    $newhint .= strtr($hint[$i], 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 'nopqrstuvwxyzabcdefghijklmNOPQRSTUVWXYZABCDEFGHIJKLM');
                } else {
                    $newhint .= $hint[$i];
                }
            }
        }
        $newhint = trim($newhint);
        if (!$newhint) {
            Vypis("NO_HINT");
        } else {
            Vypis($newhint);
        }
    }
} elseif ($part == "waypoints") {
    $waypoint = $_GET["waypoint"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?wp=' . $waypoint .
        '+&Submit6=Go');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, '/account/login?returnUrl=', $pozice)) {
        Vypis('ERR_YOU_ARE_NOT_LOGGED');
    } elseif (strpos($response, 'Cache is Unpublished')) {
        Vypis("ERR_BAD_WAYPOINT");
    } else {
        $pozice = strpos($response, 'ctl00_ContentBody_Waypoints');
        $output = "";
        if ($pozice > 0) {
            $pozice = 0;
            $response = coJeMezi($response, $pozice, 'ctl00_ContentBody_Waypoints', '</table>');
            $pozice = 0;

            while ($pozice = strpos($response, "<tr class=", $pozice)) {
                $pozice = strpos($response, '<td', $pozice);
                $pozice = strpos($response, '<td', $pozice + 1);
                $pozice = strpos($response, '<td', $pozice + 1);
                $pozice = strpos($response, '<td', $pozice + 1);
                $lookup = trim(odstranHTML(coJeMezi($response, $pozice, '<td>', '</td>')));
                //$pozice = strpos($response, 'DS=1">', $pozice) + 1;
                $name = trim(odstranHTML(coJeMezi($response, $pozice, 'DS=1">', '</a>')));
                $coordinates = trim(odstranHTML(coJeMezi($response, $pozice, '<td>', '</td>')));
                //lattitude a longitude ze souradnic
                $k = 0;
                $poz = 0;
                while ($poz = strpos($coordinates, " ", $poz + 1)) {
                    $k++;
                    if ($k == 3)
                        break;
                }
                $latitude = substr($coordinates, 0, $poz);
                $longitude = substr($coordinates, $poz + 1);
                $note = trim(odstranHTML(coJeMezi($response, $pozice, 'colspan="6">', '</td>')));
                //echo replaceBrackets(html_entity_decode_utf8($name)) . '}' . $lookup . '}' . $lattitude . '}' . $longitude . '}' . replaceBrackets(html_entity_decode_utf8($note)) . '{';
                $output .= replaceBrackets(html_entity_decode_utf8($name)) . '}' . $lookup . '}' . $latitude . '}' . $longitude . '}' . replaceBrackets(html_entity_decode_utf8($note)) . '{';
            }
        }

        if ($output == "") {
            Vypis("NO_WAYPOINTS");
        } else {
            Vypis($output);
        }
    }
} elseif ($part == "logs") {
    $waypoint = $_GET["waypoint"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?wp=' . $waypoint .
        '+&Submit6=Go');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, 'Cache is Unpublished')) {
        Vypis("ERR_BAD_WAYPOINT");
    } else {
        $logs_json = coJeMezi($response, $pozice, 'initalLogs = {', ' };');
        $json = json_decode('{' . $logs_json . '}', true);
        $logs = analyze_logs($json);

        foreach ($logs AS $log) {
            Vypis($log['type'] . '}' . replaceBrackets($log['name']) . '}' . $log['found'] . '}' . $log['date'] . '}' . replaceBrackets($log['log']) . '{');
        }

        $addlogs = $json['pageInfo']['totalRows'] - $json['pageInfo']['size'];

        if ($addlogs <= 0) {
            $guidline = "NO_MORE_LOGS";
            $addlogs = 0;
        } else {
            $guidline = $waypoint;
        }
        Vypis($addlogs . '}' . $guidline . '}' . '}' . '}' . '{');
    }
} elseif ($part == "alllogs") {
    $guideline = $_GET["guideline"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?guid=' .
        $guideline . '&log=y&decrypt=');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, 'Cache is Unpublished')) {
        Vypis("ERR_BAD_WAYPOINT");
    } else {
        $pozice = strpos($response, '<table class="LogsTable">');
        $logs = 0;
        while ($pozice = strpos($response, "<tr><td class=\"", $pozice)) {
            $logs++;
            $pozice = strpos($response, '<a href', $pozice);
            $name = html_entity_decode_utf8(coJeMezi($response, $pozice, '">', '</a>'));

            $p = 0;
            $found = coJeMezi($response, $pozice, '<p class="logOwnerStats">', '</div>');
            $found .= '<';
            $found = coJeMezi($found, $p, 'title="Caches Found" /> ', '<');
            $found = (int)str_replace(array(',', '.'), '', $found);

            $image = coJeMezi($response, $pozice, 'icons/', '.gif');
            switch ($image) {
                case "icon_smile" :
                    $type = "FOUND";
                    break;
                case "icon_note" :
                    $type = "NOTE";
                    break;
                case "icon_sad" :
                    $type = "DNF";
                    break;
                case "icon_greenlight" :
                    $type = "PUBLISHED";
                    break;
                case "icon_maint" :
                    $type = "MAINTANCE";
                    break;
                case "coord_update" :
                    $type = "UPDATE";
                    break;
                case "icon_disabled" :
                    $type = "DISABLED";
                    break;
                case "icon_attended" :
                    $type = "ATTENDED";
                    break;
                default :
                    $type = "?";
                    break;

            }
            $date = coJeMezi($response, $pozice, 'LogDate">', '</span>');
            //$pozice = strpos($response, "underline;\">", $pozice) + 10;

            /*if ($type=='PUBLISHED') {
              $found = 0;
      } else {
        $found = '?';
      }*/
            $log = odstranHTML(coJeMezi($response, $pozice, '<p class="LogText">', '</p>'));
            if ($logs > 5)
                Vypis($type .
                    '}' . replaceBrackets($name) . '}' . $found . '}' . $date . '}' . replaceBrackets($log) . '{');
        }
    }
} elseif ($part == "trackable") {
    $trnumber = $_GET["trnumber"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/track/details.aspx?tracker=' . $trnumber);
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, 'does not exist') !== false) {
        Vypis("WRONG_TRACKING_NUMBER");
    } else {
        $owner = '';
        $released = '';
        $origin = '';
        $cache = '';

        $pozice = 0;
        $name = coJeMezi($response, $pozice, '<span id="ctl00_ContentBody_lbHeading">', '</span>');

        $pozice = strpos($response, 'ctl00_ContentBody_BugDetails_BugOwner', $pozice);
        if ($pozice !== false) {
            $owner = coJeMezi($response, $pozice, '>', '</a>');
        }

        $pozice = strpos($response, 'ctl00_ContentBody_BugDetails_BugReleaseDate', $pozice);
        if ($pozice !== false) {
            $released = coJeMezi($response, $pozice, '>', '</span>');
        }

        $pozice = strpos($response, 'ctl00_ContentBody_BugDetails_BugOrigin', $pozice);
        if ($pozice !== false) {
            $origin = coJeMezi($response, $pozice, '>', '</span>');
        }

        $pozice = strpos($response, 'ctl00_ContentBody_BugDetails_BugLocation', $pozice);
        if ($pozice !== false) {
            $cache = odstranHTML(coJeMezi($response, $pozice, '>', '</a>'));
        }

        $goal = odstranHTML(coJeMezi($response, $pozice, '</h3>', '<h3>'));
        $about = odstranHTML(coJeMezi($response, $pozice, '</h3>', '<div id="ctl00_ContentBody_BugDetails_uxAbuseReport">'));
        Vypis(replaceBrackets(html_entity_decode_utf8($name)) . "}" . replaceBrackets(html_entity_decode_utf8($origin)) . "}" . replaceBrackets(html_entity_decode_utf8($cache)) . "}" . $released . "}" . replaceBrackets(html_entity_decode_utf8($owner)) . "}" . replaceBrackets($goal) . "}" . replaceBrackets($about) . "{");
    }
} elseif ($part == "patterns") {
    $waypoint = $_GET["waypoint"];
    $cookie = $_GET["cookie"];

    $req = new SimpleHttpRequest('https://www.geocaching.com/seek/cache_details.aspx?wp=' . $waypoint .
        '+&Submit6=Go');
    $req->setMethod(SimpleHttpRequest::METHOD_GET);
    addHeaders($req);

    $req->sendRequest();
    UserSessionHandler::handleHTTPCookies($req);

    $response = $req->getResponseBody();
    if (!$req->getResponseBody())
        die('err:TIMEOUT');
    unset($req);

    //data mining
    if (strpos($response, 'Cache is Unpublished"')) {
        Vypis("ERR_BAD_WAYPOINT");
    } else {
        $pozice = 0;
        $patterntext = coJeMezi($response, $pozice, '<!--Handy Geocaching patterns:', '-->');
        $rows = explode('||', $patterntext);
        for ($i = 0; $i < count($rows); $i++) {
            if (trim($rows[$i]) != "") {
                $items = explode('|', $rows[$i]);
                Vypis(trim($items[0]) . "}" . trim($items[1]) . "}" . trim($items[2]) . "{");
            }
        }
    }
}

//vlastni vypis
if (strlen($content) == 0)
    $content = "NO_CONTENT";

$content = AutoCzech($content, "utf");
$content = str_replace('&amp;', '&', $content);
$content = str_replace('&quot;', '"', $content);

header("Content-type: text/plain; charset=utf-8");
header("Content-length: " . strlen($content));
echo $content;

function analyze_logs($arr) {
    $logs = Array();

    foreach ($arr['data'] AS $item) {
        $type = strtoupper(html_entity_decode_utf8($item['LogType']));
        $name = html_entity_decode_utf8($item['UserName']);
        $found = $item['GeocacheFindCount'];
        $date = $item['Visited'];
        $log = odstranHTML($item['LogText']);

        $logs[] = Array(
            'type' => $type,
            'name' => $name,
            'found' => $found,
            'date' => $date,
            'log' => $log
        );
    }
    return $logs;
}

function directionString($direction) {
    //uhel na S,J,V,Z a kombinace
    $quadrants = Array('S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ', '-');

    $direction += 22.5;
    if ($direction >= 360)
        $direction -= 360;

    $quadrant = (int)($direction / 45);

    return $quadrants[$quadrant];
}

function directionToCzech($direction) {
    $quadrantsEN = Array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW');
    $quadrantsCZ = Array('S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ');

    $quadrant = array_search($direction, $quadrantsEN);
    if ($quadrant === NULL)
        return $direction;

    return $quadrantsCZ[$quadrant];
}


function getImgData($data) {
    $key = "signalthefrog";
    $data = urldecode($data);

    for ($i = 0; $i < strlen($data); $i++) {
        $y = $i % strlen($key);
        $data[$i] = $data[$i] ^ $key[$y];
    }

    return explode('|', $data);
}

function coJeMezi(&$response, &$pozice, $prvni, $druhy) {
    //global $pozice;
    //global $response;
    $pozice2 = strpos($response, $prvni, $pozice);
    $pozice3 = strpos($response, $druhy, $pozice2);
    $pozice = $pozice3;
    return trim(substr($response, $pozice2 + strlen($prvni), ($pozice3 - $pozice2 - strlen($prvni))));
}

function Vypis($text) {
    //global $content;
    //echo $text;
    //flush();
    $GLOBALS['content'] .= $text;
    //echo $text;
}

function smiles_decode($text) {
    $smiles = Array(
        'smile' => ':)',
        'smile_big' => ':D',
        'smile_cool' => '8D',
        'smile_blush' => ':I',
        'smile_tongue' => ':P',
        'smile_evil' => '}:)',
        'smile_wink' => ';)',
        'smile_blackeye' => 'B)',
        'smile_8ball' => '8',
        'smile_sad' => ':(',
        'smile_shy' => '8)',
        'smile_shock' => ':O',
        'smile_angry' => ':(!',
        'smile_dead' => 'xx(',
        'smile_sleepy' => '|)',
        'smile_kisses' => ':x',
        'smile_approve' => '(^)',
        'smile_dissapprove' => '(V)',
        'smile_question' => '(?)'
    );

    foreach ($smiles AS $key => $smile) {
        $text = str_replace("<img src=\"/images/icons/icon_$key.gif\" border=\"0\" align=\"middle\" >", $smile, $text);
    }

    return $text;
}

function odstranHTML($text, $noNewLine = true) {
    //prevedeni <br /> na \n
    $text = smiles_decode($text);
    $text = str_replace("<br/>", "\n", $text);
    $text = str_replace("<br />", "\n", $text);
    $text = str_replace("<br>", "\n", $text);

    //zbaveni textu html znaku
    $ignoruji = false;
    $text2 = "";
    for ($i = 0; $i < strlen($text); $i++) {
        if ($text[$i] == "<") {
            $ignoruji = true;
        } elseif ($ignoruji && $text[$i] == ">") {
            $ignoruji = false;
        } elseif (!$ignoruji) {
            $text2 .= $text[$i];
        }

    }

    //odstraneni nezadoucich znaku
    $text2 = html_entity_decode_utf8($text2);

    //$text2 = html_entity_decode($text2, ENT_QUOTES, "UTF-8");
    //odstraneni prebytecnych \n a mezer
    if ($noNewLine) {
        $text2 = str_replace("\r\n", " ", $text2);
        $text2 = str_replace("\n", " ", $text2);
        $text2 = str_replace("  ", " ", $text2);
        $text2 = str_replace("  ", " ", $text2);
        $text2 = str_replace("  ", " ", $text2);
    }

    return $text2;
}


function html_entity_decode_utf8($string) {
    return html_entity_decode($string, ENT_QUOTES, "UTF-8");
}

// Returns the utf string corresponding to the unicode value (from php.net, courtesy - romans@void.lv)
function code2utf($num) {
    if ($num < 128)
        return chr($num);
    if ($num < 2048)
        return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
    if ($num < 65536)
        return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    if ($num < 2097152)
        return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    return '';
}

function replaceBrackets($str) {
    return str_replace("{", "(", str_replace("}", ")", $str));
}

/**
 * @param $req SimpleHttpRequest
 */
function addHeaders(&$req, $skipCookies = false) {
    if (!$skipCookies)
        UserSessionHandler::prepareHTTPCookies($req);

    $req->addHeader('User-Agent', 'Mozilla/5.0 (Windows NT 5.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1');
    $req->addHeader('Accept', 'text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5');
    $req->addHeader('Accept-Charset', 'ISO-8859-1,utf-8;q=0.7,*;q=0.7');
    $req->addHeader('Accept-Encoding', ''); //podpora vsech
    $req->addHeader('Accept-Language', 'en-us,en;q=0.5');
}

function translateImage($image) {
    global $protocol_1_1;
    switch ($image) {
        case "2":
            return "gc_traditional";
            break;
        case "3":
            return "gc_multi";
            break;
        case "4":
            return "gc_virtual";
            break;
        case "5":
            return "gc_letter";
            break;
        case "6":
        case "453": //mega-event
        case "3774": //lost and found
        case "mega": //mega-event
            return "gc_event";
            break;
        case "8":
            return "gc_unknown";
            break;
        case "11":
            return "gc_webcam";
            break;
        case "12":
            return "gc_locationless";
            break;
        case "13":
            return "gc_cito";
            break;
        case "137":
            return "gc_earthcache";
            break;
        case "1858":
            if ($protocol_1_1) {
                return "gc_wherigo";
            } else {
                return "gc_unknown";
            }
            break;
        case "earthcache":
            return "gc_earthcache";
            break;
        default:
            return "gc_unknown";
    }
}

function stripSlashes2($string) {
    $string = str_replace("\\\"", "\"", $string);
    $string = str_replace("\\'", "'", $string);
    $string = str_replace("\\\\", "\\", $string);
    return $string;
}
