<?php

namespace Heidelpay\Gateway\Block\Info;

/**
 * Heidelpay Santander Invoice Info Block Class
 *
 * @license    Use of this software requires acceptance of the License Agreement. See LICENSE file.
 * @copyright  Copyright © 2016-present Heidelberger Payment GmbH. All rights reserved.
 *
 * @link       https://dev.heidelpay.de/magento2
 * @author     Stephano Vogel
 *
 * @package    heidelpay\magento2\block\info\santanderinvoice
 */
class SantanderInvoice extends \Heidelpay\Gateway\Block\Info\AbstractBlock
{
    /**
     * @var string
     */
    protected $_template = 'Heidelpay_Gateway::info/santander_invoice.phtml';

    /**
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Heidelpay_Gateway::info/pdf/santander_invoice.phtml');
        return $this->toHtml();
    }
}
