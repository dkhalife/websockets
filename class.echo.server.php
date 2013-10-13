<?php
require 'class.websocket.php';

/**
 * This class represents a demo server that echoes all messages it receives
 *
 * @author: Dany Khalife
 **/
class EchoServer extends WebSocket {
	/**
	 * This method implements the process abstract method 
	 * that treats each message received
	 **/
	protected function process($user, $data){
		// Send the data back to the user
		$this->send($user, $data);
		// Also output it on the terminal
		$this->log('Received data: '.$data);
	}
}