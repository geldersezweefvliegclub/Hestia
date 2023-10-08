<?php


class HeliosPrincipalBackend extends Sabre\DAVACL\PrincipalBackend\AbstractBackend {
    public function getPrincipalsByPrefix($prefix)
    {
        Debug(__FILE__, __LINE__, sprintf("getPrincipalsByPrefix(%s)", $prefix));

        return
            [
                'id' => 1,
                'uri' => "gezc/leden",
                '{DAV:}displayname' => "Gelderse Zweefvliegclub"
            ];
    }

    public function getPrincipalByPath($path)
    {
        Debug(__FILE__, __LINE__, sprintf("getPrincipalByPath(%s)", $path));
        $login = HeliosAuthBackend::LoginInfoByLogin($path);
        $principal =
            [
                'id' => $login['ID'],
                'uri' => $path,
                '{DAV:}displayname' => "Gelderse Zweefvliegclub"
            ];
        Debug(__FILE__, __LINE__, json_encode($principal));
        return $principal;
    }

    public function updatePrincipal($path, $mutations)
    {
        Debug(__FILE__, __LINE__, sprintf("updatePrincipal(%s, %s)", $path, $mutations));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        Debug(__FILE__, __LINE__, sprintf("searchPrincipals(%s, %s, %s)", $prefixPath, json_encode($searchProperties), $test));
        return
            [
                'uri' => "gezc/leden"
            ];
    }

    public function getGroupMemberSet($principal)
    {
        Debug(__FILE__, __LINE__, sprintf("getGroupMemberSet(%s)", $principal));
        // not implemented, this could return all principals for a share-all calendar server
        return array();
    }

    public function setGroupMemberSet($principal, array $members)
    {
        Debug(__FILE__, __LINE__, sprintf("setGroupMemberSet(%s, %s)", $principal, json_encode($members)));
        throw new Exception\NotImplemented('Not Implemented');
    }

    public function getGroupMembership($principal)
    {
        Debug(__FILE__, __LINE__, sprintf("getGroupMembership(%s)", $principal));
        // not implemented, this could return a list of all principals
        // with two subprincipals: calendar-proxy-read and calendar-proxy-write for a share-all calendar server
        return [];
    }
}



