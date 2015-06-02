# SilverStripe Active Directory module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-activedirectory.svg)](https://travis-ci.org/silverstripe/silverstripe-activedirectory)

## Introduction

This SilverStripe module provides Active Directory integration. It comes with three major components:

* Single sign-on authentication capability using SAML 2.0
* Synchronisation of Active Directory users and group memberships via LDAP
* Active Directory authentication via LDAP binding

These components may be used in any combination, also alongside the default SilverStripe authentication scheme.

## Requirements

 * PHP 5.3.3+ with extensions: ldap, openssl, dom, and mcrypt
 * SilverStripe 3.1+
 * Active Directory on Windows Server 2008 R2
 * Active Directory Federation Services FS 2.0 (ADFS)

While this module was only tested with Windows Server 2008 R2 and ADFS 2.0, it should also work with newer versions of Windows Server and ADFS.

We have not tested this module against non-Microsoft products such as OpenLDAP.
 
## Example installation steps

In this section we'll step through a possible list of set-up tasks for this module. Your specific configuration will vary depending on your requirements.

 1. Update ADFS on your Windows Server
 1. Install *silverstripe-activedirectory* module
 1. Configure SilverStripe as SAML Service Provider
 1. Configure ADFS as SAML Identity Provider
 1. Configure SilverStripe Authenticators
 1. Set LDAP synchronisation up
 1. Configuring for browsers

### 1. Update ADFS on your Windows Server

We assume you have installed Active Directory on Windows Server 2008 R2.

To be able to use the SAML Single Sign On functionality you need to have ADFS 2.0.
In some cases ADFS 1.0 is installed, but you can upgrade for free with [an update from Microsoft](http://www.microsoft.com/en-us/download/details.aspx?id=10909).

[Installing Active Directory Federation Services (ADFS) 2.0](http://pipe2text.com/?page_id=285) information is available.

If you're exposing the SAML endpoint over HTTPS, you also need to make sure that that there is a SSL certificate that matches the web endpoint.

The client browser will use this endpoint for SSO purposes e.g: https://domain.com/adfs/ls/

### 2. Install *silverstripe-activedirectory* module

Pull the module into your SilverStripe project. You can use composer:

	composer require "silverstripe/activedirectory:*"

### 3. Configure SilverStripe as SAML Service Provider

Follow the [SAML Service Provider (SP) Setup](docs/en/saml_setup.md) for setting up the SilverStripe side of SAML.

### 4. Configure ADFS as SAML Identity Provider

Follow the [ADFS 2.0 setup and configuration](docs/en/adfs_setup.md) for setting up the ADFS side of SAML.

### 5. Configure SilverStripe Authenticators

We have now established the bi-directional trust between the IdP and SP, and can now configure SilverStripe authentication.

To be able to use the SAML or the LDAP authenticator you will need to set them up in the `mysite/config.php`.

You can choose which authenticators you would like to display on the login form.

	// Show the SAML Login button on login form
	Authenticator::register_authenticator('SAMLAuthenticator');
	// Show the LDAP Login form  
	Authenticator::register_authenticator('LDAPAuthenticator');

You can unregister the default authenticator by adding this line

	Authenticator::unregister('MemberAuthenticator');

But you shouldn't do that before you have mapped an LDAP group to the SilverStripe `Administrator`
Security Group, since no user would have access to the SilverStripe Security admin.

#### Enabling the SAML Auto login

If you register the SAMLAuthenticator as the default authenticator, it will automatically send users
to the ADFS login server when they are required to login.

	Authenticator::set_default_authenticator('SAMLAuthenticator');
	
To bypass this and show the login form with all the configured Authenticators, go to this URL

	/Security/login?showloginform=1
	
For more information see the [SAMLSecurityExtension.php](code/authenticators/SAMLSecurityExtension.php). 

### 6. Set LDAP synchronisation up

If you need to perform authorisation based on AD groups, or need additional fields synchronised other than already provided by ADFS claim rules, follow the [LDAP Setup](docs/en/ldap_setup.md) to configure LDAP synchronisation.

### 7. Configuring for browsers

Read [Configuring for browsers](docs/en/saml_browsers.md) to find out about browser peculiarities regarding SAML single sign-on.

## Administering synchronised users and groups

Documentation on how to use the module to import users and map them to SilverStripe groups, etc.

[Usage docs](docs/en/usage.md)

## Debugging

There are certain parts of his module that have debugging messages logged. You can configure logging to receive
these via email, for example. In your `mysite/_config.php`:

	SS_Log::add_writer(new SS_LogEmailWriter('my@email.com'), SS_Log::DEBUG, '<=');