# Developer guide

This guide will step you through configuring your SilverStripe project to function as a SAML 2.0 Service Provider (SP). It will also show you a typical way to synchronise user details and group memberships from LDAP.

As a SilverStripe developer after reading this guide, you should be able to correctly configure your site to integrate with the Identity Provider (IdP). You will also be able to authorise users based on their AD group memberships, and synchronise their personal details.

We assume ADFS 2.0 or greater is used as an IdP.

## Table of contents

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Install the module](#install-the-module)
- [Make x509 certificates available](#make-x509-certificates-available)
  - [SP certificate and key](#sp-certificate-and-key)
  - [IdP certificate](#idp-certificate)
- [YAML configuration](#yaml-configuration)
  - [Service Provider (SP)](#service-provider-sp)
  - [Identity Provider (IdP)](#identity-provider-idp)
- [Establish trust](#establish-trust)
- [Configure SilverStripe Authenticators](#configure-silverstripe-authenticators)
  - [Bypass auto login](#bypass-auto-login)
- [Test the connection](#test-the-connection)
- [Configure LDAP synchronisation](#configure-ldap-synchronisation)
  - [Connect with LDAP](#connect-with-ldap)
  - [Configure LDAP search query](#configure-ldap-search-query)
  - [Verify LDAP connectivity](#verify-ldap-connectivity)
  - [Put imported Member into a default group](#put-imported-member-into-a-default-group)
  - [Map AD attributes to Member fields](#map-ad-attributes-to-member-fields)
    - [Example](#example)
  - [Syncing AD users on a schedule](#syncing-ad-users-on-a-schedule)
  - [Syncing AD groups and users on a schedule](#syncing-ad-groups-and-users-on-a-schedule)
  - [Migrating existing users](#migrating-existing-users)
- [Debugging](#debugging)
  - [SAML debugging](#saml-debugging)
  - [Debugging LDAP from SilverStripe](#debugging-ldap-from-silverstripe)
  - [Debugging LDAP directly](#debugging-ldap-directly)
- [Advanced SAML configuration](#advanced-saml-configuration)
- [Advanced features](#advanced-features)
  - [Allowing users to update their AD password](#allowing-users-to-update-their-ad-password)
  - [Writing LDAP data from SilverStripe](#writing-ldap-data-from-silverstripe)
- [Resources](#resources)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Install the module

First step is to add this module into your SilverStripe project. You can use composer for this:

    composer require "silverstripe/activedirectory:*"

Commit the changes.

## Make x509 certificates available

SAML uses pre-shared certificates for establishing trust between the Service Provider (SP - here, SilverStripe) the Identity Provider (IdP - here, ADFS).

### SP certificate and key

You need to make the SP x509 certificate and private key available to the SilverStripe site to be able to sign SAML requests. The certificate's "Common Name" needs to match the site endpoint that the ADFS will be using.

For testing purposes, you can generate this yourself by using the `openssl` command:

    openssl req -x509 -nodes -newkey rsa:2048 -keyout saml.pem -out saml.crt -days 1826

Contact your system administrator if you are not sure how to install these.

### IdP certificate

You also need to make the certificate for your ADFS endpoint available to the SilverStripe site. Talk with your ADFS administrator to find out how to obtain this.

If you are managing ADFS yourself, consult the [ADFS administrator guide](adfs.md).

You may also be able to extract the certificate yourself from the IdP endpoint if it has already been configured: `https://<idp-domain>/FederationMetadata/2007-06/FederationMetadata.xml`.

## YAML configuration

Now we need to make the *silverstripe-activedirectory* module aware of where the certificates can be found.

Add the following configuration to `mysite/_config/saml.yml` (make sure to replace paths to the certificates and keys):

    ---
    Name: mysamlsettings
    After:
      - "#samlsettings"
    ---
    SilverStripe\ActiveDirectory\Services\SAMLConfiguration:
      strict: true
      debug: false
      SP:
        entityId: "https://<your-site-domain>"
        privateKey: "<path-to-silverstripe-private-key>.pem"
        x509cert: "<path-to-silverstripe-cert>.crt"
      IdP:
        entityId: "https://<idp-domain>/adfs/services/trust"
        x509cert: "<path-to-adfs-cert>.pem"
        singleSignOnService: "https://<idp-domain>/adfs/ls/"
      Security:
        signatureAlgorithm: "http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"

If you don't use absolute paths, the certificate paths will be relative to the `BASE_PATH` (your site web root).

All IdP and SP endpoints must use HTTPS scheme with SSL certificates matching the domain names used.

### A note on signature algorithm config

The signature algorithm must match the setting in the ADFS relying party trust
configuration. For ADFS it's possible to downgrade the default from SHA-256 to
 SHA-1, but this is not recommended. To do this, you can change YAML configuration:

    SilverStripe\ActiveDirectory\Services\SAMLConfiguration:
      Security:
        signatureAlgorithm: "http://www.w3.org/2000/09/xmldsig#rsa-sha1"

### Service Provider (SP)

 - `entityId`: This should be the base URL with https for the SP
 - `privateKey`: The private key used for signing SAML request
 - `x509cert`: The public key that the IdP is using for verifying a signed request

### Identity Provider (IdP)

 - `entityId`: Provided by the IdP, but for ADFS it's typically `https://<idp-domain>/adfs/services/trust`
 - `x509cert`: The token-signing certificate from ADFS (base 64 encoded)
 - `singleSignOnService`: The endpoint on ADFS for where to send the SAML login request

## Establish trust

At this stage the SilverStripe site trusts the ADFS, but the ADFS does not have any way to establish the identity of the SilverStripe site.

ADFS should now be configured to extract the SP certificate from SilverStripe's SP endpoint. Once this is completed, bi-directional trust has been established and the authentication should be possible.

*silverstripe-activedirectory* has some specific requirements on how ADFS is configured. If you are managing ADFS yourself, or you are assisting an ADFS administrator, consult the [ADFS administrator guide](adfs.md).

## Configure SilverStripe Authenticators

To be able to use the SAML or the LDAP authenticator you will need to set them up in the `mysite/_config.php`.

You can choose which authenticators you would like to display on the login form.

    // Show the SAML Login button on login form
    \SilverStripe\Security\Authenticator::register_authenticator(
        'SilverStripe\\ActiveDirectory\\Authenticators\\SAMLAuthenticator'
    );
    // Show the LDAP Login form
    \SilverStripe\Security\Authenticator::register_authenticator(
        'SilverStripe\\ActiveDirectory\\Authenticators\\LDAPAuthenticator'
    );

You can unregister the default authenticator by adding this line:

    \SilverStripe\Security\Authenticator::unregister('SilverStripe\\Security\\MemberAuthenticator');

To prevent locking yourself out, before you remove the "MemberAuthenticator" make sure you map at least one LDAP group to the SilverStripe `Administrator` Security Group. Consult [CMS usage docs](usage.md) for how to do it.

### Bypass auto login

If you register the SAMLAuthenticator as the default authenticator, it will automatically send users to the ADFS login server when they are required to login.

    \SilverStripe\Security\Authenticator::set_default_authenticator(
        'SilverStripe\\ActiveDirectory\\Authenticators\\SAMLAuthenticator'
    );

Should you need to access the login form with all the configured Authenticators, go to:

    /Security/login?showloginform=1

Note that if you have unregistered the `MemberAuthenticator`, and you wish to use that method during `showloginform=1`, you
will need to set a cookie so it can be used temporarily.

This will set a cookie to show `MemberAuthenticator` if `showloginform=1` is requested:

    \SilverStripe\Security\Authenticator::unregister('SilverStripe\\Security\\MemberAuthenticator');
    if(isset($_GET['showloginform'])) {
        \SilverStripe\Control\Cookie::set('showloginform', (bool)$_GET['showloginform'], 1);
    }
    if(\SilverStripe\Control\Cookie::get('showloginform')) {
        \SilverStripe\Security\Authenticator::register_authenticator('SilverStripe\\Security\\MemberAuthenticator');
    }

For more information see the [`SAMLSecurityExtension.php`](../../src/Authenticators/SAMLSecurityExtension.php).

## Test the connection

At this stage you should be able to authenticate. If you cannot, you should double check the claims rules and hashing algorithm used by ADFS. Consult [ADFS administrator guide](adfs.md) to assist the ADFS administrator.

You can also review the [troubleshooting](troubleshooting.md) guide if you are experiencing problems.

## Configure LDAP synchronisation

These are the reasons for configuring LDAP synchronisation:

* It allows you to authorise users based on their AD groups. *silverstripe-activedirectory* is able to automatically maintain Group memberships for its managed users based on the AD "memberOf" attribute.
* You can pull in additional personal details about your users that may not be available from the IdP directly - either because of claim rules, or inherent limitations such as binary data transfers.
* The data is only synchronised upon modification, so it helps to keep SAML payloads small.

### Connect with LDAP

Example configuration for `mysite/_config/ldap.yml`:

    SilverStripe\ActiveDirectory\Model\LDAPGateway:
      options:
        'host': 'ad.mydomain.local'
        'username': 'myusername'
        'password': 'mypassword'
        'accountDomainName': 'mydomain.local'
        'baseDn': 'DC=mydomain,DC=local'
        'networkTimeout': 10
        'useSsl': 'TRUE'

The `baseDn` option defines the initial scope of the directory where the connector can perform queries. This should be set to the root base DN, e.g. DC=mydomain,DC=local

The `useSsl` option enables encrypted transport for LDAP communication. This should be mandatory for production systems to prevent eavesdropping. A certificate trusted by the webserver must be installed on the AD server. StartTLS can alternatively be used (`useStartTls` option).

For more information about available LDAP options, please [see the Zend\Ldap documentation](http://framework.zend.com/manual/2.2/en/modules/zend.ldap.introduction.html) and [API overview documentation](http://framework.zend.com/manual/2.2/en/modules/zend.ldap.api.html).

### Configure LDAP search query

You can then set specific locations to search your directory. Note that these locations must be within the `baseDn` you have specified above:

    SilverStripe\ActiveDirectory\Services\LDAPService:
      users_search_locations:
        - 'CN=Users,DC=mydomain,DC=local'
        - 'CN=Others,DC=mydomain,DC=local'
      groups_search_locations:
        - 'CN=Somewhere,DC=mydomain,DC=local'

Note that these search locations should only be tree nodes (e.g. containers, organisational units, domains) within your Active Directory.
Specifying groups will not work. [More information](http://stackoverflow.com/questions/9945518/can-ldap-matching-rule-in-chain-return-subtree-search-results-with-attributes) is available on the distinction between a node and a group.

If you are experiencing problems with getting the right nodes, run the search query directly via LDAP and see what is returned. For that you can either use Windows' `ldp.exe` tool, or Unix/Linux equivalent `ldapsearch`.

See "LDAP debugging" section below for more information.

### Verify LDAP connectivity

You can visit a controller called `/LDAPDebug` to check that the connection is working. This will output a page listing the connection options used, as well as all AD groups that can be found.

### Put imported Member into a default group

You can configure the module so everyone imported goes into a default group. The group must already exist before
you can use this setting. The value of this setting should be the "Code" field from the Group.

    SilverStripe\ActiveDirectory\Services\LDAPService:
      default_group: "content-authors"

### Map AD attributes to Member fields

`SilverStripe\Security\Member.ldap_field_mappings` defines the AD attributes that should be mapped to `Member` fields.
By default, it will map the AD first name, surname and email to the built-in FirstName, Surname,
and Email Member fields.

You can map AD attributes to custom fields by specifying configuration in your `mysite/_config/ldap.yml`. The three
different AD fields types that can be mapped are: textual, array and thumbnail photo.

    SilverStripe\Security\Member:
      ldap_field_mappings:
        'description': 'Description'
        'othertelephone': 'OtherTelephone'
        'thumbnailphoto': 'Photo'

A couple of things to note:

 * The AD attributes names must be in lowercase
 * You must provide a receiver on the `Member` on your own (a field, or a setter - see example below).

There is a special case for the `thumbnailphoto` attribute which can contain a photo of a user in AD. This comes
through from AD in binary format. If you have a `has_one` relation to an `Image` on the Member, you can map that field
to this attribute, and it will put the file into place and assign the image to that field.

By default, thumbnails are saved into `assets/Uploads`, but you can specify the location
(relative to /assets) by setting the following configuration:

    SilverStripe\Security\Member:
      ldap_thumbnail_path: 'some-path'

The image files will be saved in this format: `thumbnailphoto-{sAMAccountName}.jpg`.

#### Example

Here is an extension that will handle different types of field mappings defined in the `mysite/_config/ldap.yml`
mentioned above. You will still need to apply that extension to `Member` to get it to work.

```php
<?php

use SilverStripe\ORM\DataExtension;

class MyMemberExtension extends DataExtension
{
    private static $db = [
        // 'description' is a regular textual field and is written automatically.
        'Description' => 'Varchar(50)',
        // ...
    ];
    private static $has_one = [
        // 'thumbnailphoto' writes to has_one Image automatically.
        'Photo' => 'SilverStripe\\Assets\\Image'
    ];
    /**
     * 'othertelephone' is an array, needs manual processing.
     */
    public function setOtherTelephone($array) {
        $serialised = implode(',', $array);
        // ...
    }
}
```

### Syncing AD users on a schedule

You can schedule a job to run, then have it re-schedule itself so it runs again in the future, but some configuration needs to be set to have it work.

If you want, you can set the behaviour of the sync to be destructive, which means any previously imported users who no
longer exist in the directory get deleted:

    SilverStripe\ActiveDirectory\Tasks\LDAPMemberSyncTask:
      destructive: true

To configure when the job should re-run itself, set the `SilverStripe\ActiveDirectory\Jobs\LDAPMemberSyncJob.regenerate_time` configuration.
In this example, this configures the job to run every 8 hours:

    SilverStripe\ActiveDirectory\Jobs\LDAPMemberSyncJob:
      regenerate_time: 28800

Once the job runs, it will enqueue itself again, so it's effectively run on a schedule. Keep in mind that you'll need to have `queuedjobs` setup on a cron so that it can automatically run those queued jobs.
See the [module docs](https://github.com/silverstripe-australia/silverstripe-queuedjobs) on how to configure that.

If you don't want to run a queued job, you can set a cronjob yourself by calling:

    /usr/bin/php framework/cli-script.php dev/tasks/LDAPMemberSyncTask

### Syncing AD groups and users on a schedule

Similarly to syncing AD users, you can also schedule a full group and user sync. Group mappings will be added automatically, resulting in Members being added to relevant Groups.

As with the user sync, you can separately set the group sync to be destructive:

    SilverStripe\ActiveDirectory\Tasks\LDAPGroupSyncTask:
      destructive: true

And here is how you make the job reschedule itself after completion:

    SilverStripe\ActiveDirectory\Jobs\LDAPAllSyncJob:
      regenerate_time: 28800

If you don't want to run a queued job, you can set a cronjob yourself by calling the two sync tasks (order is important, otherwise your group memberships might not get updated):

    /usr/bin/php framework/cli-script.php dev/tasks/LDAPGroupSyncTask
    /usr/bin/php framework/cli-script.php dev/tasks/LDAPMemberSyncTask

### Migrating existing users

If you have existing Member records on your site that have matching email addresses to users in the directory,
you can migrate those by running the task `LDAPMigrateExistingMembersTask`. For example, visting
`http://mysite.com/dev/tasks/LDAPMigrateExistingMembersTask` will run the migration.

This essentially just updates those existing records with the matching directory user's `GUID` so they will be synced from now on.

## Debugging

There are certain parts of his module that have debugging messages logged. You can configure logging to receive these via email, for example. For more information on this topic see [Logging and Error Handling](https://docs.silverstripe.org/en/4/developer_guides/debugging/error_handling/) in the developer documentation.

### SAML debugging

To enable some very light weight debugging from the 3rd party library set the `debug` to true

    SilverStripe\ActiveDirectory\Services\SAMLConfiguration:
      debug: true

In general it can be tricky to debug what is failing during the setup phase. The SAML protocol error
message as quite hard to decipher.

In most cases it's configuration issues that can debugged by using the ADFS Event log, see the
[Diagnostics in ADFS 2.0](http://blogs.msdn.com/b/card/archive/2010/01/21/diagnostics-in-ad-fs-2-0.aspx)
for more information.

Also ensure that all protocols are matching. SAML is very sensitive to differences in http and https in URIs.

### Debugging LDAP from SilverStripe

For debugging what information SilverStripe is getting from LDAP, you can visit the `<site-root>/LDAPDebug` from your browser. Assuming you are an ADMIN, this will give you a breakdown of visible information.

To see debug information on the sync tasks, run them directly from your browser. The tasks are at `<site-root>/dev/tasks/LDAPGroupSyncTask` and `dev/tasks/LDAPMemberSyncTask`.

### Debugging LDAP directly

LDAP is a plain-text protocol for interacting with user directories. You can debug LDAP responses by querying directly. For that you can use Windows' `ldp.exe` tool, or Unix/Linux equivalent `ldapsearch`.

Here is an example of `ldapsearch` usage. You will need to bind to the directory using an administrator account (specified via `-D`). The base of your query is specified via `-b`, and the search query follows.

```bash
ldapsearch \
    -W \
    -H ldaps://<ldap-url>:<ldap-port> \
    -D "CN=<administrative-user>,DC=yourldap,DC=co,DC=nz" \
    -b "DC=yourldap,DC=co,DC=nz" \
    "(name=*)"
```

## Advanced SAML configuration

It is possible to customize all the settings provided by the 3rd party SAML code.

This can be done by registering your own `SilverStripe\ActiveDirectory\Services\SAMLConfiguration` object via `mysite/_config/saml.yml`:

Example:

    ---
    Name: samlconfig
    After:
      - "#samlsettings"
    ---
    SilverStripe\Core\Injector\Injector:
      SAMLConfService: YourVendor\YourModule\MySAMLConfiguration

and then in your namespaced `MySAMLConfiguration.php`:

    <?php

    namespace YourVendor\YourModule;

    use SilverStripe\Core\Object;

    class MySAMLConfiguration extends Object
    {
        public function asArray() {
            return [
                // add settings here;
            ];
        }
    }

See the [advanced\_settings\_example.php](https://github.com/onelogin/php-saml/blob/master/advanced_settings_example.php)
for the advanced settings.

## Advanced features

### Allowing email login on LDAP login form instead of username

`LDAPAuthenticator` expects a username to log in, due to authentication with LDAP traditionally
using username instead of email. You can additionally allow people to authenticate with their email.

Example configuration in `mysite/_config/ldap.yml`:

```yaml
SilverStripe\ActiveDirectory\Authenticators\LDAPAuthenticator:
  allow_email_login: 'yes'
```

Note that your LDAP users logging in must have the `mail` attribute set, otherwise this will not work.

### Falling back authentication on LDAP login form

You can allow users who have not been migrated to LDAP to authenticate via the default `SilverStripe\Security\MemberAuthenticator`.
This is different to registering multiple authenticators, in that the fallback works on the one login form.

Example configuration in `mysite/_config/ldap.yml`:

```yaml
SilverStripe\ActiveDirectory\Authenticators\LDAPAuthenticator:
  fallback_authenticator: 'yes'
```

The fallback authenticator will be used in the following conditions:

 * User logs in using their email address, but does not have a username
 * The user logs in with a password that does not match what is set in LDAP

### Allowing users to update their AD password

If the LDAP bind user that is configured under 'Connect with LDAP' section has permission to write attributes to the AD, it's possible to allow users to update their password via the internet site.

Word of caution, you will potentially open a security hole by exposing an AD user that can write passwords. Normally you would only bind to LDAP via a read-only user. Windows AD stores passwords in a hashed format that is very hard to brute-force. A user with write access can take over an accounts, create objects, delete and have access to all systems that authenticate with AD.

If you still need this feature, we recommend that you use a combination of encryption, scheduled password rotation and limit permission for the bind user to minimum required permissions.

This feature only works if you have the `LDAPAuthenticator` enabled (see "Configure SilverStripe Authenticators" section).

This feature has only been tested on Microsoft AD compatible servers.

Example configuration in `mysite/_config/ldap.yml`:

```yaml
SilverStripe\ActiveDirectory\Services\LDAPService:
  allow_password_change: true
```

### Writing LDAP data from SilverStripe

A feature is available that allows data to be written back to LDAP based on the state of `SilverStripe\Security\Member` object fields.
Additionally, you can also create new users in LDAP from your local records.

Before this can be used, the credentials connecting to LDAP need to have write permissions so LDAP attributes can
be written to.

To turn on the feature, here is some example configuration in `mysite/_config/ldap.yml`:

```yaml
SilverStripe\Security\Member:
  update_ldap_from_local: true
  create_users_in_ldap: true
SilverStripe\ActiveDirectory\Services\LDAPService:
  new_users_dn: CN=Users,DC=mydomain,DC=com
```

The `new_users_dn` is the DN (Distinguished Name) of the location in the LDAP structure where new users will be created.

Now when you create a new user using the Security section in `/admin`, the user will be created in LDAP. Take note
that the "Username" field must be filled in, otherwise it will not be created, due to LDAP users requiring a username.

You can also programatically create a user. For example:

    $member = new \SilverStripe\Security\Member();
    $member->FirstName = 'Joe';
    $member->Username = 'jbloggs';
    $member->write();

If you enable `update_ldap_from_local` saving a user in the Security section of the CMS or calling `write()` on
a Member object will push up the mapped fields to LDAP, assuming that Member record has a `GUID` field.

See "Map AD attributes to Member fields" section above for more information on mapping fields.

## Resources

 - [ADFS Deep-Dive: Onboarding Applications](http://blogs.technet.com/b/askpfeplat/archive/2015/03/02/adfs-deep-dive-onboarding-applications.aspx)
