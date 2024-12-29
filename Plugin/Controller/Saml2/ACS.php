<?php

namespace Jvdh\ToyotaSsoProcessing\Plugin\Controller\Saml2;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Jvdh\ToyotaSsoProcessing\Helper\Data;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;

use Foobar\SAML\Controller\Saml2\ACS as FoobarACS;

class ACS
{
    /**
     * Constructor to inject dependencies required for SSO processing.
     *
     * @param ResultFactory $resultFactory Factory to create results like redirects
     * @param Session $checkoutSession Current customer checkout session
     * @param CartRepositoryInterface $quoteRepository Repository to manage customer quotes
     * @param CustomerRepositoryInterface $customerRepository Repository to fetch and save customer data
     * @param Data $helperData Helper class for SSO processing and group resolution
     * @param ManagerInterface $messageManager Manager to display user-facing messages
     */
    public function __construct(
        protected ResultFactory $resultFactory,
        private Session $checkoutSession,
        private CartRepositoryInterface $quoteRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected Data $helperData,
        protected ManagerInterface $messageManager
    ) {
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->customerRepository = $customerRepository;
        $this->helperData = $helperData;
        $this->messageManager = $messageManager;
    }

    /**
     * Overrides ACS controller action to assign customer group if required.
     *
     * This method processes the SAML response and determines the correct customer
     * group based on SAML attributes. If a matching group is found, the customer
     * is updated, and their quote is updated with the new group.
     *
     * @param FoobarACS $subject Original ACS controller instance
     * @param mixed $result Original result of the ACS controller
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface|mixed
     * @throws NoSuchEntityException If the customer does not exist
     * @throws \Magento\Framework\Exception\InputException On invalid input
     * @throws \Magento\Framework\Exception\LocalizedException On localization errors
     * @throws \Magento\Framework\Exception\State\InputMismatchException On state mismatch errors
     */
    public function afterExecute(FoobarACS $subject, $result)
    {
        // Check if the module is enabled in the configuration
        if (!$this->helperData->isModuleEnabled()) {
            return $result; // Return early if the module is disabled
        }

        // Retrieve the current customer session and customer model
        $customerSession = $subject->_getCustomerSession();
        $customer = $customerSession->getCustomer();
        $customerModel = $this->customerRepository->getById($customer->getId());

        // Process the SAML response to extract attributes
        $auth = $subject->_getSAMLAuth();
        $auth->processResponse();
        $attributes = $auth->getAttributes();

        try {
            // Resolve the customer group based on SAML attributes
            $groupId = $this->helperData->resolveCustomerGroupId($attributes);
        } catch (\Exception $e) {
            // Handle errors in group resolution by logging out the customer
            $this->messageManager->addError(__($e->getMessage()));
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl('/customer/account/logout/');
        }

        // Update the customer's group and save the updated model
        $customerModel->setGroupId($groupId);
        $customerSession->setCustomerGroupId($groupId);
        $this->customerRepository->save($customerModel);

        // Update the customer's quote with the new group
        $quote = $this->checkoutSession->getQuote();
        $quote->setCustomerGroupId($groupId);
        $quote->setCustomer($customerModel);
        $this->quoteRepository->save($quote);

        return $result; // Return the original result
    }
}
