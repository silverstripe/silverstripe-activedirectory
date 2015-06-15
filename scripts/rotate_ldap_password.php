#!/usr/bin/env php
<?php
error_reporting(E_ALL | E_STRICT);

/**
 * Rotate a configured LDAP account's password in an _ss_environment.php file.
 * This changes the password of that account to a randomly generated one, then sets the
 * password back into the same file so it's effective immediately.
 *
 * Requirements:
 * Microsoft Active Directory or Samba directory.
 * PHP CLI with ldap, and iconv extensions.
 *
 * Installation:
 * Copy this script into /usr/local/bin/rotate_ldap_password
 *
 * Command synax:
 * rotate_ldap_password <env-file-path> <ldap-hostname-const> <ldap-username-const> <ldap-password-const>
 *
 * Example:
 * Assuming you have this configuration in your /sites/_ss_environment.php file:
 * <code>
 * define('LDAP_HOSTNAME', 'ldap://myldap.hostname:636');
 * define('LDAP_USERNAME', 'CN=MyUser,DC=platform,DC=silverstripe,DC=com');
 * define('LDAP_PASSWORD', 'somesupersecretpassword');
 * </code>
 * The password can be rotated by running:
 * rotate_ldap_password /sites/_ss_environment.php LDAP_HOSTNAME LDAP_USERNAME LDAP_PASSWORD
 */

if(empty($_SERVER['argv'][1])) {
	echo "Missing first argument: _ss_environment.php file path\n";
	exit(1);
}
if(empty($_SERVER['argv'][2])) {
	echo "Missing second argument: LDAP hostname constant\n";
	exit(1);
}
if(empty($_SERVER['argv'][3])) {
	echo "Missing third argument: LDAP username constant\n";
	exit(1);
}
if(empty($_SERVER['argv'][4])) {
	echo "Missing fourth argument: LDAP password constant\n";
	exit(1);
}

$envFilePath = $_SERVER['argv'][1];
$hostnameConst = $_SERVER['argv'][2];
$usernameConst = $_SERVER['argv'][3];
$passwordConst = $_SERVER['argv'][4];

if(!file_exists($envFilePath)) {
	echo sprintf("%s does not exist\n", $envFilePath);
	exit(1);
}
if(!is_writable($envFilePath)) {
	echo sprintf("%s is not writable\n", $envFilePath);
	exit(1);
}

require_once($envFilePath);

$conn = ldap_connect(constant($hostnameConst));
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

$bind = @ldap_bind($conn, constant($usernameConst), constant($passwordConst));
if(!$bind) {
	echo sprintf(
		"Could not bind to LDAP: %s (error code: %s)\n",
		ldap_error($conn),
		ldap_errno($conn)
	);
	exit(1);
}

// ldap_read searches the directory but uses LDAP_SCOPE_BASE, so we only get a single entry.
// We're just doing this to validate that the entry exists in the directory.
$query = ldap_read($conn, constant($usernameConst), '(objectClass=user)', array('dn'));
if(!$query) {
	echo sprintf(
		"Could not read the LDAP entry %s: %s (error code: %s)\n",
		constant($usernameConst),
		ldap_error($conn),
		ldap_errno($conn)
	);
	exit(1);
}

$results = ldap_get_entries($conn, $query);

if(empty($results[0]['dn'])) {
	echo sprintf(
		"There was a problem retrieving the details for %s\n",
		constant($usernameConst)
	);
	exit(1);
}

// Generate a sufficiently complex password and set that as the password in LDAP.
// Note: This only supports Microsoft Active Directory and Samba based directories.
//
// With 24-character long password, this hardly ever loops.
for (;;) {
	$password = generatePassword(24);
	if (
		preg_match('/[A-Z]/', $password) &&
		preg_match('/[a-z]/', $password) &&
		preg_match('/[0-9]/', $password) &&
		preg_match('/[\!\@\#\$\%\&\*\?]/', $password)
	) {
		break;
	}
}

$success = ldap_modify($conn, $results[0]['dn'], array(
	'unicodePwd' => iconv('UTF-8', 'UTF-16LE', sprintf('"%s"', $password))
));

if(!$success) {
	echo sprintf(
		"Could not modify the LDAP entry %s: %s (error code: %s)\n",
		constant($usernameConst),
		ldap_error($conn),
		ldap_errno($conn)
	);
	exit(1);
}

ldap_close($conn);

// Replace the old password with the new one in the environment file.
$contents = file_get_contents($envFilePath);
$contents = str_replace(constant($passwordConst), $password, $contents);
file_put_contents($envFilePath, $contents);

echo sprintf(
	"LDAP password for %s has been reset. Old password has been replaced in %s\n",
	$results[0]['dn'],
	$envFilePath
);

exit(0);

function generatePassword($length = 24) {

	$passwordData = null;

	if(function_exists('mcrypt_create_iv')) {
		$e = mcrypt_create_iv(64, MCRYPT_DEV_URANDOM);
		if($e !== false) $passwordData = $e;
	}

	if (function_exists('openssl_random_pseudo_bytes')) {
		$e = openssl_random_pseudo_bytes(64, $strong);
		// Only return if strong algorithm was used
		if($strong) $passwordData = $e;
	}

	if (empty($passwordData)) {
		echo "Cannot generate passwords - PRNG functions missing? Try installing mcrypt.";
		exit(1);
	}

	// Convert the binary data into password-usable characters.
	$usable = str_replace('=', '', base64_encode($passwordData));

	// Re-map the base64 alphabet to include special password characters so we can satisfy all character classes.
	$withSpecial = strtr($usable, '/+abcABC', '!@#$%&*?');

	// Trim to requested size.
	$password = substr($withSpecial, 0, $length);

	return $password;
}
