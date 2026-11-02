<?php
namespace TrueLoyal\LoyaltyRewards\Observer\Customer;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;
use TrueLoyal\LoyaltyRewards\Helper\Config;

/**
 * Tells the storefront that the customer's profile changed, so js/widget.js reloads the "trueloyal" section
 * (the widget JWT carries name, address and phone, and the widget login writes them to the member).
 * customer-data's sections.xml can't do this: the address form submits natively and never triggers it,
 * and checkout saves addresses over REST.
 */
class ProfileChangedObserver implements ObserverInterface
{
    /**
     * Cookie holding the profile version; CustomerData\TrueLoyal stores the value each token was built with.
     */
    public const COOKIE_NAME = 'trueloyal_profile';

    /**
     * ProfileChangedObserver constructor.
     *
     * @param Config $config
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     * @param UserContextInterface $userContext
     * @param State $appState
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly SessionManagerInterface $sessionManager,
        private readonly UserContextInterface $userContext,
        private readonly State $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->getConfig(Config::XML_PATH_LOYALTY_REWARDS_ACTIVE)) {
            return;
        }
        /* Over REST, only when the customer made the change (storefront checkout), not an integration */
        if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND
            && $this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER
        ) {
            return;
        }
        try {
            $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setPath($this->sessionManager->getCookiePath())
                ->setDomain($this->sessionManager->getCookieDomain());
            $this->cookieManager->setPublicCookie(self::COOKIE_NAME, bin2hex(random_bytes(8)), $metadata);
        } catch (\Exception $e) {
            $this->logger->error('TrueLoyal: could not set the profile cookie: ' . $e->getMessage());
        }
    }
}
