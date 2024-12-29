<?php

namespace Jvdh\ToyotaSsoProcessing\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Jvdh\ToyotaSsoProcessing\Helper\Data;

class Saml2CustomerSuccessfullyUpdated implements ObserverInterface
{
    /**
     * Constructor method to inject the helper dependency.
     * 
     * @param Data $helperData Helper class instance for SSO-related operations.
     */
    public function __construct(protected Data $helperData)
    {
    }

    /**
     * Executes when the `saml2_customer_successfully_updated` event is triggered.
     *
     * This method updates the `customerUpdatedEvent` flag in the helper class,
     * allowing other components (e.g., plugins) to detect that the customer has been updated.
     *
     * @param Observer $observer The event observer instance.
     * @return void
     */
    public function execute(Observer $observer): void
    {
        // Flag the event as dispatched to signal that the customer update is complete.
        $this->helperData->dispatchedSsoCustomerUpdatedEvent();
    }
}
