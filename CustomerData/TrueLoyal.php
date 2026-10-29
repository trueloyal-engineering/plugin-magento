<?php

namespace TrueLoyal\LoyaltyRewards\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use TrueLoyal\LoyaltyRewards\Model\MemberJwt;
use TrueLoyal\LoyaltyRewards\Observer\Customer\ProfileChangedObserver;

/**
 * "trueloyal" customer-data section: the widget JWT, loaded per visitor through customer/section/load
 * so it never ends up in full-page-cached HTML.
 */
class TrueLoyal implements SectionSourceInterface
{
    /**
     * @var MemberJwt
     */
    private $memberJwt;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * TrueLoyal section constructor.
     *
     * @param MemberJwt $memberJwt
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        MemberJwt $memberJwt,
        CookieManagerInterface $cookieManager
    ) {
        $this->memberJwt = $memberJwt;
        $this->cookieManager = $cookieManager;
    }

    /**
     * @inheritDoc
     */
    public function getSectionData()
    {
        $expiresAt = time() + MemberJwt::LIFETIME;
        return [
            'jwt_token' => $this->memberJwt->create($expiresAt),
            'expires_at' => $expiresAt,
            /* js/widget.js reloads the section when the profile cookie no longer matches this */
            'profile_version' => (string)$this->cookieManager->getCookie(ProfileChangedObserver::COOKIE_NAME, '')
        ];
    }
}
