# LDAP Setup

This guide will step you through configuring synchronisation between LDAP and SilverStripe *Members* and *Groups*.

## Configuring the connection

Example configuration for `mysite/_config/ldap.yml`:

	LDAPGateway:
	  options:
	    'host': 'ad.mydomain.local'
	    'username': 'myusername'
	    'password': 'mypassword'
	    'accountDomainName': 'mydomain.local'
	    'baseDn': 'DC=mydomain,DC=local'
	    'networkTimeout': 10

The `baseDn` option defines the initial scope of the directory where the connector can perform queries.
This should be set to the root base DN, e.g. DC=mydomain,DC=local

You can then set specific locations to search your directory. Note that these locations must be within the `baseDn`
you have specified above:

	LDAPService:
	  users_search_locations:
	    - 'CN=Users,DC=mydomain,DC=local'
	    - 'CN=Others,DC=mydomain,DC=local'
	  groups_search_locations:
	    - 'CN=Somewhere,DC=mydomain,DC=local'

Note that these search locations should only be tree nodes (e.g. containers, organisational units, domains) within your Active Directory.
Specifying groups will not work. [More information](http://stackoverflow.com/questions/9945518/can-ldap-matching-rule-in-chain-return-subtree-search-results-with-attributes) is available on the distinction between a node and a group.

TIP: On Windows, there is a utility called `ldp.exe` which is useful for exploring your directory to find which DN to use.

There are several more LDAP options you can configure. For more information, please [see the Zend\Ldap documentation](http://framework.zend.com/manual/2.2/en/modules/zend.ldap.introduction.html) and [API overview documentation](http://framework.zend.com/manual/2.2/en/modules/zend.ldap.api.html).

## Verifying that it works

You can visit a controller called `/LDAPDebugController` to check that the connection is working. This will output
a page listing the connection options used, as well as all AD groups that can be found.

## Putting imported Member into a default group

You can configure the module so everyone imported goes into a default group. The group must already exist before
you can use this setting. The value of this setting should be the "Code" field from the Group.

	LDAPService:
	  default_group: "content-authors"

## Mapping AD attributes to Member fields

`Member.ldap_field_mappings` defines the AD attributes that should be mapped to `Member` fields.
By default, it will map the AD first name, surname and email to the built-in FirstName, Surname,
and Email Member fields.

You can map AD attributes to custom fields by specifying configuration in your `mysite/_config/ldap.yml`. The three
different AD fields types that can be mapped are: textual, array and thumbnail photo.

	Member:
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

	Member:
	  ldap_thumbnail_path: 'some-path'

The image files will be saved in this format: `thumbnailphoto-{sAMAccountName}.jpg`.

### Example

Here is an extension that will handle different types of field mappings defined in the `mysite/_config/ldap.yml`
mentioned above. You will still need to apply that extension to `Member` to get it to work.

```php
<?php
class MyMemberExtension extends DataExtension {
	private static $db = array(
		// 'description' is a regular textual field and is written automatically.
		'Description' => 'Varchar(50)',
		...
	);
	private static $has_one = array(
		// 'thumbnailphoto' writes to has_one Image automatically.
		'Photo' => 'Image'
	);
	/**
	 * 'othertelephone' is an array, needs manual processing.
	 */
	public function setOtherTelephone($array) {
		$serialised = implode(',', $array));
		...
	}
}
```
