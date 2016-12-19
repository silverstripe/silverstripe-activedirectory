# Troubleshooting

This guide contains a list of solutions to problems we have encountered in practice when integrating this module. This is not an exhaustive list, but it may provide assistance in case of some common issues.

## Table of contents

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Unexpected users when synchronising LDAP](#unexpected-users-when-synchronising-ldap)
- [No users showing up when synchronising LDAP](#no-users-showing-up-when-synchronising-ldap)
- [AD fields are not synchronised into SilverStripe](#ad-fields-are-not-synchronised-into-silverstripe)
- [Problem finding names for field mappings](#problem-finding-names-for-field-mappings)
- ["Invalid issuer" error in SilverStripe](#invalid-issuer-error-in-silverstripe)
- [Updating ADFS from 1.0 to 2.0](#updating-adfs-from-10-to-20)
- [ADFS 3.0 and Chrome authentication](#adfs-30-and-chrome-authentication)
- [Intranet level security settings](#intranet-level-security-settings)
- [Stale AD groups in the CMS](#stale-ad-groups-in-the-cms)
- [1000 users limit in AD](#1000-users-limit-in-ad)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Unexpected users when synchronising LDAP

This module will synchronise fields as specified by the LDAP search query. If unexpected users pop the reason might be an LDAP search base that either wrong or not specific enough.

Note that the comma separated terms in the query base are conjunctive - adding another term will narrow it down. Make sure you specify all "OU", "CN" and "DC" terms when searching.

See "Configure LDAP search query" and "LDAP debugging" in the [developer guide](developer.md) for more details.

## No users showing up when synchronising LDAP

First, check the advice in "Unexpected users when synchronising LDAP" above.

If your queries seem correct, one additional reason why no users might be returned is if you use a Security Group in your search base. Search locations should only be tree nodes (e.g. containers, organisational units, domains) within your Active Directory.

See "Configure LDAP search query" section in the [developer guide](developer.md) for more details.

## AD fields are not synchronised into SilverStripe

By default only a few fields are synchronised from AD into the default SilverStripe Member fields:

```php
// AD => SilverStripe
'givenname' => 'FirstName',
'sn' => 'Surname',
'mail' => 'Email'
```

As a developer, to synchronise further fields, you need to provide an explicit mapping configuration via `LDAPMemberExtension::$ldap_field_mappings`.

See "Map AD attributes to Member fields" in [developer guide](developer.md) for more details.

## Problem finding names for field mappings

The human-friendly names shown to the AD administrator don’t necessarily match what LDAP (and SilverStripe) use.

You can work out LDAP attribute names by enabling "Advanced features" in your AD browser, and looking at the "Attribute Editor" tab in user's properties.

Alternatively, consult a [cheatsheet](http://www.kouti.com/tables/userattributes.htm).

## "Invalid issuer" error in SilverStripe

In your SAML configuration file for SilverStripe, `entityId` must match *exactly* to the correct URL (including the protocol).

The correct URL can be extracted from ADFS by checking the "Federation Service Properties".

## Updating ADFS from 1.0 to 2.0

To be able to use the SAML Single Sign On functionality you need to have ADFS 2.0 or greater.
In some cases ADFS 1.0 is installed, but you can upgrade for free with [an update from Microsoft](http://www.microsoft.com/en-us/download/details.aspx?id=10909).

[Installing Active Directory Federation Services (ADFS) 2.0](http://pipe2text.com/?page_id=285) information is available.

## ADFS 3.0 and Chrome authentication

ADFS 3.0, such as the kind found on Windows Server 2012 requires some extra configuration for Chrome to authenticate.

Run these commands on the ADFS server using Powershell:

	Set-ADFSProperties –ExtendedProtectionTokenCheck None
	Set-ADFSProperties -WIASupportedUserAgents @("MSIE 6.0", "MSIE 7.0", "MSIE 8.0", "MSIE 9.0", "MSIE 10.0", "Trident/7.0", "MSIPC", "Windows Rights Management Client", "Mozilla/5.0")

You will then need to restart the Active Directory service in Windows.

## Intranet level security settings

Internet Explorer running on your Windows machine must have the ADFS URL, e.g. https://adfs.mydomain.com set with "intranet" security settings, otherwise the browser will not attempt Windows authentication with the ADFS server, as the default is "internet" security settings.

More [detailed information](https://sysadminspot.com/windows/google-chrome-and-ntlm-auto-logon-using-windows-authentication/) can be found on this subject.

## Stale AD groups in the CMS

The list of groups here is cached, with a default lifetime of 8 hours. You can clear and regenerate the cache by adding `?flush=1` in the URL.

To change the cache lifetime, for example to make sure caches only last for an hour, a developer can set
this the your `mysite/_config.php`:

	\SilverStripe\Core\Cache::set_lifetime('ldap', 3600);

## 1000 users limit in AD

Active Directory has a default max LDAP page size limit of 1000. This means if you have over 1000 users some of them won't be imported.

Unfortunately due to a missing paging feature with the LDAP PHP extension, paging results is not currently possible. The workaround is to modify an LDAP policy `MaxPageSize` on your
Active Directory server using `ntdsutil.exe`:

	C:\Documents and Settings\username>ntdsutil.exe
	ntdsutil: ldap policies
	ldap policy: connections
	server connections: connect to server <yourservername>
	Binding to <yourservername> ...
	Connected to <yourservername> using credentials of locally logged on user.
	server connections: q
	ldap policy: show values

	Policy                          Current(New)

	MaxPoolThreads                  4
	MaxDatagramRecv                 1024
	MaxReceiveBuffer                10485760
	InitRecvTimeout                 120
	MaxConnections                  5000
	MaxConnIdleTime                 900
	MaxPageSize                     1000
	MaxQueryDuration                120
	MaxTempTableSize                10000
	MaxResultSetSize                262144
	MaxNotificationPerConn          5
	MaxValRange                     0

	ldap policy: set maxpagesize to 10000
	ldap policy: commit changes
	ldap policy: q
	ntdsutil: q

