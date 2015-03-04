# LDAP Setup

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

You can then set specific locations to search your directory. Note that these locations must be within the `baseDn`
you have specified above:

	LDAPService:
	  search_locations:
	    - 'CN=Users,DC=mydomain,DC=local'
	    - 'CN=Others,DC=mydomain,DC=local'

If you don't set `LDAPService.search_locations` configuration, it defaults to searching within the `baseDn` provided in `LDAPGateway.options`.
Note that you should only pick search locations that reside within the `baseDn` set in `LDAPGateway.options`, otherwise things will not work correctly.

TIP: On Windows, there is a utility called `ldp.exe` which is useful for exploring your directory to find which DN to use.

There are several more LDAP options you can configure. For more information, please [see the Zend\Ldap documentation](http://framework.zend.com/manual/2.2/en/modules/zend.ldap.introduction.html) and [API overview documentation](http://framework.zend.com/manual/2.2/en/modules/zend.ldap.api.html).

## Verifying that it works

You can visit a controller called `/LDAPDebugController` to check that the connection is working. This will output
a page listing the connection options used, as well as all AD groups that can be found.

## Mapping AD attributes to Member fields

`Member.ldap_field_mappings` defines the AD attributes that should be mapped to `Member` fields.
By default, it will map the AD first name, surname and email to the Member record fields.

You can map AD attributes to custom fields by specifying configuration in your `mysite/_config/ldap.yml`:

	Member:
	  ldap_field_mappings:
	    'someadattribute': 'MyMemberField'
	    'anotherattribute': 'AnotherMemberField'

NOTE: Attributes in the configuration should be in lowercase!

There is a special case for the `thumbnailphoto` attribute which can contain a photo of a user in AD.
This comes through from AD in binary format.
If you have a `has_one` relation to an `Image` on the Member, you can map that field to this
attribute, and it will put the file into place and assign the image to that field.

By default, thumbnails are saved into `assets/Uploads`, but you can specify the location
(relative to /assets) by setting the following configuration:

	Member:
	  ldap_thumbnail_path: 'some-path'

The image files will be saved in this format: `thumbnailphoto-{sAMAccountName}.jpg`.
