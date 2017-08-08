<?php

namespace Heidelpay\Gateway\PaymentMethods;

use Heidelpay\Gateway\Model\ResourceModel\PaymentInformation\CollectionFactory as HeidelpayPaymentInformationFactory;
use Heidelpay\Gateway\Model\ResourceModel\Transaction\CollectionFactory as HeidelpayTransactionFactory;

/**
 * easycredit PaymentMethod
 *
 * The heidelpay implementation for the easyCredit payment method.
 *
 * @licence Use of this software requires acceptance of the Licence Agreement. See LICENSE file.
 * @copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento2
 *
 * @author Stephano Vogel
 *
 * @package Heidelpay\Gateway\PaymentMethods
 */
class HeidelpayEasycreditPaymentMethod extends HeidelpayAbstractPaymentMethod
{
    /**
     * @var string
     */
    protected $_code = 'hgweasycredit';

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface
     */
    protected $orderCollectionFactory;

    /**
     * HeidelpayEasycreditPaymentMethod constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlinterface
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\Module\ResourceInterface $moduleResource
     * @param \Heidelpay\Gateway\Helper\Payment $paymentHelper
     * @param \Magento\Sales\Helper\Data $salesHelper
     * @param HeidelpayPaymentInformationFactory $heidelpayPaymentInformationCollectionFactory
     * @param \Heidelpay\Gateway\Model\TransactionFactory $transactionFactory
     * @param HeidelpayTransactionFactory $heidelpayTransactionCollectionFactory
     * @param \Heidelpay\PhpApi\PaymentMethods\EasyCreditPaymentMethod $easyCreditPaymentMethod
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlinterface,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Heidelpay\Gateway\Helper\Payment $paymentHelper,
        \Magento\Sales\Helper\Data $salesHelper,
        HeidelpayPaymentInformationFactory $heidelpayPaymentInformationCollectionFactory,
        \Heidelpay\Gateway\Model\TransactionFactory $transactionFactory,
        HeidelpayTransactionFactory $heidelpayTransactionCollectionFactory,
        \Heidelpay\PhpApi\PaymentMethods\EasyCreditPaymentMethod $easyCreditPaymentMethod,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $request,
            $urlinterface,
            $encryptor,
            $logger,
            $localeResolver,
            $productMetadata,
            $moduleResource,
            $paymentHelper,
            $salesHelper,
            $heidelpayPaymentInformationCollectionFactory,
            $transactionFactory,
            $heidelpayTransactionCollectionFactory,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_heidelpayPaymentMethod = $easyCreditPaymentMethod;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function getHeidelpayUrl($quote)
    {
        // initialize data for the request (e.g. security, user- & basketdata, ...)
        parent::getHeidelpayUrl($quote);

        // set relevant risk information
        $this->_heidelpayPaymentMethod->getRequest()->getRiskInformation()
            ->set('guestcheckout', $quote->getCustomerIsGuest() ? 'true' : 'false')
            ->set('since', date('Y-m-d', strtotime($quote->getCustomer()->getCreatedAt())))
            ->set('ordercount', $this->orderCollectionFactory->create($quote->getCustomerId())->count());

        $this->_heidelpayPaymentMethod->getRequest()->getBasket()->set('id', $this->submitQuoteToBasketApi($quote));

        // TODO: remove debug log
        $this->_logger->debug(
            'heidelpay - easyCredit Request: ' . $this->_heidelpayPaymentMethod->getRequest()->toJson()
        );

        $this->_heidelpayPaymentMethod->initialize();

        // TODO: remove debug log
        $this->_logger->debug(
            'heidelpay - easyCredit Response: ' . $this->_heidelpayPaymentMethod->getResponse()->toJson()
        );

        return $this->_heidelpayPaymentMethod->getResponse();
    }
}
