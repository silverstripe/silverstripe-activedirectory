<?php
/**
 * Class SAMLHelper
 *
 * SAMLHelper acts as a simple wrapper for the OneLogin implementation, so that we can configure
 * and inject it via the config system.
 */
class SAMLHelper extends SS_Object
{
    /**
     * @var array
     */
    public static $dependencies = [
        'SAMLConfService' => '%$SAMLConfService',
    ];

    /**
     * @var SAMLConfService
     */
    public $SAMLConfService;

    /**
     * @return OneLogin_Saml2_Auth
     */
    public function getSAMLauth()
    {
        $samlConfig = $this->SAMLConfService->asArray();
        return new \OneLogin_Saml2_Auth($samlConfig);
    }
}
