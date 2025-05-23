<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Helper;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\Data\CreditmemoCommentInterface;
use Magento\Sales\Api\Data\InvoiceCommentInterface;
use Magento\Sales\Api\Data\ShipmentCommentInterface;

/**
 * Sales module base helper
 */
class SalesEntityCommentValidator extends AbstractHelper
{
    /**
     * UserContextInterface
     *
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @param Context $context
     * @param UserContextInterface $userContext
     */
    public function __construct(
        Context $context,
        UserContextInterface $userContext
    ) {
        $this->userContext = $userContext;
        parent::__construct(
            $context
        );
    }

    /**
     * Check whether sales entity comments are allowed to edit or not
     *
     * @param CreditmemoCommentInterface|InvoiceCommentInterface|ShipmentCommentInterface $salesEntityComment
     * @return bool
     */
    public function isEditCommentAllowed(
        $salesEntityComment
    ): bool {
        if (!empty($salesEntityComment->getId())) {
            if ($salesEntityComment->getData('user_id') != $this->userContext->getUserId() ||
                $salesEntityComment->getData('user_type') != $this->userContext->getUserType()) {
                return false;
            }
        }

        return true;
    }
}
