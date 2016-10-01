<?php

/* GNU knihovna pro automaticky prevod do kodovani iso, win, utf     */
/* Radek HULAN                                                       */
/* http://hulan.info/blog                                            */

function codeToEncoding($code) {
    switch ($code) {
        case 'iso':
            return 'ISO-8859-2';
        case 'win':
            return 'CP1250';
        case 'asc':
            return 'ISO-8859-1';
        case 'utf':
            return 'UTF-8';
    }
    return $code;
}

function AutoCzech($str, $code) {
    $win = array('Á', 'Č', 'Ď', 'É', 'Ě', 'Í', 'Ň', 'Ó', 'Ř', 'Š', 'Ť', 'Ú', 'Ů', 'Ý', 'Ž', 'á', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ř', 'š', 'ť', 'ú', 'ů', 'ý', 'ž');
    $iso = array('Á', 'Č', 'Ď', 'É', 'Ě', 'Í', 'Ň', 'Ó', 'Ř', '©', '«', 'Ú', 'Ů', 'Ý', '®', 'á', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ř', 'ą', '»', 'ú', 'ů', 'ý', 'ľ');
    $utf = array("\xc3\x81", "\xc3\x88", "\xc3\x8f", "\xc3\x89", "\xc3\x83", "\xc3\x8d", "\xc3\x92", "\xc3\x93", "\xc3\x98", "\xc5\xa0", "\xc2\x8d", "\xc3\x9a", "\xc3\x99", "\xc3\x9d", "\xc5\xbd", "\xc3\xa1", "\xc3\xa8", "\xc3\xaf", "\xc3\xa9", "\xc3\xac", "\xc3\xad", "\xc3\xb2", "\xc3\xb3", "\xc3\xb8", "\xc5\xa1", "\xc2\x9d", "\xc3\xba", "\xc3\xb9", "\xc3\xbd", "\xc5\xbe");
    // pocty
    $_win = 0;
    $_iso = 0;
    $_utf = 0;
    // spocitam pocet vyskytu v retezci
    for ($i = 0; $i < 30; $i++) {
        $_win += substr_count($str, $win[$i]);
        $_iso += substr_count($str, $iso[$i]);
        $_utf += substr_count($str, $utf[$i]);
    }
    // nejvyssi vyskyt
    if ($_utf > 0)
        return iconv('UTF-8', codeToEncoding($code), $str);
    if ($_iso >= $_win && $_iso > 0)
        return iconv('ISO-8859-2', codeToEncoding($code), $str);
    if ($_win >= $_iso && $_win > 0)
        return iconv('CP1250', codeToEncoding($code), $str);
    return iconv('UTF-8', codeToEncoding($code), $str);
}
