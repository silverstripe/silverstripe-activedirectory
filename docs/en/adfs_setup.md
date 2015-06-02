# ADFS 2.0 setup and configuration

This guide explains how to configure ADFS for acting as an Identity Provider (IdP) for this module.

## Install ADFS 2.0

This module is using ADFS 2.0 as an identity provider that issues SAML tokens for the identities it manages.
For that, a new relying party needs to be created. A relying party in ADFS 2.0 is a representation of an
application (a Web site or a Web service) and contains all the security-related information, such as
encryption certificate, claims transformation rules and so on.

## Configuration of the Identity Provider (IdP)

As part of this we will establish the trust between this IdP and the SilverStripe's Service Provider. ADFS will download the public certificate we have generated in the "SAML Service Provider (SP) Setup" guide.

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

## Setup claim rules

Claim rules decide what information is shared with the Service Provider. Here, we make some Active Directory fields already available to the *silverstripe-activedirectory* module so that authentication can be performed. Other fields may be synchronised later by using the LDAP synchronisation feature of this module.

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
