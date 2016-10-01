<?php

class UserSessionHandler {
    const LIFETIME = 3600;

    public static function prepareSession() {
        if (isset($_GET['cookie'])) {
            session_id($_GET['cookie']);
        }
        session_start();

        if (!isset($_SESSION['lifetime']) || $_SESSION['lifetime'] < time()) {
            session_destroy();
            session_start();
            $_SESSION['lifetime'] = time() + self::LIFETIME;
            $_SESSION['cookies'] = Array();

            return false;
        }

        $_SESSION['lifetime'] = time() + self::LIFETIME;

        return true;
    }

    public static function getId() {
        return session_id();
    }

    /**
     * @param $req SimpleHttpRequest
     */
    public static function handleHTTPCookies(&$req) {
        $cookies = $req->getResponseCookies();
        foreach ($cookies AS $cookie) {
            $_SESSION['cookies'][$cookie['name']] = $cookie['value'];
        }
    }

    /**
     * @param $req SimpleHttpRequest
     */
    public static function prepareHTTPCookies(&$req) {
        foreach ($_SESSION['cookies'] AS $key => $value) {
            $req->addCookie($key, $value);
        }
    }
}
