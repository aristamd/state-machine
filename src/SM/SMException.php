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
     * @param string $message  original     Exception message
     * @param array  $additionalLogInfo     Additional log info
     */
    public function __construct( $message=null, $additionalLogInfo )
    {
        parent::__construct( $message, 0, null );
        $this->additionalLogInfo = $additionalLogInfo;
    }
}
