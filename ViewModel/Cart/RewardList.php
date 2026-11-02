<?php
declare(strict_types=1);

namespace TrueLoyal\LoyaltyRewards\ViewModel\Cart;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;

/**
 * Cart page rewards: the member's balance, the rewards they can apply, or the one applied
 */
class RewardList implements ArgumentInterface
{
    private ?array $state = null;

    public function __construct(
        private readonly Data $helper,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Shown to members with points and rewards, and whenever a reward is applied
     */
    public function isVisible(): bool
    {
        $state = $this->state();
        if (!$this->helper->isRewardDropDownEnable()) {
            return false;
        }
        return ($state['memberId'] !== '' && $state['points'] !== 'error' && $state['rewards'])
            || (string)$state['trueloyalQuote']->getRewardRulesData() !== '';
    }

    public function getPoints(): int
    {
        $points = $this->state()['points'];
        return $points === 'error' ? 0 : (int)$points;
    }

    /**
     * The admin's cart label (HTML) with the balance in place of {{AVAILABLE_POINTS}}, or before it
     */
    public function getMessageHtml(): string
    {
        $points = '<strong>' . $this->getPoints() . '</strong>';
        $message = (string)$this->helper->getRewardLabelAtCartPage();
        return str_contains($message, '{{AVAILABLE_POINTS}}')
            ? str_replace('{{AVAILABLE_POINTS}}', $points, $message)
            : $points . ' ' . $message;
    }

    /**
     * Rewards the member can apply, by reward ID
     *
     * @return array<string, array>
     */
    public function getRewards(): array
    {
        return $this->state()['rewards'];
    }

    /**
     * The applied reward rule, null when none
     */
    public function getAppliedReward(): ?array
    {
        $trueloyalQuote = $this->state()['trueloyalQuote'];
        if (empty($trueloyalQuote->getRewardRulesData()) || empty($trueloyalQuote->getRedeemRewardDiscount())) {
            return null;
        }
        return $this->state()['applied'] ?: null;
    }

    /**
     * Configuration of the TrueLoyal_LoyaltyRewards/js/reward-list widget
     */
    public function getJsConfig(): array
    {
        return [
            'url' => $this->urlBuilder->getUrl('loyaltyRewards/loyaltyrewards/applyPoint', ['_secure' => true]),
            'points' => $this->getPoints(),
            'applied' => $this->getAppliedReward() !== null,
        ];
    }

    private function state(): array
    {
        if ($this->state === null) {
            $quote = $this->helper->getQuote();
            $trueloyalQuote = $this->helper->getTrueLoyalQuoteByQuoteId($quote->getId());
            $this->state = [
                'trueloyalQuote' => $trueloyalQuote,
                'applied' => $this->helper->getRewardRulesData($quote, $trueloyalQuote->getRedeemRewardDiscount()),
                'memberId' => $this->helper->getMemberIdentifierValue(),
                'points' => $this->helper->getRewardPoints(),
                'rewards' => $this->helper->getRedeemRules(),
            ];
        }
        return $this->state;
    }
}
