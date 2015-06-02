# Configuring for browsers

Different browsers have varying requirements regarding how and when a SAML single sign-on is permitted, and when the Windows domain identity can be used for authentication.

## ADFS Windows authentication "auto-login"

### ADFS 3.0 and Chrome authentication

ADFS 3.0, such as the kind found on Windows Server 2012 requires some extra configuration for Chrome to authenticate.

Run these commands on the ADFS server using Powershell:

	Set-ADFSProperties –ExtendedProtectionTokenCheck None
	Set-ADFSProperties -WIASupportedUserAgents @("MSIE 6.0", "MSIE 7.0", "MSIE 8.0", "MSIE 9.0", "MSIE 10.0", "Trident/7.0", "MSIPC", "Windows Rights Management Client", "Mozilla/5.0")

You will then need to restart the Active Directory service in Windows.

### Intranet level security settings

Internet Explorer running on your Windows machine must have the ADFS URL, e.g. https://adfs.mydomain.com set with "intranet" security
settings, otherwise the browser will not attempt Windows authentication with the ADFS server, as the default is "internet" security settings.

More [detailed information](https://sysadminspot.com/windows/google-chrome-and-ntlm-auto-logon-using-windows-authentication/) can be found on this subject.