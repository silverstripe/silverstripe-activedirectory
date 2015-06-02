# Technical notes

## Interface between SAML and LDAP

The SAML and LDAP parts of this module interact only through the following two locations:

* `GUID` field on `Member`, added by both `SAMLMemberExtension` and `LDAPMemberExtension`.
* `LDAPMemberExtension::memberLoggedIn` login hook, triggered after any login (including after
`SAMLAuthenticator::authenticate`)

## SAML+LDAP sequence

Normal sequence, involving single sign-on and LDAP synchronisation:

1. User requests a secured resource, and is redirected to `SAMLLoginForm`
1. User clicks the only button on the form
1. `SAMLAuthenticator::authenticate` is called
1. User is redirected to an Identity Provider (IdP), by utilising the `SAMLHelper` (and the contained library)
1. User performs the authentication off-site
1. User is sent back to `SAMLController::acs`, with an appropriate authentication token
1. If `Member` record is not found, stub is created with some basic fields (i.e. GUID, name, surname, email), but no group
mapping.
1. User is logged into SilverStripe as that member, considered authenticated. GUID is used to uniquely identify that
user.
1. A login hook is triggered at `LDAPMemberExtension::memberLoggedIn`
1. LDAP synchronisation is performed by looking up the GUID. All `Member` fields are overwritten with the data obtained
from LDAP, and LDAP group mappings are added.
1. User is now authorised, since the group mappings are in place.

## LDAP only sequence

LDAP-only sequence:

1. User requests a secured resource, and is redirected to `LDAPLoginForm`
1. User fills in the credentials
1. `LDAPAuthenticator::authenticate` is called
1. Authentication against LDAP is performed by SilverStripe's backend.
1. If `Member` record is not found, stub is created with some basic fields (i.e. GUID), but no group mapping.
1. A login hook is triggered at `LDAPMemberExtension::memberLoggedIn`
1. LDAP synchronisation is performed by looking up the GUID. All `Member` fields are overwritten with the data obtained
from LDAP, and LDAP group mappings are added.
1. User is logged into SilverStripe as that member, considered authenticated and authorised (since the group mappings
are in place)

## Member record manipulation

`Member` records are manipulated from multiple locations in this module. Members are identified by GUIDs by both LDAP
and SAML components.

* `SAMLAuthenticator::authenticate`: creates stub `Member` after authorisation (if non-existent).
* `LDAPAuthenticator::authenticate`: creates stub `Member` after authorisation (if non-existent).
* `LDAPMemberExtension::memberLoggedIn`: triggers LDAP synchronisation, rewriting all `Member` fields.
* `LDAPMemberSyncTask::run`: pulls all LDAP records and creates relevant `Members`.
