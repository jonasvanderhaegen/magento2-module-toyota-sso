<?php

namespace Jvdh\ToyotaSsoProcessing\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Jvdh\SamlExtendedAdvancedSettings\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class Data extends AbstractHelper
{
    /**
     * @var array Mapping of customer groups based on SAML data
     * This array defines the relationship between customer group codes,
     * the countries they belong to, and specific SAML group codes.
     */
    private static array $customerGroupMapping = [
        15 => [ // b2b_dealers_es
            'countries' => ['TESP', 'TES'], // SAML country codes for Spain
            'codes' => [
                'Retailer' // SAML group code for retailers
            ]
        ],
        16 => [ // b2b_dealers_pl
            'countries' => ['TPOL'], // SAML country codes for Poland
            'codes' => [
                'Retailer'
            ]
        ],
        17 => [ // b2b_dealers_uk
            'countries' => ['TGBR'], // SAML country codes for the UK
            'codes' => [
                'Retailer'
            ]
        ],
        8 => [ // b2b_tme
            'codes' => [
                'TME', // SAML group code for Toyota Motor Europe
            ]
        ],
        9 => [ // b2b_dealers
            'codes' => [
                'Retailer', // Generic retailer code
            ]
        ],
        10 => [ // b2b_nmsc
            'codes' => [
                'NMSC', // National Marketing and Sales Companies
            ]
        ],
        12 => [ // Toyota - B2C Employees
            'codes' => [
                'Employee' // SAML group code for employees
            ]
        ]
    ];

    /**
     * @var bool Flag to track if the customer update event has been dispatched
     */
    protected bool $customerUpdatedEvent = false;

    /**
     * Constructor method to initialize the helper with required dependencies.
     *
     * @param Context $context Provides access to application-related utilities
     * @param Config $config Helper for extended SAML configurations
     */
    public function __construct(Context $context, protected Config $config)
    {
        parent::__construct($context);
    }

    /**
     * Check if the customer update event has been dispatched.
     *
     * @return bool Returns true if the event is dispatched, false otherwise.
     */
    public function ssoCustomerUpdatedEventIsDispatched(): bool
    {
        return $this->customerUpdatedEvent;
    }

    /**
     * Mark the customer update event as dispatched.
     *
     * @return void
     */
    public function dispatchedSsoCustomerUpdatedEvent(): void
    {
        $this->customerUpdatedEvent = true;
    }

    /**
     * Check if the module is enabled in the system configuration.
     *
     * @return bool Returns true if the module is enabled, false otherwise.
     */
    public function isModuleEnabled(): bool
    {
        return $this->scopeConfig->getValue(
            'foobar_saml_customer/advanced/enabled_for_toyota',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Resolve the customer group ID based on SAML attributes.
     *
     * This is a public-facing method that wraps the internal logic
     * for determining the customer group code.
     *
     * @param array $attributes Array of SAML attributes for the customer.
     * @return int The resolved customer group ID.
     * @throws LocalizedException If no valid group can be resolved.
     */
    public function resolveCustomerGroupId(array $attributes): int
    {
        return $this->resolveCustomerGroupCode($attributes);
    }

    /**
     * Determine the appropriate customer group code based on SAML data.
     *
     * This method uses the `customerGroupMapping` array to match SAML
     * attributes (e.g., country and group codes) to predefined customer group codes.
     *
     * @param array $attributes Array of SAML attributes for the customer.
     * @return int The resolved customer group code.
     * @throws LocalizedException If no matching group is found.
     */
    protected function resolveCustomerGroupCode(array $attributes): int
    {
        // Extract the group code from the SAML attributes
        $toyotaGroup = $this->resolveToyotaGroup($attributes);

        // Extract the primary country code from the organization identifier
        // The 'Org' attribute is expected to be a string containing the country code followed by additional data.
        $country = explode('.', $attributes['Org'][0])[0];

        // Iterate through the mapping to find the appropriate customer group
        foreach (self::$customerGroupMapping as $code => $toyotaGroups) {
            if (!in_array($toyotaGroup, $toyotaGroups['codes'])) {
                continue; // Skip if the group code doesn't match
            }
            if (array_key_exists('countries', $toyotaGroups) && !in_array($country, $toyotaGroups['countries'])) {
                continue; // Skip if the country doesn't match
            }
            return $code; // Return the matching customer group code
        }

        // Throw an exception if no group matches
        throw new LocalizedException(
            new Phrase("Can't map values to customer group. Please contact us.")
        );
    }

    /**
     * Extract the group value from the SAML attributes.
     *
     * This method retrieves the group value based on the configured
     * group field key from the SAML attributes.
     *
     * @param array $attributes Array of SAML attributes for the customer.
     * @return string The resolved group value.
     */
    public function resolveToyotaGroup(array $attributes): string
    {
        return $attributes[$this->config->getGroupsValue()][0];
    }
}
