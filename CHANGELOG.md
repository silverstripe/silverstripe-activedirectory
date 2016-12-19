# Changelog

## 4.0.0 (Unreleased)

New module requirements:

 - Minimum SilverStripe framework version: 4.0 or above
 - Minimum PHP version: 5.5 or above
 - Support for PHP 7

## 3.0.0 (Unreleased)

Adding features to allow writing Member data back to LDAP.
See [Writing LDAP data from SilverStripe](docs/en/developer.md#writing-ldap-data-from-silverstripe).

 - IsImportedFromLDAP field removed from Group and Member. To determine if
   either of these are imported from LDAP, check if the GUID field is not NULL.

## 2.0.0

LDAP functionality that modifies user data (i.e. password reset) will require
binding credentials with LDAP write access.

 - SAML defaults to the SHA-256 hash signature hash
 - An issue was fixed where absolute paths couldn't be used for certificates and
   keys in YAML configuration.
 - Better LDAP group syncing that isn't as likely to crash and leave the database
   in an unknown state.
 - Removes LDAP group mappings when a security group is deleted
 - Minor UI changes for the login form
 - Moved the AD fields in the CMS to a separate tab and made them read-only.
 - Member "last synced" time is now showing the correct date and time.
 - Adding a script to rotate LDAP binding credentials for system administrators
 - Samba has been confirmed to work
 - Automatic syncing of LDAP groups and users via the `LDAPAllSyncJob` build task
 - Users can now reset their AD password via the "I've lost my password" on the
   login form.
 - Updated documentation

The source code is now following the SilverStripe supported modules standard.

## 1.0.0

Initial release.

# Migration

## 2.0.0

This module is now using SHA-256 hashing algorithm for the SAML integration.
SHA-1 is no longer recommended.

If you are upgrading from an earlier version you will need to change the "secure
hash algorithm" setting in ADFS from `SHA-1` to `SHA-256`, see
[Set the secure hash algorithm](docs/en/adfs.md#set-the-secure-hash-algorithm).

If you can't change the ADFS setting, you will need to downgrade to SHA-1
in YAML, i.e `mysite/_config/saml.yml`:

```
SAMLConfiguration:
 Security:
   signatureAlgorithm: "http://www.w3.org/2000/09/xmldsig#rsa-sha1"
```
