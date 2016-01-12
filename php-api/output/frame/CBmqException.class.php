<?php
/***************************************************************************
 * 
 * Copyright (c) 2010 Baidu.com, Inc. All Rights Reserved
 * 
 **************************************************************************/
 
 
 
/**
 * @file CBmqException.class.php
 * @author zhang_rui(com@baidu.com)
 * @date 2010-7-12
 * @brief 
 *  
 **/

/**
 * Exception
 */
class BmqException extends Exception
{
    protected $_details;
    protected $_frame = null;
    
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param string $details Stomp server error details
     */
    public function __construct($message = null, $code = 0, $details = '', $eframe = null)
    {
        $this->_details = $details;
        $this->_frame = $eframe;
        
        parent::__construct($message, $code);
    }
    
    /**
     * server error details
     *
     * @return string
     */
    public function getDetails()
    {
        return $this->_details;
    }
    
    public function getFrame()
    {
        return $this->_frame;
    }
}



?>
