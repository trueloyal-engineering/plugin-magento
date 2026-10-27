<?php

namespace TrueLoyal\LoyaltyRewards\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use TrueLoyal\LoyaltyRewards\Model\MemberJwt;

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
     * TrueLoyal section constructor.
     *
     * @param MemberJwt $memberJwt
     */
    public function __construct(
        MemberJwt $memberJwt
    ) {
        $this->memberJwt = $memberJwt;
    }

    /**
     * @inheritDoc
     */
    public function getSectionData()
    {
        $expiresAt = time() + MemberJwt::LIFETIME;
        return [
            'jwt_token' => $this->memberJwt->create($expiresAt),
            'expires_at' => $expiresAt
        ];
    }
}
