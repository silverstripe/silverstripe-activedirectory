# Azure AD administrator guide

This guide and code changes are backported from SS4 SAML module:
[see Github Pull Request](https://github.com/silverstripe/silverstripe-saml/pull/16/files)


This guide will step you through configuring a new SAML integration in Azure AD, such that Azure AD can act as an Identity Provider (IdP) for a SilverStripe site.

## Table of contents

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->


- [Overview](#overview)
- [Creating a new Enterprise Application](#creating-a-new-enterprise-application)
- [FAQs](#faqs)
  - [Why do we require using `objectId` instead of `userprincipalname`?](#why-do-we-require-using-objectid-instead-of-userprincipalname)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Overview

This is not an exhaustive guide, and it only covers Azure AD. Other SAML IdPs should have similar configuration, and the module should be able to work with them provided they can give the same guarantees about the attributes being passed through in the SAML assertion.

As an implementor of the IdP, you need to ensure the following have been set up:

* A bi-directional trust between SP and IdP (via metadata files)
* Ensuring that `user.objectId` is used as the User Identifier (aka SAML Name ID) rather than `user.userprincipalname`

## Creating a new Enterprise Application

1. Log in as an Azure portal administrator.
2. In the Azure portal, go to the ['Azure Active Directory' service](https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/Overview)
3. Click on 'Enterprise Applications', then 'New application'
4. Select 'Non-gallery application' and enter a descriptive name (e.g. 'Intranet'), then click Add
5. Once the application is created, select 'Single sign-on' from the left-menu, then select 'SAML'
6. Either upload the metadata file provided by the site developers, or enter the Entity ID and Reply URLs provided by the developers. These should always have the same base hostname, with the Reply URL including '/saml/acs' at the end
7. Under 'User Attributes', select `user.objectId` as the User Identifier, instead of the default (`user.userprincipalname`)
8. Provide the 'App Federation Metadata Url' and the base64 copy of the certificate under section 4 to the site developers

Once the site developers have deployed the certificates and login URLs, the configuration should be set up and ready to be tested. You will need to configure users or groups that have access to this application before Azure AD will pass them through to the website, you can do this from the 'Users and groups' sidebar menu.

## FAQs

### Why do we require using `objectId` instead of `userprincipalname`?

This is because `user.userprincipalname` (UPN) is not guaranteed to be unique *forever*, it is only guaranteed to be unique for the lifetime of a user's account. This is problematic in some situations. For example:

1. John Smith joins the organisation, and given a UPN of john.smith@contoso.com
2. John Smith is added to the list of users that can access the website, and is given special administrator privileges over the website
3. John then leaves the organisation and his account is deleted. The website is not aware that John has been deleted because SAML provides no mechanism for this, so the website still has an administrator account configured for John, but nobody can login to it right now.
4. A different John Smith starts at the organisation in a different role (e.g. not an administrator). They are given access to the website, and when they login they see they have administrative privileges.

This is because the UPN is only unique at any given time, not forever. To ensure that we don't run into security issues with the above, we require that Azure AD integrations use the `objectId` of the user instead. This means that the second John Smith in the above example will have a new account created for him, as his `objectId` will not match the `objectId` of the former John Smith.
