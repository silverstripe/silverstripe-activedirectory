<?php

namespace SilverStripe\ActiveDirectory\Helpers;

use SilverStripe\ActiveDirectory\Services\SAMLConfiguration;
use SilverStripe\Core\Injector\Injectable;
use OneLogin_Saml2_Auth;

/**
 * Class SAMLHelper
 *
 * SAMLHelper acts as a simple wrapper for the OneLogin implementation, so that we can configure
 * and inject it via the config system.
 */
class SAMLHelper
{
    use Injectable;

    /**
     * @var array
     */
    public static $dependencies = [
        'SAMLConfService' => '%$SilverStripe\\ActiveDirectory\\Services\\SAMLConfiguration',
    ];

    /**
     * @var SAMLConfiguration
     */
    public $SAMLConfService;

    /**
     * @return OneLogin_Saml2_Auth
     */
    public function getSAMLauth()
    {
        $samlConfig = $this->SAMLConfService->asArray();
        return new OneLogin_Saml2_Auth($samlConfig);
    }
}
