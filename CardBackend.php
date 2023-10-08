<?php

declare(strict_types=1);

use Sabre\CardDAV;
use Sabre\DAV;
use Sabre\DAV\PropPatch;

class HeliosCardDAVBackend extends Sabre\CardDAV\Backend\AbstractBackend implements Sabre\CardDAV\Backend\SyncSupport {

    public function getCards($addressBookId) {
        Debug(__FILE__, __LINE__, sprintf("getCards(%s)", $addressBookId));
        $login = HeliosAuthBackend::LoginInfoByID($addressBookId);

        Helios::$username = $login['INLOGNAAM'];
        Helios::$token = $login['WACHTWOORD'];
        $vcards = Helios::vCards();
        return $vcards;
    }

    public function createCard($addressBookId, $cardUri, $cardData){
        Debug(__FILE__, __LINE__, sprintf("createCard(%s, %s, %s)", $addressBookId, $cardUri, $cardData));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function updateCard($addressBookId, $cardId, $cardData) {
        Debug(__FILE__, __LINE__, sprintf("updateCard(%s, %s, %s)", $addressBookId, $cardId, $cardData));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function deleteCard($addressBookId, $cardId) {
        Debug(__FILE__, __LINE__, sprintf("deleteCard(%s, %s)", $addressBookId, $cardId));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function createAddressBook($principalUri, $url, array $properties)
    {
        Debug(__FILE__, __LINE__, sprintf("createAddressBook(%s, %s, %s)", $principalUri, $url, json_encode($properties)));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function getAddressBooksForUser($principalUri)
    {
        global $helios_settings;

        Debug(__FILE__, __LINE__, sprintf("getAddressBooksForUser(%s)", $principalUri));

        // Beheerder moet audit tabel opvragen
        Helios::$username = $helios_settings['username'];
        Helios::$token = sha1(strtolower ($helios_settings['username']) . $helios_settings['password']);
        $currentSyncToken = Helios::LaatsteChangeLeden();

        $login = HeliosAuthBackend::LoginInfoByLogin($principalUri);
        Helios::$username = $login['INLOGNAAM'];
        Helios::$token = $login['WACHTWOORD'];          // opgeslagen wachtewoord is een token

        $addressBooks[] = [
            'id'  => $login['ID'],
            'uri' => "default",
            'principaluri' => $principalUri,
            '{DAV:}displayname' => "Adresboek GeZC",
            '{' . CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => "Actueel leden bestand GeZC",
            '{http://calendarserver.org/ns/}getctag' => $currentSyncToken,
            '{http://sabredav.org/ns}sync-token' => $currentSyncToken
            ];
        Debug(__FILE__, __LINE__, json_encode($addressBooks));
        return $addressBooks;
    }

    public function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch)
    {
        Debug(__FILE__, __LINE__, sprintf("updateAddressBook(%s, %s)", $addressBookId, json_encode($propPatch)));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function deleteAddressBook($addressBookId)
    {
        Debug(__FILE__, __LINE__, sprintf("deleteAddressBook(%s)", $addressBookId));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null)
    {
        global $helios_settings;

        Debug(__FILE__, __LINE__, sprintf("getChangesForAddressBook(%s, %s, %s, %s)", $addressBookId, $syncToken, $syncLevel, $limit));

        // Beheerder moet audit tabel opvragen
        Helios::$username = $helios_settings['username'];
        Helios::$token = sha1(strtolower ($helios_settings['username']) . $helios_settings['password']);
        $currentSyncToken = Helios::LaatsteChangeLeden();

        $result = [
            'syncToken' => $currentSyncToken,
            'added' => [],
            'modified' => [],
            'deleted' => [],
        ];

        if ($syncToken)
        {
            $dataset = Helios::ChangesLedenSinds($syncToken, $limit);

            if (is_null($dataset) || count($dataset) == 0) {
                return $result;
            }

            foreach ($dataset as $record) {
                switch ($record['ACTIE']) {
                    case "Toevoegen":
                        $r = json_decode($record['RESULTAAT']);
                        $lidID = $r->ID;

                        Debug(__FILE__, __LINE__, sprintf("Toevoegen: %s", $lidID));
                        $result['added'][$lidID] = $lidID;
                        break;
                    case "Hersteld":
                        $r = json_decode($record['RESULTAAT']);
                        $lidID = $r->ID;

                        if (array_key_exists($lidID, $result['deleted'])) {
                            Debug(__FILE__, __LINE__,"unset deleted");
                            unset($result['deleted'][$lidID]);
                        }
                        Debug(__FILE__, __LINE__, sprintf("Hersteld: %s", $lidID));
                        $result['added'][$lidID] = $lidID;
                        break;
                    case "Aanpassen":
                        $r = json_decode($record['RESULTAAT']);
                        $lidID = $r->ID;

                        if (!array_key_exists($lidID, $result['added']) &&
                            !array_key_exists($lidID, $result['modified']) &&
                            !array_key_exists($lidID, $result['deleted']))
                        {
                            Debug(__FILE__, __LINE__, sprintf("Aanpassen: %s", $lidID));
                            $result['modified'][$lidID] = $lidID;
                        }

                        //TODO als lidtype aangepast is
                        break;
                    case "Verwijderd":
                        $r = json_decode($record['VOOR']);
                        $lidID = $r->ID;

                        if (array_key_exists($lidID, $result['added'])) {
                            Debug(__FILE__, __LINE__,"unset added");
                            unset($result['added'][$lidID]);
                        }

                        if (array_key_exists($lidID, $result['modified'])) {
                            Debug(__FILE__, __LINE__,"unset modified");
                            unset($result['modified'][$lidID]);
                        }

                        Debug(__FILE__, __LINE__, sprintf("Verwijderd: %s", $lidID));
                        $result['deleted'][$lidID] = $lidID;
                        break;
                }
            }
        } else {
            $dataset = Helios::AlleLeden();

            foreach ($dataset as $record) {
                $lidID = $record['ID'];
                $result['added'][$lidID] = $lidID;
            }
        }

        $result['added'] = array_values($result['added']);
        $result['modified'] = array_values($result['modified']);
        $result['deleted'] = array_values($result['deleted']);

        Debug(__FILE__, __LINE__, sprintf("result: %s", json_encode($result)));

        // Zet userdata terug (voorkom dat andere Helios calls worden uitgevoerd met beheerdersaccount)
        $login = HeliosAuthBackend::LoginInfoByID($addressBookId);
        Helios::$username = $login['INLOGNAAM'];
        Helios::$token = $login['WACHTWOORD'];      // opgeslagen wachtewoord is een token

        return $result;
    }

    public function getCard($addressBookId, $cardUri)
    {
        Debug(__FILE__, __LINE__, sprintf("getCard(%s, %s)", $addressBookId, $cardUri));

        // LidID zit versleuteld in cardUri
        $o = substr($cardUri,0, -4); // eerst .vcf eraf
        $lidID= explode(":", strrev(base64_decode($o)))[1];

        $login = HeliosAuthBackend::LoginInfoByID($addressBookId);
        Helios::$username = $login['INLOGNAAM'];
        Helios::$token = $login['WACHTWOORD'];      // opgeslagen wachtewoord is een token
        $result = Helios::vCard($lidID);

        if ($result == null) {
            return false;
        }
        return $result;
    }

    public function getMultipleCards($addressBookId, array $uris)
    {
        Debug(__FILE__, __LINE__, sprintf("getMultipleCards(%s, %s)", $addressBookId, json_encode($uris)));

        $login = HeliosAuthBackend::LoginInfoByID($addressBookId);
        Helios::$username = $login['INLOGNAAM'];
        Helios::$token = $login['WACHTWOORD'];          // opgeslagen wachtewoord is een token

        $result = Helios::vCards($uris);

        if ($result == null) {
            return [];
        }
        return $result;
    }
}

