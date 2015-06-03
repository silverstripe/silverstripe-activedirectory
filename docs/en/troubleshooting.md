# Troubleshooting

This guide contains a list of solutions to problems we have encountered in practice when integrating this module. This is not an exhaustive list, but it may provide assistance in case of some common issues.

### "Invalid issuer" error in SilverStripe

In your SAML configuration file for SilverStripe, `entityId` must match *exactly* to the correct URL (including the protocol).

The correct URL can be extracted from ADFS by checking the "Federation Service Properties".

### Updating ADFS to 2.0

To be able to use the SAML Single Sign On functionality you need to have ADFS 2.0. In some cases ADFS 1.0 is installed, but you can upgrade for free with [an update from Microsoft](http://www.microsoft.com/en-us/download/details.aspx?id=10909).

[Installing Active Directory Federation Services (ADFS) 2.0](http://pipe2text.com/?page_id=285) information is available.

### ADFS 3.0 and Chrome authentication

ADFS 3.0, such as the kind found on Windows Server 2012 requires some extra configuration for Chrome to authenticate.

Run these commands on the ADFS server using Powershell:

	Set-ADFSProperties –ExtendedProtectionTokenCheck None
	Set-ADFSProperties -WIASupportedUserAgents @("MSIE 6.0", "MSIE 7.0", "MSIE 8.0", "MSIE 9.0", "MSIE 10.0", "Trident/7.0", "MSIPC", "Windows Rights Management Client", "Mozilla/5.0")

You will then need to restart the Active Directory service in Windows.

### Intranet level security settings

Internet Explorer running on your Windows machine must have the ADFS URL, e.g. https://adfs.mydomain.com set with "intranet" security settings, otherwise the browser will not attempt Windows authentication with the ADFS server, as the default is "internet" security settings.

More [detailed information](https://sysadminspot.com/windows/google-chrome-and-ntlm-auto-logon-using-windows-authentication/) can be found on this subject.

### Stale AD groups in the CMS

The list of groups here is cached, with a default lifetime of 8 hours. You can clear and regenerate the cache by adding `?flush=1` in the URL.

To change the cache lifetime, for example to make sure caches only last for an hour, a developer can set 
this the your `mysite/_config.php`:

	SS_Cache::set_lifetime('ldap', 3600);
	
### 1000 users limit in AD

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

