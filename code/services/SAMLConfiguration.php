<?php
/**
 * Class SAMLConfiguration
 *
 * This object's job is to convert configuration from SilverStripe config system
 * into an array that can be consumed by the Onelogin SAML implementation.
 *
 * The configuration tells the IdP and SP how to establish the circle of trust - i.e.
 * how to exchange certificates and which endpoints to use (e.g. see SAMLConfiguration::metadata).
 *
 * https://syncplicity.zendesk.com/hc/en-us/articles/202392814-Single-sign-on-with-ADFS
 */
class SAMLConfiguration extends SS_Object
{
    /**
     * @var bool
     */
    private static $strict = true;

    /**
     * @var bool
     */
    private static $debug = false;

    /**
     * @var array
     */
    private static $SP;

    /**
     * @var array
     */
    private static $IdP;

    /**
     * @var array
     */
    private static $Security = [
        'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    ];

    /**
     * @return array
     */
    public function asArray()
    {
        $conf = [];

        $conf['strict'] = $this->config()->get('strict');
        $conf['debug'] = $this->config()->get('debug');

        // SERVICE PROVIDER SECTION
        $sp = $this->config()->get('SP');

        // Set baseurl for SAML messages coming back to the SP
        $conf['baseurl'] = sprintf('%s/saml', $sp['entityId']);

        $spCertPath = Director::is_absolute($sp['x509cert']) ? $sp['x509cert'] : sprintf('%s/%s', BASE_PATH, $sp['x509cert']);
        $spKeyPath = Director::is_absolute($sp['privateKey']) ? $sp['privateKey'] : sprintf('%s/%s', BASE_PATH, $sp['privateKey']);
        $conf['sp']['entityId'] = $sp['entityId'];
        $conf['sp']['assertionConsumerService'] = [
            'url' => $sp['entityId'] . '/saml/acs',
            'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_POST
        ];
        $conf['sp']['NameIDFormat'] = isset($sp['nameIdFormat']) ? $sp['nameIdFormat'] : OneLogin_Saml2_Constants::NAMEID_TRANSIENT;
        $conf['sp']['x509cert'] = file_get_contents($spCertPath);
        $conf['sp']['privateKey'] = file_get_contents($spKeyPath);

        // IDENTITY PROVIDER SECTION
        $idp = $this->config()->get('IdP');
        $conf['idp']['entityId'] = $idp['entityId'];
        $conf['idp']['singleSignOnService'] = [
            'url' => $idp['singleSignOnService'],
            'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_REDIRECT,
        ];
        if (isset($idp['singleLogoutService'])) {
            $conf['idp']['singleLogoutService'] = [
                'url' => $idp['singleLogoutService'],
                'binding' => OneLogin_Saml2_Constants::BINDING_HTTP_REDIRECT,
            ];
        }

        $idpCertPath = Director::is_absolute($idp['x509cert']) ? $idp['x509cert'] : sprintf('%s/%s', BASE_PATH, $idp['x509cert']);
        $conf['idp']['x509cert'] = file_get_contents($idpCertPath);

        $conf['security'] = [
            /** signatures and encryptions offered */
            // Indicates that the nameID of the <samlp:logoutRequest> sent by this SP will be encrypted.
            'nameIdEncrypted' => true,
            // Indicates whether the <samlp:AuthnRequest> messages sent by this SP will be signed. [Metadata of the SP will offer this info]
            'authnRequestsSigned' => true,
            // Indicates whether the <samlp:logoutRequest> messages sent by this SP will be signed.
            'logoutRequestSigned' => true,
            // Indicates whether the <samlp:logoutResponse> messages sent by this SP will be signed.
            'logoutResponseSigned' => true,
            'signMetadata' => false,
            /** signatures and encryptions required **/
            // Indicates a requirement for the <samlp:Response>, <samlp:LogoutRequest>
            // and <samlp:LogoutResponse> elements received by this SP to be signed.
            'wantMessagesSigned' => false,
            // Indicates a requirement for the <saml:Assertion> elements received by
            // this SP to be signed. [Metadata of the SP will offer this info]
            'wantAssertionsSigned' => true,
            // Indicates a requirement for the NameID received by
            // this SP to be encrypted.
            'wantNameIdEncrypted' => false,
            // Authentication context.
            // Set to false and no AuthContext will be sent in the AuthNRequest,
            // Set true or don't present thi parameter and you will get an AuthContext 'exact' 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport'
            // Set an array with the possible auth context values: array ('urn:oasis:names:tc:SAML:2.0:ac:classes:Password', 'urn:oasis:names:tc:SAML:2.0:ac:classes:X509'),
            'requestedAuthnContext' => $this->getRequestedAuthnContext(),
            // Indicates if the SP will validate all received xmls.
            // (In order to validate the xml, 'strict' and 'wantXMLValidation' must be true).
            'wantXMLValidation' => true,
        ];

        $security = $this->config()->get('Security');

        if (isset($security['signatureAlgorithm'])) {
            // Algorithm that the toolkit will use on signing process. Options:
            //  - 'http://www.w3.org/2000/09/xmldsig#rsa-sha1'
            //  - 'http://www.w3.org/2000/09/xmldsig#dsa-sha1'
            //  - 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'
            //  - 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384'
            //  - 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512'
            $conf['security']['signatureAlgorithm'] = $security['signatureAlgorithm'];
        }

        if (isset($security['requestedAuthnContextComparison'])) {
            // Allows the authn comparison parameter to be set, defaults to 'exact' if
            // the setting is not present.
            // better | exact | maximum | minimum
            $conf['security']['requestedAuthnContextComparison'] = $security['requestedAuthnContextComparison'];
        }

        return $conf;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getRequestedAuthnContext()
    {
        $security = $this->config()->get('Security');

        if (isset($security['requestedAuthnContext'])) {
            throw new Exception(sprintf(
                'Config setting "%s" is not settable directly. Please set either "%s" or "%s" in your YAML.',
                'SAMLConfiguration.Security.requestedAuthnContext',
                'SAMLConfiguration.Security.requestedAuthnContextBool',
                'SAMLConfiguration.Security.requestedAuthnContextArray'
            ));
        }
        if (isset($security['requestedAuthnContextBool']) && isset($security['requestedAuthnContextArray'])) {
            throw new Exception(sprintf(
                'Not permitted to set both "%s" and "%s" configuration elements. Check your config yamls.',
                'SAMLConfiguration.Security.requestedAuthnContextBool',
                'SAMLConfiguration.Security.requestedAuthnContextArray'
            ));
        }

        $requestedAuthnContext = [
            'urn:federation:authentication:windows',
            'urn:oasis:names:tc:SAML:2.0:ac:classes:Password',
            'urn:oasis:names:tc:SAML:2.0:ac:classes:X509',
        ];

        if (isset($security['requestedAuthnContextBool'])) {
            $requestedAuthnContext = $security['requestedAuthnContextBool'];
        }

        if (isset($security['requestedAuthnContextArray'])) {
            $requestedAuthnContext = $security['requestedAuthnContextArray'];
        }

        return $requestedAuthnContext;
    }
}
