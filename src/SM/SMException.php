<?php

/*
 * This file is part of the StateMachine package.
 *
 * (c) Alexandre Bacco
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SM;

class SMException extends \Exception
{

    /**
     * @var Array  This array will store additional info to be sent as part of the error log on kibana
     */
    public $additionalLogInfo = [];


    /**
     * SMException constructor.
     *
     * @param string $message  original Exception message
     * @param string $from     Previous status of the object referral/econsult
     * @param string $to       Current status of the object
     * @param mix $object      This can be an econsult or referral object
     */
    public function __construct( $message=null, $from=null, $to=null, $object=null )
    {
        parent::__construct( $message, 0, null );
        $this->additionalLogInfo['from_status'] = $from;
        $this->additionalLogInfo['to_status'] = $to;
        $this->additionalLogInfo['object'] = $object;
    }
}
