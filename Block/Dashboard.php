<?php

namespace TrueLoyal\LoyaltyRewards\Block;

use Magento\Framework\View\Element\Template;
use TrueLoyal\LoyaltyRewards\Helper\Config;

/**
 * Loads the TrueLoyal widget on every page. The output is full-page cached, so it must not depend on the
 * visitor: the member JWT comes from the "trueloyal" customer-data section.
 */
class Dashboard extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * Dashboard constructor.
     *
     * @param Template\Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Is the module switched on in config (independent of the visitor, unlike Config::isModuleEnabled)
     *
     * @return bool
     */
    public function isModuleActive(): bool
    {
        return (bool)$this->config->getConfig(Config::XML_PATH_LOYALTY_REWARDS_ACTIVE);
    }

    /**
     * Get Partner Id
     *
     * @return mixed|string
     */
    public function getPartnerId()
    {
        return $this->config->getPartnerId();
    }
}
