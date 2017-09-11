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
 * rotate_ldap_password <ss_env_file> <c_hostname|hostname> <c_privileged_dn> <c_privileged_password> [<c_target_dn> <c_target_password>]
 *
 * Example:
 * Assuming you have this configuration in your /sites/.env file:
 * <code>
 * LDAP_HOSTNAME="ldap://myldap.hostname:636"
 * PRIVILEGED_DN="CN=PrivilegedUser,DC=platform,DC=silverstripe,DC=com"
 * PRIVILEGED_PASSWORD="somesupersecretpassword"
 * TARGET_DN="CN=MyUser,DC=platform,DC=silverstripe,DC=com"
 * TARGET_PASSWORD="somesupersecretpassword"
 * </code>
 * The password can be rotated by running:
 * rotate_ldap_password /sites/_ss_environment.php LDAP_HOSTNAME PRIVILEGED_DN PRIVILEGED_PASSWORD TARGET_DN TARGET_PASSWORD
 *
 * LDAP endpoint hostname can also be overriden:
 * rotate_ldap_password /sites/_ss_environment.php ldaps://otherldap.org PRIVILEGED_DN PRIVILEGED_PASSWORD TARGET_DN TARGET_PASSWORD
 */

if (count($_SERVER['argv'])!=5 && count($_SERVER['argv'])!=7) {
    echo "Not enough arguments.\n\n";
    echo "Usage (`c_' suffix denotes constants, not actual value):\n";
    echo "	{$_SERVER['argv'][0]} <ss_env_file> <c_hostname|hostname> <c_privileged_dn> <c_privileged_password> [<c_target_dn> <c_target_password>]\n";
    exit(1);
}

$envFilePath = $_SERVER['argv'][1];
$hostname = $_SERVER['argv'][2];
$privilegedDNConst = $_SERVER['argv'][3];
$privilegedPasswordConst = $_SERVER['argv'][4];

if (empty($_SERVER['argv'][5])) {
    // No target user specified, assume the privileged user's password is being changed.
    $DNConst = $privilegedDNConst;
    $passwordConst = $privilegedPasswordConst;
} else {
    // Target specified for change.
    $DNConst = $_SERVER['argv'][5];
    $passwordConst = $_SERVER['argv'][6];
}

if (!file_exists($envFilePath)) {
    echo sprintf("%s does not exist\n", $envFilePath);
    exit(1);
}
if (!is_writable($envFilePath)) {
    echo sprintf("%s is not writable\n", $envFilePath);
    exit(1);
}

require_once($envFilePath);

$hostnameValue = defined($hostname) ? constant($hostname) : $hostname;
$conn = ldap_connect($hostnameValue);
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

$bind = @ldap_bind($conn, constant($privilegedDNConst), constant($privilegedPasswordConst));
if (!$bind) {
    echo sprintf(
        "Could not bind to LDAP: %s (error code: %s)\n",
        ldap_error($conn),
        ldap_errno($conn)
    );
    exit(1);
}

// ldap_read searches the directory but uses LDAP_SCOPE_BASE, so we only get a single entry.
// We're just doing this to validate that the entry exists in the directory.
$query = ldap_read($conn, constant($DNConst), '(objectClass=user)', ['dn']);
if (!$query) {
    echo sprintf(
        "Could not read the LDAP entry %s: %s (error code: %s)\n",
        constant($DNConst),
        ldap_error($conn),
        ldap_errno($conn)
    );
    exit(1);
}

$results = ldap_get_entries($conn, $query);

if (empty($results[0]['dn'])) {
    echo sprintf(
        "There was a problem retrieving the details for %s\n",
        constant($DNConst)
    );
    exit(1);
}

// Generate a sufficiently complex password and set that as the password in LDAP.
// Note: This only supports Microsoft Active Directory and Samba based directories.
//
// With 24-character long password, this hardly ever loops.
for (;;) {
    $password = generatePassword(24);
    if (preg_match('/[A-Z]/', $password) &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/[0-9]/', $password) &&
        preg_match('/[\!\@\#\$\%\&\*\?]/', $password)
    ) {
        break;
    }
}

$success = ldap_modify($conn, $results[0]['dn'], [
    'unicodePwd' => iconv('UTF-8', 'UTF-16LE', sprintf('"%s"', $password))
]);

if (!$success) {
    echo sprintf(
        "Could not modify the LDAP entry %s: %s (error code: %s)\n",
        constant($DNConst),
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

function generatePassword($length = 24)
{
    $passwordData = null;

    if (function_exists('mcrypt_create_iv')) {
        $e = mcrypt_create_iv(64, MCRYPT_DEV_URANDOM);
        if ($e !== false) {
            $passwordData = $e;
        }
    }

    if (empty($passwordData) && function_exists('openssl_random_pseudo_bytes')) {
        $e = openssl_random_pseudo_bytes(64, $strong);
        // Only return if strong algorithm was used
        if ($strong) {
            $passwordData = $e;
        }
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
