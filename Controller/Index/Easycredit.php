<?php

namespace Heidelpay\Gateway\Controller\Index;

/**
 * Class Easycredit
 *
 * @license Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link https://dev.heidelpay.de/magento
 *
 * @author Stephano Vogel
 *
 * @package heidelpay\magento2\controller\index\easycredit
 */
class Easycredit extends \Heidelpay\Gateway\Controller\HgwAbstract
{
    /** @var \Heidelpay\Gateway\Model\ResourceModel\Transaction\CollectionFactory */
    protected $transactionCollectionFactory;

    /**
     * heidelpay Easycredit controller constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteObject
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param HeidelpayHelper $paymentHelper
     * @param \Magento\Sales\Helper\Data $salesHelper
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param OrderCommentSender $orderCommentSender
     * @param \Magento\Framework\Encryption\Encryptor $encryptor
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param \Heidelpay\PhpApi\Response $heidelpayResponse
     * @param \Heidelpay\Gateway\Model\ResourceModel\Transaction\CollectionFactory $transactionCollectionFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Quote\Api\CartRepositoryInterface $quoteObject,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        HeidelpayHelper $paymentHelper,
        \Magento\Sales\Helper\Data $salesHelper,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        OrderCommentSender $orderCommentSender,
        \Magento\Framework\Encryption\Encryptor $encryptor,
        \Magento\Customer\Model\Url $customerUrl,
        \Heidelpay\PhpApi\Response $heidelpayResponse,
        \Heidelpay\Gateway\Model\ResourceModel\Transaction\CollectionFactory $transactionCollectionFactory
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $orderFactory,
            $urlHelper,
            $logger,
            $cartManagement,
            $quoteObject,
            $resultPageFactory,
            $paymentHelper,
            $orderSender,
            $invoiceSender,
            $orderCommentSender,
            $encryptor,
            $customerUrl
        );

        $this->heidelpayResponse = $heidelpayResponse;
        $this->transactionCollectionFactory = $transactionCollectionFactory;
        $this->salesHelper = $salesHelper;
    }

    public function execute()
    {
        $session = $this->getCheckout();
        $quoteId = $session->getQuoteId();

        if (empty($quoteId)) {
            $this->_logger->warning('Heidelpay - easyCredit Controller: Called with empty quoteId');

            return $this->_redirect('checkout/cart/', ['_secure' => true]);
        }

        /** @var array $data */
        $data = null;

        try {
            /** @var \Heidelpay\Gateway\Model\Transaction $transaction */
            $transaction = $this->transactionCollectionFactory->create()->loadByQuoteId($quoteId);
            $data = $transaction->getJsonResponse();
        } catch (\Exception $e) {
            $this->_logger->error('Heidelpay - easyCredit Controller: Load transaction fail. ' . $e->getMessage());
        }

        // if our data is still null, we got no transaction data - so nothing to work with.
        // - redirect the user back to the checkout cart.
        if ($data === null) {
            $this->_logger->error(
                'Heidelpay - easyCredit Controller: Empty transaction data->jsonResponse. '
                . '(no data was stored in Response or controller called directly?)'
            );

            // display the customer-friendly message for the customer
            $this->messageManager->addErrorMessage(
                __("An unexpected error occurred. Please contact us to get further information.")
            );

            return $this->_redirect('checkout/cart/', ['_secure' => true]);
        }
    }
}
