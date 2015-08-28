<?php
/**
 * Validate Client ID and Client Secret
 *
 * @category    Amazon
 * @package     Amazon_Payments
 * @copyright   Copyright (c) 2014 Amazon.com
 * @license     http://opensource.org/licenses/Apache-2.0  Apache License, Version 2.0
 */

class Amazon_Payments_Model_System_Config_Backend_Enabled extends Mage_Core_Model_Config_Data
{
    /**
     * Validate data
     */
    public function save()
    {

        $data = $this->_getCredentials();
        $isEnabled = $this->getValue();

        if ($isEnabled) {
            if ($data['seller_id']['value'] && !ctype_alnum($data['seller_id']['value'])) {
                Mage::getSingleton('core/session')->addError('Error: Please verify your Seller ID (alphanumeric characters only).');
            }
        }
        return parent::save();
    }
    /**
     * Perform API call to Amazon to validate keys
     *
     */
    public function _afterSaveCommit()
    {
        $data = $this->_getCredentials();
        $isEnabled = $this->getValue();

        $access_secret = $data['access_secret']['value'];
        if (strpos($access_secret, '*****') !== FALSE) { // Encrypted
            $access_secret = Mage::getSingleton('amazon_payments/config')->getAccessSecret();
        }

        if ($isEnabled) {
            $config = array (
                'ServiceURL' => "https://mws.amazonservices.com/Sellers/2011-07-01",
                'ProxyHost' => null,
                'ProxyPort' => -1,
                'ProxyUsername' => null,
                'ProxyPassword' => null,
                'MaxErrorRetry' => 3,
            );
            $service = new MarketplaceWebServiceSellers_Client(
                $data['access_key']['value'],
                $access_secret,
                'Login and Pay for Magento',
                '1.3',
                $config);

            $request = new MarketplaceWebServiceSellers_Model_ListMarketplaceParticipationsRequest();
            $request->setSellerId($data['seller_id']['value']);
            try {
                $service->ListMarketplaceParticipations($request);
                Mage::getSingleton('core/session')->addSuccess("All of your Amazon API keys are correct!");
                }
            catch (MarketplaceWebServiceSellers_Exception $ex) {
                if ($ex->getErrorCode() == 'InvalidAccessKeyId'){
                    Mage::getSingleton('core/session')->addError("The Amazon MWS Access Key is incorrect");
                }
                else if ($ex->getErrorCode() == 'SignatureDoesNotMatch'){
                    Mage::getSingleton('core/session')->addError("The Amazon MWS Secret Key is incorrect");
                }
                else if ($ex->getErrorCode() == 'InvalidParameterValue'){
                    Mage::getSingleton('core/session')->addError("The Amazon Seller/Merchant ID is incorrect");
                }
                else if ($ex->getErrorCode() == 'AccessDenied') {
                    Mage::getSingleton('core/session')->addError("The Amazon Seller/Merchant ID does not match the MWS keys provided");
                }
                else{
                    $string =  " Error Message: " . $ex->getMessage();
                    $string .= " Response Status Code: " . $ex->getStatusCode();
                    $string .= " Error Code: " . $ex->getErrorCode();
                    Mage::getSingleton('core/session')->addError($string);
                }
            }
        }
        return parent::_afterSaveCommit();
    }

    /**
     * Return dynamic help/comment text
     */
    public function getCommentText(Mage_Core_Model_Config_Element $element, $currentValue)
    {
        $version = Mage::getConfig()->getModuleConfig("Amazon_Payments")->version;

        // SimplePath
        $replace_cleanup = array('index.php/', ':80', ':443');
        $callbackUrl = str_replace($replace_cleanup, '', Mage::getUrl('amazon_payments/simplepath', array('_store' => 1, '_forced_secure' => true)));
        //$callbackUrl = Mage::helper('adminhtml')->getUrl("adminhtml/amazon_payments/simplepath", array('_forced_secure' => true));

        $_simplePath = Mage::getModel('amazon_payments/simplePath');
        $publickey = urlencode($_simplePath->getPublicKey());
        $amazonSimplepathUrl = $_simplePath::API_ENDPOINT_DOWNLOAD_KEYS . '?post_url=' . $callbackUrl . '&pub_key=' . $publickey;

        return "v$version

        <!-- SimplePath URL -->
        <script>
        var amazonSimplepathUrl = '$amazonSimplepathUrl';
        console.log(amazonSimplepathUrl);
        </script>
        ";
    }

    /**
     * Return credentials
     */
    private function _getCredentials()
    {
        $groups = $this->getData('groups');
        return $groups['ap_credentials']['fields'];
    }

}
