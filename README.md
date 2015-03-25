# SilverStripe Active Directory module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-activedirectory.svg)](https://travis-ci.org/silverstripe/silverstripe-activedirectory)

## Introduction

This module allows you to use single sign-on on your SilverStripe site using SAML 2.0.
It also allows you to import Active Directory users and groups to SilverStripe.

## Requirements

 * PHP 5.3.3+ with extensions: ldap, openssl, dom, and mcrypt
 * SilverStripe 3.1+
 * Active Directory and ADFS 2.0 (Active Directory Federation Services)
 
## Installation & Configuration

The configuration and setup from beginning can look like this:

 1. Install a Microsoft AD server
 2. Install / Update AD FS to version 2.0 on the Microsoft server
 3. Configure AD FS 2.0
 4. Install SilverStripe active directory module
 5. Setup SilverStripe site SAML configuration with SP info
 6. Create SAML Relying Party in ADFS on the Microsoft server
 7. Setup SilverStripe site SAML configuration with IdP info
 8. Setup SilverStripe site LDAP configuration
 9. Configure SilverStripe Authenticators

### 1. Install a Microsoft AD server

This module has only been tested using Active Directory on Windows Server 2008 R2.

Providing instructions on how to install an AD server is out of scope for this module,
but there are many resources to be found via Google.

### 2. Install / Update AD FS to version 2.0 on the Microsoft server

To be able to use the SAML Single Sign On functionality you need to have ADFS 2.0.
In some cases ADFS 1.0 is installed, but you can upgrade for free with [an update from Microsoft](http://www.microsoft.com/en-us/download/details.aspx?id=10909).

[Installing Active Directory Federation Services (ADFS) 2.0](http://pipe2text.com/?page_id=285) information is available.

If you're exposing the SAML endpoint over HTTPS, you also need to make sure that that there is a SSL certificate that matches the web endpoint.

The client browser will use this endpoint for SSO purposes e.g: https://adfs-server.test.com/adfs/ls/

### 3. Configure AD FS 2.0

You need to ensure that ADFS 2.0 is setup correctly with certificates and proper domain names for the endpoints.

@todo fill out with more information if necessary

### 4. Install SilverStripe active directory module

	composer require "silverstripe/activedirectory:*"

### 5. Setup SilverStripe site SAML configuration with SP info

Note that you will not be able to setup the IdP configuration until step 6 has been done

[AD FS 2.0 setup and configuration](docs/en/saml_setup.md).

### 6. Create SAML Relying Party in ADFS on the Microsoft server

[AD FS 2.0 setup and configuration](docs/en/adfs_setup.md).

### 7. Setup SilverStripe site SAML configuration with IdP info
 
With the information from step 6 you should be able to setup the IdP endpoint and certificate.

### 8. Setup SilverStripe site LDAP configuration

[LDAP Setup](docs/en/ldap_setup.md)

### 9. Configure SilverStripe Authenticators

To be able to use the SAML or the LDAP authenticator you will need to set them up in the
`mysite/config.php`.

You can pick and choose between the SAML, LDAP and the default authenticator.

	Authenticator::set_default_authenticator('SAMLAuthenticator');
	Authenticator::register_authenticator('SAMLAuthenticator');
	Authenticator::register_authenticator('LDAPAuthenticator');

You can unregister the default authenticator by adding this line

	Authenticator::unregister('MemberAuthenticator');

But you shouldn't do that before you have mapped an LDAP group to the SilverStripe `Administrator`
Security Group, since no user would have access to the SilverStripe Security admin.

## Usage

How to use the module to import users and map them to SilverStripe groups, etc.

[Usage docs](docs/en/usage.md)

## Debugging

There are certain parts of his module that have debugging messages logged. You can configure logging to receive
these via email, for example. In your `mysite/_config.php`:

	SS_Log::add_writer(new SS_LogEmailWriter('my@email.com'), SS_Log::DEBUG, '<=');

## Technical notes

### Interface between SAML and LDAP

The SAML and LDAP parts of this module interact only through the following two locations:

* `GUID` field on `Member`, added by both `SAMLMemberExtension` and `LDAPMemberExtension`.
* `LDAPMemberExtension::memberLoggedIn` login hook, triggered after any login (including after
`SAMLAuthenticator::authenticate`)

### SAML+LDAP sequence

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

### LDAP only sequence

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

### Member record manipulation

`Member` records are manipulated from multiple locations in this module. Members are identified by GUIDs by both LDAP
and SAML components.

* `SAMLAuthenticator::authenticate`: creates stub `Member` after authorisation (if non-existent).
* `LDAPAuthenticator::authenticate`: creates stub `Member` after authorisation (if non-existent).
* `LDAPMemberExtension::memberLoggedIn`: triggers LDAP synchronisation, rewriting all `Member` fields.
* `LDAPMemberSyncTask::run`: pulls all LDAP records and creates relevant `Members`.
