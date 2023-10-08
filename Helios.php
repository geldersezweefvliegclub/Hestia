<?php

class Helios {
    private static $curl_session = null;
    public static $username = null;
    public static $password = null;
    public static $token = null;

    private static function heliosInit($url, $username = null, $password = null, $token = null)
    {
        global $helios_settings;
        Debug(__FILE__, __LINE__, sprintf("heliosInit(%s, %s, %s, %s)", $url, $username, $password, $token));

        if (!isset($url))
        {
            Helios::$username = $username;
            Helios::$password = $password;
            Helios::$token = $token;
        }
        else {

            if (isset(Helios::$curl_session)) {
                curl_setopt(Helios::$curl_session, CURLOPT_USERPWD, null);  // basic auth niet meer nodig, gebruik vanaf nu php session cookie
            } else {
                // init curl sessie
                Helios::$curl_session = curl_init();

                curl_setopt(Helios::$curl_session, CURLOPT_TIMEOUT, 60);
                curl_setopt(Helios::$curl_session, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt(Helios::$curl_session, CURLOPT_HEADER, true);      // curl response bevat header info

                curl_setopt(Helios::$curl_session, CURLOPT_USERPWD, Helios::$username . ":" . Helios::$password);  // basic auth
                $loginUrl = $helios_settings['url'] . "/Login/Login";

                if (isset(Helios::$token))
                    $loginUrl .= "?token=" . sha1($helios_settings['bypassToken'].Helios::$token);

                Debug(__FILE__, __LINE__, "loginUrl=" . $loginUrl);

                curl_setopt(Helios::$curl_session, CURLOPT_URL, $loginUrl);
                curl_setopt(Helios::$curl_session, CURLOPT_CUSTOMREQUEST, "GET");

                $result = curl_exec(Helios::$curl_session);
                $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
                list($header, $body) = Helios::returnHeaderBody($result);

                if ($status_code == 200) {
                    curl_setopt(Helios::$curl_session, CURLOPT_COOKIE, $header["Set-Cookie"]);
                    Helios::heliosInit($url);
                } else {

                    HestiaError(__FILE__, __LINE__, sprintf("status:%d header:%s", $status_code, json_encode($header)));
                    HestiaError(__FILE__, __LINE__, sprintf("body: %s", $body));
                    header("X-Error-Message: heliosInit() failed", true, 500);
                    header("Content-Type: text/plain");
                    die;
                }
            }
            $full_url = sprintf("%s/%s", $helios_settings['url'], $url);
            Debug(__FILE__, __LINE__, "full_url=" . $full_url);

            curl_setopt(Helios::$curl_session, CURLOPT_URL, $full_url);
        }
    }

    private static function returnHeaderBody($response)
    {
        // extract header
        $headerSize = curl_getinfo(Helios::$curl_session, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $header = Helios::getHeaders($header);

        // extract body
        $body = substr($response, $headerSize);
        return [$header, $body];
    }

    // Zet de headers in een array
    private static function getHeaders($respHeaders)
    {
        global $cookies;

        $headers = array();
        $headerText = substr($respHeaders, 0, strpos($respHeaders, "\r\n\r\n"));

        foreach (explode("\r\n", $headerText) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list ($key, $value) = explode(': ', $line);

                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    public static function UserInfo($user, $passwd)
    {
        Debug(__FILE__, __LINE__, "UserInfo()");
        Helios::heliosInit("Login/GetUserInfo");

        $result      = curl_exec(Helios::$curl_session);
        $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
        list($header, $body) = Helios::returnHeaderBody($result);

        if ($status_code != 200) // We verwachten een status code van 200
        {
            header("X-Error-Message: UserInfo() failed", true, 500);
            header("Content-Type: text/plain");
            Debug(__FILE__, __LINE__, sprintf("Exception http_status=%s header=%s", $status_code, json_encode($header)));
            return null;
        }
        $userInfo = json_decode($body, true);
        Debug(__FILE__, __LINE__, sprintf("userInfo: %s", json_encode($userInfo)));

        $ui = $userInfo['Userinfo'];
        if (($ui['isBeheerder'] === false) &&
            ($ui['isBeheerderDDWV'] === false) &&
            ($ui['isInstructeur'] === false) &&
            ($ui['isStarttoren'] == false) &&
            ($ui['isCIMT'] === false))
        {
            Debug(__FILE__, __LINE__, sprintf("%d PRIVACY:", $userInfo['LidData']['ID'], $userInfo['LidData']['PRIVACY']));
            return ($userInfo['LidData']['PRIVACY']) ? null : $userInfo;
        }
        Debug(__FILE__, __LINE__,sprintf("%d Is speciaal", $userInfo['LidData']['ID']));
        return $userInfo;
    }

    public static function LaatsteChangeLeden()
    {
        Debug(__FILE__, __LINE__, "LaatsteChange()");

        $url_args = "?TABEL=ref_leden&MAX=1&VELDEN=ID";
        Helios::heliosInit("Audit/GetObjects" . $url_args);

        $result      = curl_exec(Helios::$curl_session);
        $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
        list($header, $body) = Helios::returnHeaderBody($result);

        if ($status_code != 200) // We verwachten een status code van 200
        {
            HestiaError(__FILE__, __LINE__, sprintf("status:%d header:%s", $status_code, json_encode($header)));
            HestiaError(__FILE__, __LINE__, sprintf("body: %s", $body));
            header("X-Error-Message: LaatsteChange() failed", true, 500);
            header("Content-Type: text/plain");
            die;
        }
        $response = json_decode($body, true);

        $retValue = (count($response['dataset']) == 0) ? 0 : $response['dataset'][0]['ID'];
        Debug(__FILE__, __LINE__, sprintf("retValue: %d", $retValue));
        return $retValue;
    }

    public static function ChangesLedenSinds($id, $limit = 0)
    {
        Debug(__FILE__, __LINE__, sprintf("ChangesSinds(%s,%s)", $id, $limit));

        $url_args = sprintf("?TABEL=ref_leden&SORT=ID&BEGIN_ID=%d", $id+1);
        if ($limit > 0)
            $url_args .= sprintf("&MAX=%d",$limit);
        Helios::heliosInit("Audit/GetObjects" . $url_args);

        $result      = curl_exec(Helios::$curl_session);
        $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
        list($header, $body) = Helios::returnHeaderBody($result);

        if ($status_code != 200) // We verwachten een status code van 200
        {
            HestiaError(__FILE__, __LINE__, sprintf("status:%d header:%s", $status_code, json_encode($header)));
            HestiaError(__FILE__, __LINE__, sprintf("body: %s", $body));
            header("X-Error-Message: ChangesSinds() failed", true, 500);
            header("Content-Type: text/plain");
            die;
        }
        $response = json_decode($body, true);
        Debug(__FILE__, __LINE__, sprintf("retValue: %s", json_encode($response['dataset'])));
        return $response['dataset'];
    }

    public static function AlleLeden()
    {
        Debug(__FILE__, __LINE__, "AlleLeden()");

        $url_args = "?TYPES=601,602,603,604,605,606&VELDEN=ID";
        Helios::heliosInit("Leden/GetObjects" . $url_args);

        $result      = curl_exec(Helios::$curl_session);
        $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
        list($header, $body) = Helios::returnHeaderBody($result);

        if ($status_code != 200) // We verwachten een status code van 200
        {
            HestiaError(__FILE__, __LINE__, sprintf("status:%d header:%s", $status_code, json_encode($header)));
            HestiaError(__FILE__, __LINE__, sprintf("body: %s", $body));
            header("X-Error-Message: AlleLeden() failed", true, 500);
            header("Content-Type: text/plain");
            die;
        }
        $response = json_decode($body, true);
        Debug(__FILE__, __LINE__, sprintf("retValue: %s", json_encode($response['dataset'])));
        return $response['dataset'];
    }

    public static function vCard($id)
    {
        Debug(__FILE__, __LINE__, sprintf("vCard(%s)", $id));

        $url_args = sprintf("?ID=%d", $id);
        Helios::heliosInit("Leden/vCard" . $url_args);

        $result      = curl_exec(Helios::$curl_session);
        $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
        list($header, $body) = Helios::returnHeaderBody($result);

        if ($status_code == 404)
        {
            return null;
        }
        else if ($status_code != 200) // We verwachten een status code van 200
        {
            HestiaError(__FILE__, __LINE__, sprintf("status:%d header:%s", $status_code, json_encode($header)));
            HestiaError(__FILE__, __LINE__, sprintf("body: %s", $body));
            header("X-Error-Message: vCard() failed", true, 500);
            header("Content-Type: text/plain");
            die;
        }
        $response = json_decode($body, true);
        Debug(__FILE__, __LINE__, sprintf("retValue: %s", json_encode($response)));
        return $response;
    }

    public static function vCards($ids = null)
    {
        Debug(__FILE__, __LINE__, sprintf("vcards(%s)", json_encode($ids)));

        $url_args = ($ids == null) ? "?TYPES=601,602,603,604,605,606" : sprintf("?ID=%s", implode(",", $ids));
        Helios::heliosInit("Leden/vCards" . $url_args);

        $result      = curl_exec(Helios::$curl_session);
        $status_code = curl_getinfo(Helios::$curl_session, CURLINFO_HTTP_CODE); //get status code
        list($header, $body) = Helios::returnHeaderBody($result);

        if ($status_code != 200) // We verwachten een status code van 200
        {
            HestiaError(__FILE__, __LINE__, sprintf("status:%d header:%s", $status_code, json_encode($header)));
            HestiaError(__FILE__, __LINE__, sprintf("body: %s", $body));
            header("X-Error-Message: vCards() failed", true, 500);
            header("Content-Type: text/plain");
            die;
        }
        $response = json_decode($body, true);

        Debug(__FILE__, __LINE__, sprintf("retValue: %s", json_encode($response)));
        return $response;
    }
}
