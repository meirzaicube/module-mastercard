<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace OnTap\Tns\Model;

use OnTap\Tns\Api\SessionInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\GuestBillingAddressManagementInterface;

class SessionInformationManagement implements SessionInformationManagementInterface
{
    const CREATE_HOSTED_SESSION = 'create_session';

    /**
     * @var CommandPoolInterface
     */
    protected $commandPool;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var PaymentDataObjectFactory
     */
    protected $paymentDataObjectFactory;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var GuestBillingAddressManagementInterface
     */
    protected $billingAddressManagement;

    /**
     * SessionInformationManagement constructor.
     * @param CommandPoolInterface $commandPool
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     */
    public function __construct(
        CommandPoolInterface $commandPool,
        CartRepositoryInterface $quoteRepository,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        GuestBillingAddressManagementInterface $billingAddressManagement
    ) {
        $this->commandPool = $commandPool;
        $this->quoteRepository = $quoteRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->billingAddressManagement = $billingAddressManagement;
    }

    /**
     * {@inheritDoc}
     */
    public function createNewPaymentSession(
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        /* @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        $quote->setReservedOrderId(null);
        $quote->reserveOrderId();

        $this->commandPool
            ->get(static::CREATE_HOSTED_SESSION)
            ->execute([
                'payment' => $this->paymentDataObjectFactory->create($quote->getPayment())
            ]);

        $session = $quote->getPayment()->getAdditionalInformation('session');

        $quote->save();

        return [
            'id' => (string) $session['id'],
            'version' => (string) $session['version']
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createNewGuestPaymentSession(
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
//        if ($billingAddress) {
//            $billingAddress->setEmail($email);
//            $this->billingAddressManagement->assign($cartId, $billingAddress);
//        }
        $quoteIdMask = $this->quoteIdMaskFactory
            ->create()
            ->load($cartId, 'masked_id');

        return $this->createNewPaymentSession($quoteIdMask->getQuoteId(), $paymentMethod, $billingAddress);
    }
}