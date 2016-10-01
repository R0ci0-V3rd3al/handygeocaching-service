<?php

class SimpleHttpRequest {
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    private $_cookies = Array();
    private $_method = self::METHOD_GET;
    private $_postData = Array();
    private $_url;
    private $_requestHeaders = Array();
    private $_responseBody = '';

    public function __construct($url) {
        $this->_url = $url;
    }

    public function __destruct() {
        unset($this->_cookies);
        unset($this->_method);
        unset($this->_postData);
        unset($this->_url);
        unset($this->_requestHeaders);
        unset($this->_responseBody);
    }

    public function sendRequest() {
        $ch = curl_init($this->_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 19);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if (isset($this->_requestHeaders['accept-encoding']))
            curl_setopt($ch, CURLOPT_ENCODING, $this->_requestHeaders['accept-encoding']);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_buildCUrlHeader());

        if ($this->_method == self::METHOD_POST) {
            curl_setopt($ch, CURLOPT_POST, 1);

            $postData = '';
            if (!is_array($this->_postData)) {
                $postData = $this->_postData;
            } else {
                foreach ($this->_postData AS $name => $value)
                    $postData .= $name . '=' . $value . '&';
                $postData = rtrim($postData, '&');
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, '_curlReadHeader'));
        $this->_responseBody = $this->_curl_redirect_exec($ch);
        curl_close($ch);
    }

    public function setMethod($method) {
        $this->_method = $method;
    }

    function addCookie($name, $value) {
        $cookies = isset($this->_requestHeaders['cookie']) ? $this->_requestHeaders['cookie'] . '; ' : '';
        $this->addHeader('Cookie', $cookies . $name . '=' . $value);
    }


    /**
     * Adds a request header
     *
     * @param $name string     Header name
     * @param $value string    Header value
     * @access public
     */
    public function addHeader($name, $value) {
        $this->_requestHeaders[strtolower($name)] = $value;
    }

    /**
     * Removes a request header
     *
     * @param $name string     Header name to remove
     * @access public
     */
    public function removeHeader($name) {
        if (isset($this->_requestHeaders[strtolower($name)])) {
            unset($this->_requestHeaders[strtolower($name)]);
        }
    }

    public function addPostData($name, $value, $preencoded = false) {
        if ($preencoded) {
            $this->_postData[$name] = $value;
        } else {
            $this->_postData[$name] = urlencode($value);
        }
    }

    public function setPostData($value) {
        $this->_postData = $value;
    }

    public function getResponseCookies() {
        return $this->_cookies;
    }

    public function getResponseBody() {
        return $this->_responseBody;
    }

    private function _curl_redirect_exec($ch) {
        static $curl_loops = 0;
        static $curl_max_loops = 5;

        if ($curl_loops++ >= $curl_max_loops) {
            $curl_loops = 0;
            return FALSE;
        }

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        list($header, $body) = explode("\r\n\r\n", $data, 2);
        if ($http_code == 301 || $http_code == 302) {

            while (preg_match('#100 Continue#i', $header)) {
                list ($header, $body) = explode("\r\n\r\n", $body, 2);
            }

            $matches = array();
            preg_match('/Location:(.*?)\n/i', $header, $matches);

            $url = @parse_url(trim(array_pop($matches)));
            if (!$url) {
                //couldn't process the url to redirect to
                $curl_loops = 0;
                return $data;
            }

            $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
            if (!$url['scheme'])
                $url['scheme'] = $last_url['scheme'];
            if (!$url['host'])
                $url['host'] = $last_url['host'];
            if (!$url['path'])
                $url['path'] = $last_url['path'];

            $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . (isset($url['query']) ? '?' . $url['query'] : '');

            curl_setopt($ch, CURLOPT_URL, $new_url);
            curl_setopt($ch, CURLOPT_POST, 0);

            foreach ($this->_cookies AS $cookie) {
                $this->addCookie($cookie['name'], $cookie['value']);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->_buildCUrlHeader());

            return $this->_curl_redirect_exec($ch);
        }

        $curl_loops = 0;
        return $body;
    }

    private function _buildCUrlHeader() {
        $ret = Array();

        if (empty($this->_requestHeaders['content-type'])) {
            // Add default content-type
            $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        foreach ($this->_requestHeaders AS $name => $value) {
            if ($name == 'accept-encoding')
                continue;
            $canonicalName = implode('-', array_map('ucfirst', explode('-', $name)));
            $ret[] = "{$canonicalName}: {$value}";
        }

        return $ret;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function _curlReadHeader($ch, $header) {
        if (strncmp(strtolower($header), 'set-cookie:', 11) == 0) {
            $cookie = substr($header, 11);
            $this->_parseCookie($cookie);
        }

        return strlen($header);
    }

    /* Parse a Set-Cookie header to fill $_cookies array
     *
     * @access private
     * @param  string    value of Set-Cookie header
     */
    private function _parseCookie($headervalue) {
        $cookie = array(
            'expires' => null,
            'domain' => null,
            'path' => null,
            'secure' => false
        );

        // Only a name=value pair
        if (!strpos($headervalue, ';')) {
            $pos = strpos($headervalue, '=');
            $cookie['name'] = trim(substr($headervalue, 0, $pos));
            $cookie['value'] = trim(substr($headervalue, $pos + 1));
            // Some optional parameters are supplied
        } else {
            $elements = explode(';', $headervalue);
            $pos = strpos($elements[0], '=');
            $cookie['name'] = trim(substr($elements[0], 0, $pos));
            $cookie['value'] = trim(substr($elements[0], $pos + 1));

            for ($i = 1; $i < count($elements); $i++) {
                if (false === strpos($elements[$i], '=')) {
                    $elName = trim($elements[$i]);
                    $elValue = null;
                } else {
                    list ($elName, $elValue) = array_map('trim', explode('=', $elements[$i]));
                }
                $elName = strtolower($elName);
                if ('secure' == $elName) {
                    $cookie['secure'] = true;
                } elseif ('expires' == $elName) {
                    $cookie['expires'] = str_replace('"', '', $elValue);
                } elseif ('path' == $elName || 'domain' == $elName) {
                    $cookie[$elName] = urldecode($elValue);
                } else {
                    $cookie[$elName] = $elValue;
                }
            }
        }
        $this->_cookies[] = $cookie;
    }
}
