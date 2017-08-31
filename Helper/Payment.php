<?php

namespace Heidelpay\Gateway\Helper;

use Heidelpay\CustomerMessages\CustomerMessage;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * Heidelpay payment helper
 *
 * The payment helper is a collection of function to prepare an send
 *
 * @license    Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 * @copyright  Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link       https://dev.heidelpay.de/magento
 *
 * @author     Jens Richter
 *
 * @package    Heidelpay
 * @subpackage Magento2
 * @category   Magento2
 */
class Payment extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_invoiceOrderEmail = true;
    protected $_debug = false;

    /**
     * @var ZendClientFactory
     */
    protected $httpClientFactory;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $localeResolver;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * @param ZendClientFactory                        $httpClientFactory
     * @param Logger                                   $logger
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Framework\Locale\Resolver       $localeResolver
     * @param \Magento\Store\Model\App\Emulation       $appEmulation
     * @param \Magento\Catalog\Helper\ImageFactory     $imageHelperFactory
     */
    public function __construct(
        ZendClientFactory $httpClientFactory,
        Logger $logger,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Catalog\Helper\ImageFactory $imageHelperFactory
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->log = $logger;
        $this->transactionFactory = $transactionFactory;
        $this->localeResolver = $localeResolver;
        $this->appEmulation = $appEmulation;
        $this->imageHelperFactory = $imageHelperFactory;
    }

    public function splitPaymentCode($PAYMENT_CODE)
    {
        return preg_split('/\./', $PAYMENT_CODE);
    }

    /**
     * @param array                      $data
     * @param \Magento\Sales\Model\Order $order
     * @param bool                       $message
     */
    public function mapStatus($data, $order, $message = false)
    {
        $paymentCode = $this->splitPaymentCode($data['PAYMENT_CODE']);

        $message = (!empty($message))
            ? $message
            : 'Short-ID: ' . $data['IDENTIFICATION_SHORTID'] . ' (' . $data['PROCESSING_RETURN'] . ')';

        $message = __('heidelpay - ' . $message);

        $quoteID = ($order->getLastQuoteId() === false)
            ? $order->getQuoteId()
            : $order->getLastQuoteId(); // last_quote_id workaround for trusted shop buyerprotection

        // If an order has been canceled, closed or complete -> do not change order status.
        if ($order->getStatus() == Order::STATE_CANCELED
            || $order->getStatus() == Order::STATE_CLOSED
            || $order->getStatus() == Order::STATE_COMPLETE
        ) {
            // you can use this event for example to get a notification when a canceled order has been paid
            return;
        }

        if ($data['PROCESSING_RESULT'] == 'NOK') {
            $order->getPayment()->getMethodInstance()->cancelledTransactionProcessing($order, $message);
        } elseif ($this->isProcessing($paymentCode[1], $data)) {
            $order->getPayment()->getMethodInstance()->processingTransactionProcessing($data, $order, $message);
        } else {
            $order->getPayment()->getMethodInstance()->pendingTransactionProcessing($data, $order, $message);
        }
    }

    /**
     * function to format amount
     *
     * @param mixed $number
     *
     * @return string
     */
    public function format($number)
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * helper to generate customer payment error messages
     *
     * @param string|null $errorCode
     *
     * @return string
     */
    public function handleError($errorCode = null)
    {
        $customerMessage = new CustomerMessage($this->localeResolver->getLocale());
        return $customerMessage->getMessage($errorCode);
    }

    /**
     * Checks if the currency in the data set matches the currency in the order.
     *
     * @param Order $order
     * @param array $data
     *
     * @return bool
     */
    public function isMatchingAmount(Order $order, $data)
    {
        if (!isset($data['PRESENTATION_AMOUNT'])) {
            return false;
        }

        return $this->format($order->getGrandTotal()) == $data['PRESENTATION_AMOUNT'];
    }

    /**
     * Checks if the currency in the data set matches the currency in the order.
     *
     * @param Order $order
     * @param array $data
     *
     * @return bool
     */
    public function isMatchingCurrency(Order $order, $data)
    {
        if (!isset($data['PRESENTATION_CURRENCY'])) {
            return false;
        }

        return $order->getOrderCurrencyCode() == $data['PRESENTATION_CURRENCY'];
    }

    /**
     * Checks if the data indicates a processing payment transaction.
     *
     * @param string $paymentCode
     * @param array  $data
     *
     * @return bool
     */
    public function isProcessing($paymentCode, $data)
    {
        if (!isset($data['PROCESSING_RESULT']) && !isset($data['PROCESSING_STATUS_CODE'])) {
            return false;
        }

        return in_array($paymentCode, ['CP', 'DB', 'FI', 'RC'])
            && $data['PROCESSING_RESULT'] == 'ACK'
            && $data['PROCESSING_STATUS_CODE'] != 80;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function isPreAuthorization(array $data)
    {
        if (!isset($data['PAYMENT_CODE'])) {
            return false;
        }

        $paymentCode = $this->splitPaymentCode($data['PAYMENT_CODE']);

        if ($paymentCode[1] == 'PA') {
            return true;
        }

        return false;
    }

    /**
     * Determines if the payment code and type are for a receipt.
     *
     * @param string $paymentMethod
     * @param string $paymentType
     *
     * @return bool
     */
    public function isReceiptAble($paymentMethod, $paymentType)
    {
        if ($paymentType !== 'RC') {
            return false;
        }

        switch ($paymentMethod) {
            case 'DD':
            case 'PP':
            case 'IV':
            case 'OT':
            case 'PC':
            case 'MP':
            case 'HP':
                $return = true;
                break;

            default:
                $return = false;
                break;
        }

        return $return;
    }

    /**
     * Checks if the given paymentcode is viable for a refund transaction.
     *
     * @param string $paymentcode
     *
     * @return bool
     */
    public function isRefundable($paymentcode)
    {
        if ($paymentcode === 'DB' || $paymentcode === 'CP' || $paymentcode === 'RC') {
            return true;
        }

        return false;
    }

    /**
     * Saves a transaction by the given invoice.
     *
     * @param Invoice $invoice
     */
    public function saveTransaction(Invoice $invoice)
    {
        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
    }

    /**
     * Converts a Quote to a heidelpay PHP Basket Api Request instance.
     *
     * @param Quote $quote
     *
     * @return \Heidelpay\PhpBasketApi\Request|null
     */
    public function convertQuoteToBasket(Quote $quote)
    {
        // if no (valid) quote is supplied, we can't convert it to a heidelpay Basket object.
        if ($quote === null || $quote->isEmpty()) {
            return null;
        }

        // we emulate that we are in the frontend to get frontend product images.
        $this->appEmulation->startEnvironmentEmulation(
            $quote->getStoreId(),
            \Magento\Framework\App\Area::AREA_FRONTEND,
            true
        );

        // initialize the basket request
        $basketRequest = new \Heidelpay\PhpBasketApi\Request();

        $basketRequest->getBasket()
            ->setCurrencyCode($quote->getQuoteCurrencyCode())
            ->setAmountTotalNet((int) ($quote->getGrandTotal() * 100))
            ->setAmountTotalVat((int) ($quote->getShippingAddress()->getTaxAmount() * 100))
            ->setAmountTotalDiscount((int) ($quote->getShippingAddress()->getDiscountAmount() * 100))
            ->setBasketReferenceId(sprintf('M2-S%dQ%d-%s', $quote->getStoreId(), $quote->getId(), date('YmdHis')));

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            $basketItem = new \Heidelpay\PhpBasketApi\Object\BasketItem();

            $basketItem->setQuantity($item->getQty())
                ->setVat((int) ($item->getTaxPercent() * 100))
                ->setAmountPerUnit((int) ($item->getPrice() * 100))
                ->setAmountVat((int) ($item->getTaxAmount() * 100))
                ->setAmountNet((int) ($item->getRowTotal() * 100))
                ->setAmountGross((int) ($item->getRowTotalInclTax() * 100))
                ->setAmountDiscount((int) ($item->getDiscountAmount() * 100))
                ->setTitle($item->getName())
                ->setDescription($item->getDescription())
                ->setArticleId($item->getSku())
                ->setImageUrl(
                    $this->imageHelperFactory->create()->init($item->getProduct(), 'category_page_list')->getUrl()
                )
                ->setBasketItemReferenceId(
                    sprintf('M2-S%dQ%d-%s-x%d', $quote->getStoreId(), $quote->getId(), $item->getSku(), $item->getQty())
                );

            $basketRequest->getBasket()->addBasketItem($basketItem);
        }

        // stop the frontend environment emulation
        $this->appEmulation->stopEnvironmentEmulation();

        return $basketRequest;
    }
}
