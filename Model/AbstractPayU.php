<?php
/**
 * Created by PhpStorm.
 * User: kenny
 * Date: 9/13/17
 * Time: 10:38 AM
 */

namespace PayU\EasyPlus\Model;

use Magento\Payment\Model\Method\AbstractMethod;

abstract class AbstractPayU extends AbstractMethod
{
    const REQUEST_TYPE_PAYMENT = 'PAYMENT';

    const REQUEST_TYPE_RESERVE = 'RESERVE';

    const REQUEST_TYPE_CANCEL = 'RESERVE_CANCEL';

    const REQUEST_TYPE_CREDIT = 'CREDIT';

    const REQUEST_TYPE_FINALIZE = 'FINALIZE';

    const TRANS_STATE_NEW = 'PAYMENT';

    const TRANS_STATE_PROCESSING = 'PROCESSING';

    const TRANS_STATE_SUCCESSFUL = 'SUCCESSFUL';

    const TRANS_STATE_FAILED = 'FAILED';

    const TRANS_STATE_TIMEOUT = 'TIMEOUT';

    const TRANS_STATE_EXPIRED = 'EXPIRED';

    const TRANS_STATE_AWAITING_PAYMENT = 'AWAITING_PAYMENT';

    /**
     * Transaction fraud state key
     */
    const TRANSACTION_FRAUD_STATE_KEY = 'is_transaction_fraud';

    /**
     * Real transaction id key
     */
    const REAL_TRANSACTION_ID_KEY = 'real_transaction_id';
}