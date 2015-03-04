# SilverStripe Active Directory module

[![Build Status](https://secure.travis-ci.org/silverstripe/activedirectory.png)](https://travis-ci.org/silverstripe/activedirectory)

## Introduction

This module allows you to use single sign-on on your SilverStripe site using SAML 2.0.
It also allows you to import Active Directory users and groups to SilverStripe.

## Requirements

 * PHP 5.3.3+ with extensions: ldap, openssl, dom, and mcrypt
 * SilverStripe 3.1+
 * Active Directory and ADFS 2.0

## Installation

In your existing SilverStripe project, install this module using composer:

	composer require "silverstripe/activedirectory:*"

## Configuration

The configuration and setup from beginning can look like this:

 1. Install an Microsoft AD server
 2. Install / Update AD FS to version 2.0 on the Microsoft server
 3. Configure AD FS 2.0
 3. Install SilverStripe active directory module
 4. Setup SilverStripe site SAML configuration (for step 6)
 5. Setup SilverStripe site LDAP configuration
 6. Create SAML Relying Party in ADFS on the Microsoft server
 7. Configure SilverStripe Authenticators

### Configure AD FS 2.0

[AD FS 2.0 setup and configuration](docs/en/adfs_setup.md).

### Setup SilverStripe site SAML configuration

[SAML Service Provider (SP) Setup](docs/en/saml_setup.md)

### Setup SilverStripe site LDAP configuration

[LDAP Setup](docs/en/ldap_setup.md)

### Configure SilverStripe Authenticators

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
