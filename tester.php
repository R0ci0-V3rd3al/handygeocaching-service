<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
    <title>Test</title>
</head>
<body>
<script charset="utf-8" type="text/javascript">
    /* <![CDATA[ */

    function sessionId(name, password) {
        var sid = name.length;
        sid += "-" + password.length;

        for (var i = 0; i < name.length; i++) {
            sid += "-" + name.charCodeAt(i);
        }

        for (var i = 0; i < password.length; i++) {
            sid += "-" + password.charCodeAt(i);
        }

        if (password.length + name.length < 20) {
            for (var i = 0; i < (20 - (password.length + name.length)); i++) {
                sid += "-" + Math.floor(Math.random() * 256);
            }
        }

        return sid;
    }

    function doLogin() {
        document.getElementById("sessid").value = sessionId(document.getElementById("login").value, document.getElementById("password").value);
        return true;
    }
    /* ]]> */
</script>
<form action="old/handy31.php" method="get" onsubmit="return doLogin();">
    <fieldset>
        <input type="hidden" name="part" value="login"/>
        <input type="hidden" name="sessid" id="sessid"/>
        <input type="hidden" name="version" value="3.5.3"/>
        <legend>Login</legend>
        <p>
            <label>Login: <input type="text" id="login"/></label><br/>
            <label>Password: <input type="password" id="password"/></label><br/>
            <input type="submit" value="Login"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="nearest"/>
        <legend>Nearest</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>Latitude: <input type="text" name="lattitude" value="50"/></label><br/>
            <label>Longitude: <input type="text" name="longitude" value="14"/> </label><br/>
            <label>Filter: <input type="text" name="filter" value="11111"/> </label><br/>
            <label>NumberCaches: <input type="text" name="numberCaches" value="20"/> </label><br/>
            <input type="submit" value="Nearest"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="keyword"/>
        <legend>Keyword</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>Keyword: <input type="text" name="keyword"/></label><br/>
            <label>NumberCaches: <input type="text" name="numberCaches" value="20"/></label><br/>
            <input type="submit" value="Keyword"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="overview"/>
        <legend>Overview</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>CacheID: <input type="text" name="waypoint" value="GCY81P"/></label><br/>
            <input type="submit" value="Overview"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="info"/>
        <legend>Listing (info)</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>CacheID: <input type="text" name="waypoint" value="GC1BQVM"/></label><br/>
            <input type="submit" value="Listing"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="hint"/>
        <legend>Hint</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>CacheID: <input type="text" name="waypoint" value="GC1BQVM"/></label><br/>
            <input type="submit" value="Hint"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="waypoints"/>
        <legend>Waypoints</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>CacheID: <input type="text" name="waypoint" value="GCY81P"/></label><br/>
            <input type="submit" value="Waypoints"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="logs"/>
        <legend>Logs</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>CacheID: <input type="text" name="waypoint" value="GCY81P"/></label><br/>
            <input type="submit" value="Logs"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="alllogs"/>
        <legend>All Logs</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>Guideline: <input type="text" name="guideline"/></label><br/>
            <input type="submit" value="All Logs"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="trackable"/>
        <legend>Trackables</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>Number: <input type="text" name="trnumber" value="TB3GW4D"/></label><br/>
            <input type="submit" value="All Logs"/>
        </p>
    </fieldset>
</form>

<form action="old/handy31.php" method="get">
    <fieldset>
        <input type="hidden" name="part" value="patterns"/>
        <legend>Pattern</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label>CacheID: <input type="text" name="waypoint" value="GC1BQVM"/></label><br/>
            <input type="submit" value="Pattern"/>
        </p>
    </fieldset>
</form>

<form action="api.php" method="post">
    <fieldset>
        <input type="hidden" name="action" value="fieldnotes"/>
        <legend>Field notes</legend>
        <p>
            <label>Session: <input type="text" name="cookie"/></label><br/>
            <label><input type="checkbox" name="incremental" value="1"/> Incremental</label><br/>
            <textarea title="Field note" name="fieldnotes" cols="50"
                      rows="5"><?php echo htmlspecialchars(generateFieldNotes()); ?></textarea><br/>
            <input type="submit" value="Field notes"/></p>
    </fieldset>
</form>
</body>
</html>
<?php

function generateFieldNotes() {
    $date = gmdate('Y-m-d\TH:i\Z', time() - 3600);
    $ret = "GC1BQVM,{$date},Found it,\"test1 ěščřžýáíé\"\r\n";
    $date = gmdate('Y-m-d\TH:i\Z', time());
    $ret .= "GCY81P,{$date},Found it,\"test2 ĚŠČŘŽÝÁÍÉ\"";

    return $ret;
}
