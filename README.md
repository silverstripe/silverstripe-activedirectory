# SilverStripe Active Directory module

[![Build Status](https://secure.travis-ci.org/silverstripe/silverstripe-activedirectory.svg)](https://travis-ci.org/silverstripe/silverstripe-activedirectory)

## Introduction

This SilverStripe module provides Active Directory integration. It comes with three major components:

* Single sign-on authentication with SAML 2.0
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

## Overview

[Security Assertion Markup Language (SAML)](http://en.wikipedia.org/wiki/Security_Assertion_Markup_Language) is an XML-based, open-standard data format for exchanging authentication and authorization data between parties. The single most important requirement that SAML addresses is web browser single sign-on (SSO).

With this module, SilverStripe site is able to act as a SAML 2.0 Service Provider (SP) entity, and thus allows users to perform a single sign-on against a centralised user directory (an Identity Provider - IdP).

The intended counterparty for this module is the [Active Directory Federation Services (ADFS)](http://en.wikipedia.org/wiki/Active_Directory_Federation_Services). ADFS is a software component developed by Microsoft that can be installed on Windows Server operating systems to provide users with single sign-on access to systems and applications located across organizational boundaries.

ADFS uses a claims-based access control authorization model to maintain application security and implement federated identity. We rely on this mechanism for authentication, and for automated synchronisation of some basic personal details into SilverStripe.

To synchronise further personal details, LDAP synchronisation feature can be used, also included in this module. This allows arbitrary fields to be synchronised - including binary fields such as photos. If relevant mappings have been configured in the CMS the module will also automatically maintain SilverStripe group memberships, which opens the way for an AD-centric authorisation.

If SAML authentication cannot be used, this module also provides an LDAP authenticator as an alternative.

## In-depth guides

* [Developer guide](docs/en/developer.md) - configure your SilverStripe site
* [ADFS administrator guide](docs/en/adfs.md) - prepare the Identity Provider
* [CMS usage guide](docs/en/usage.md) - manage LDAP group mappings
* [Troubleshooting](docs/en/troubleshooting.md) - common problems