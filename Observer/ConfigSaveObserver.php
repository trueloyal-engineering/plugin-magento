<?php

namespace TrueLoyal\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TrueLoyal\LoyaltyRewards\Helper\Data;
use TrueLoyal\LoyaltyRewards\Logger\Logger as TrueLoyalLogger;

class ConfigSaveObserver implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var TrueLoyalLogger
     */
    private $logger;
    /**
     * Data constructor.
     *
     * @param Data $helper
     * @param TrueLoyalLogger $logger
     */
    public function __construct(
        Data $helper,
        TrueLoyalLogger $logger,
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $existingWebhookUrl = $this->helper->getWebHookUrl();
        $webhookIntegrationID = $this->helper->getWebHookIntegrationID();
        $this->logger->info('Existing Webhook URL: ' . $existingWebhookUrl);
        if (!empty($existingWebhookUrl) && empty($webhookIntegrationID)) {
            return;
        }
        $url = $this->helper->getWebHookIntegrationURL();
        if (empty($existingWebhookUrl)) {
            $integration = $this->helper->createOrUpdateZIFIntegration($url);
        } else {
            $integration = $this->helper->createOrUpdateZIFIntegration($url . "/" . $webhookIntegrationID);
            // Integrations created by older builds run on the v1 connector. Creating a new one moves
            // the store to the v2 connector, and TrueLoyal deactivates the old one.
            if (($integration['integration_type'] ?? '') !== Data::INTEGRATION_TYPE) {
                $integration = $this->helper->createOrUpdateZIFIntegration($url);
            }
        }
        // Saved on updates too: TrueLoyal may move the integration to a new workflow URL
        $webhookUrl = $integration['config']['zif_config']['workflow_url'];
        if ($webhookUrl !== $existingWebhookUrl) {
            $this->logger->info('New Webhook URL: ' . $webhookUrl);
            $this->helper->saveWebHookUrl($webhookUrl);
        }
        if ($integration['id'] !== $webhookIntegrationID) {
            $this->helper->saveWebHookIntegrationID($integration['id']);
        }
    }
}