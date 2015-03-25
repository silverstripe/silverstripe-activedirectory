# SAML Service Provider (SP) Setup

## SSL Certificates

You need to have an SSL certificate and private key for signing SAML requests.
These should not be checked into the source code and should be placed outside of the
web document root for security reasons.

One way to generate self signed test certificates is by using the `openssl` command

	openssl req -x509 -nodes -newkey rsa:2048 -keyout private.pem -out public.crt -days 1826

You also need to have access to the token-signing certificate from AD FS.
You can get that by parsing `https://mydomain.com/FederationMetadata/2007-06/FederationMetadata.xml`
or by exporting the certificate manually using AD FS console on Windows.

## Example YAML configuration

Example configuration for `mysite/_config/saml.yml`

	---
	Name: mysamlsettings
	After:
	  - "#samlsettings"
	---
	SAMLConfiguration:
	  strict: true
	  debug: false
	  SP:
	    entityId: "https://25b5994c.ngrok.com"
	    privateKey: "../certs/saml.pem"
	    x509cert: "../certs/saml.crt"
	  IdP:
	    entityId: "https://mydomain.com/adfs/services/trust"
	    x509cert: "mysite/certs/adfs_certificate.pem"
	    singleSignOnService: "https://mydomain.com/adfs/ls/"

SAML configuration can be quite complicated and this example relies on that the IdP have been setup
according to [AD FS 2.0 setup and configuration](docs/en/adfs_setup.md).

### Service Provider (SP)

 - `entityId`: This should be the base URL with https for the SP
 - `privateKey`: The private key used for signing SAML request
 - `x509cert`: The public key that the IdP is using for verifying a signed request

### Identity Provider (IdP)

The IdP settings and public certificate should be provided by who set this up in the AD FS 2.0 server.

 - `entityId`: Provided by the IdP, but for AD FS it's typically "https://domain.com/adfs/services/trust"
 - `x509cert`: The token-signing certificate from AD FS in PEM format (base 64 encoded)
 - `singleSignOnService`: The endpoint on AD FS for where to send the SAML login request
 
### Verifying that it works

@todo
 
## Debugging

To enable some very light weight debugging from the 3rd party library set the `debug` to true

	SAMLConfiguration:
	  debug: false

In general it can be tricky to debug what is failing during the setup phase. The SAML protocol error
message as quite hard to decipher.

In most cases it's configuration issues that can debugged by using the AD FS 2.0 Event log, see the 
[Diagnostics in AD FS 2.0](http://blogs.msdn.com/b/card/archive/2010/01/21/diagnostics-in-ad-fs-2-0.aspx)
for more information.

Also ensure that all protocols are matching. SAML is very sensitive to differences in http and https in URIs.

### Advanced configuration

It is possible to customize all the settings provided by the 3rd party SAML code. 
 
This can be done by registering your own `SAMLConfiguration` object via mysite/_config/saml.yml:
 
Example:

	---
	Name: samlconfig
	After:
	  - "#samlsettings"
	---
	Injector:
	  SAMLConfService: MySAMLConfiguration

and the MySAMLConfiguration.php:

	<?php
	class MySAMLConfiguration extends Object {
		public function asArray() {
			return array(
				// add settings here;
			);
		}
	}

See the [advanced_settings_example.php](https://github.com/onelogin/php-saml/blob/master/advanced_settings_example.php)
for the advanced settings.

## Resources

 - [ADFS Deep-Dive: Onboarding Applications](http://blogs.technet.com/b/askpfeplat/archive/2015/03/02/adfs-deep-dive-onboarding-applications.aspx)
