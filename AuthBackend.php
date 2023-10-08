<?php

class HeliosAuthBackend extends Sabre\DAV\Auth\Backend\AbstractBasic {
    public function validateUserPass($username, $password)
    {
        global $db;

        Debug(__FILE__, __LINE__, sprintf("validateUserPass(%s, %s)", $username, $password));

        Helios::$username = $username;
        Helios::$token = sha1(strtolower ($username) . $password);
        $userInfo = Helios::UserInfo($username, $password);

        // opslaan meta data in database
        if (isset($userInfo)) {
            $db->DbOpvraag(sprintf("SELECT * FROM login WHERE ID=%d", $userInfo['LidData']['ID']));

            if ($db->Rows() === 0) {
                $record = array();
                $record['ID'] = $userInfo['LidData']['ID'];
                $record['INLOGNAAM'] = $userInfo['LidData']['INLOGNAAM'];
                $record['WACHTWOORD'] = sha1(strtolower ($username) . $password);

                $db->DbToevoegen('login', $record);
            } else {
                $data = $db->Data();
                $crc = dechex(crc32($data[0]['WACHTWOORD'].""));

                if ($crc !== $userInfo['LidData']['WACHTWOORD']) {
                    $record = array();
                    $record['INLOGNAAM'] = $userInfo['LidData']['INLOGNAAM'];
                    $record['WACHTWOORD'] = sha1(strtolower ($username) . $password);
                    $db->DbAanpassen('login', $userInfo['LidData']['ID'], $record);
                }
            }
            return true;
        }
        return false;
    }

    public static function LoginInfoByLogin($gebruiker)
    {
        global $db;
        Debug(__FILE__, __LINE__, sprintf("LoginInfoByLogin(%s)", $gebruiker));

        $arrStr = explode("/", $gebruiker);
        $rows = $db->DbOpvraag(sprintf("SELECT * FROM login WHERE INLOGNAAM='%s'", $arrStr[count($arrStr)-1]));

        if ($rows === 0)
            return null;

        return $db->Data()[0];
    }

    public static function LoginInfoByID($id)
    {
        global $db;
        Debug(__FILE__, __LINE__, sprintf("LoginInfoByID(%s)", $id));

        $rows = $db->DbOpvraag(sprintf("SELECT * FROM login WHERE ID=%s", $id));

        if ($rows === 0)
            return null;

        return $db->Data()[0];
    }
}
