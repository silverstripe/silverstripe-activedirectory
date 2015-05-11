# AD FS 2.0 setup and configuration

## Install AD FS 2.0

This module is using AD FS 2.0 as an identity provider that issues SAML tokens for the identities it manages.
For that, a new relying party needs to be created. A relying party in AD FS 2.0 is a representation of an
application (a Web site or a Web service) and contains all the security-related information, such as
encryption certificate, claims transformation rules and so on.

## Configuration of the Identity Provider (IdP)

### Create a new relying party trust

![](img/create_relying_party.png)

and click "Start"

### Select Data Source

"Federation metadata address (host name or URL):" should be the SilverStripe SAML metadata endpoint, e.g:
"https://77dd4125.ngrok.com/saml/metadata".

![](img/add_metadata_from_endpoint.png)

### Specify Display Name

Here you can add any notes, for example who would be the technical contact for the SP.

![](img/add_notes.png)

### Choose Issuance Authorization Rules

## Setup claim rules

Claim rules decides what information is shared with the Service Provider. In this case the SP want to have
a couple of properties from the Active Directory.

Right click the relying party and choose "Edit Claim Rules".

![](img/add_claims_rule.png)

![](img/send_claims_using_a_custom_rule.png)

### Rule 1: Send LDAP Attributes

Click "Add Rule" and select "Send Claims Using a Custom Rule" from the dropdown.
Add the following rule:

	c:[Type == "http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname", Issuer == "AD AUTHORITY"] => issue(store = "Active Directory", types = ("http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress", "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname", "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname", "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier"), query = ";mail,givenName,sn,objectGuid;{0}", param = c.Value);
	
![](img/send_ldap_attributes.png)

### Rule 2: Send objectId as nameidentifier

Repeat the same "Add Rule" as done above and select "Send Claims Using a Custom Rule" from the dropdown to add this rule:

	c:[Type == "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/privatepersonalidentifier"] => issue(Type = "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/nameidentifier", Issuer = c.Issuer, OriginalIssuer = c.OriginalIssuer, Value = c.Value, ValueType = c.ValueType, Properties["http://schemas.xmlsoap.org/ws/2005/05/identity/claimproperties/format"] = "urn:oasis:names:tc:SAML:2.0:nameid-format:transient");

## Set the secure hash algorithm

You will also have to change the secure hash algorithm from SHA-256 to SHA-1

1. Right click the relying party and choose properties.
2. Choose the "Advanced" tab and select the "SHA-1" option in the dropdown and press OK.

![](img/1_set_encryption_to_sha1.png)

![](img/2_set_encryption_to_sha1.png)

## AD FS Windows authentication "auto-login"

### AD FS 3.0 and Chrome authentication

AD FS 3.0, such as the kind found on Windows Server 2012 requires some extra configuration for Chrome to authenticate.

Run these commands on the AD FS server using Powershell:

	Set-ADFSProperties –ExtendedProtectionTokenCheck None
	Set-ADFSProperties -WIASupportedUserAgents @("MSIE 6.0", "MSIE 7.0", "MSIE 8.0", "MSIE 9.0", "MSIE 10.0", "Trident/7.0", "MSIPC", "Windows Rights Management Client", "Mozilla/5.0")

You will then need to restart the Active Directory service in Windows.

### Intranet level security settings

Internet Explorer running on your Windows machine must have the AD FS URL, e.g. https://adfs.mydomain.com set with "intranet" security
settings, otherwise the browser will not attempt Windows authentication with the AD FS server, as the default is "internet" security settings.

More [detailed information](https://sysadminspot.com/windows/google-chrome-and-ntlm-auto-logon-using-windows-authentication/) can be found on this subject.
