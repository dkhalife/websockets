<?php
require 'class.user.php';

/**
 * This class implements a Web Socket v13
 * according to the RFC6455
 *
 * @author: Dany Khalife (see below for other contributors)
 * @see	http://tools.ietf.org/html/rfc6455
 **/
abstract class WebSocket {
	// The connected sockets
	protected $sockets = array();
	// The sockets to listen for write events only
	protected $write = array();
	// The sockets to listen for exceptions
	protected $except = array();
	// The master socket
	protected $master = NULL;
	
	// The connected users 
	protected $users = array();
	// Debug mode
	protected $debug = TRUE;
	
	/**
	 * Constructor
	 *
	 * @param host: The host to bind the socket to
	 * @param port: The port to bind the socket to
	 **/
	public function __construct($host, $port){
		// Error handling
		error_reporting(E_ALL);

		// Connection configuration
		set_time_limit(0);
		ob_implicit_flush();

		// Create a socket
		$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)	or die("socket_option() failed");
		
		// Bind it to the address and port
		socket_bind($this->master, $host, $port) or die("socket_bind() failed");
		
		// Start listening for connections
		socket_listen($this->master, 20) or die("socket_listen() failed");
		
		$this->sockets[] = $this->master;
		
		// Everything is set, we are ready to start accepting connections
		$this->log("Server Started");
		$this->log("Master socket: ".$this->master);
		$this->log("Listening on : ".$host." port ".$port);
	}
	
	/**
	 * This method performs a handshake with a given user
	 *
	 * @param user: The user with which to perform the handshake
	 * @param buffer: The data buffer containing the headers received from that user
	 **/
	private function handshake($user, $buffer){
		if($this->debug)
			$this->log("Handshaking: ".$user->getSocket()." [".$user->getIp()."]");
			
		// Grab the key from the buffer
		// TODO: Should not use regex for performance!
		$key = null;
		if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $buffer, $match))
			$key=$match[1];
			
		// Calculate the accept key (RFC6455)
		$accept = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

		// Build the upgrade response
		$upgrade =  "HTTP/1.1 101 Switching Protocols\r\n" .
					"Upgrade: websocket\r\n" .
					"Connection: Upgrade\r\n" .
					"Sec-WebSocket-Accept: ". $accept . "\r\n" .
					"\r\n";

		// Send the frame
		$user->send($upgrade);
		// And mark it as paired
		$user->setPaired();
		
		return true;
	}

	/**
	 * @author Simon Samtleben @link https://github.com/lemmingzshadow
	 *
	 * @param string $data
	 * @return string Decoded data
	 */
	private function decode($data){
		$bytes = $data;
		$dataLength = '';
		$mask = '';
		$coded_data = '';
		$decodedData = '';
		$secondByte = sprintf('%08b', ord($bytes[1]));
		$masked = ($secondByte[0] == '1') ? true : false;
		$dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);

		if ($masked === true) {
			if ($dataLength === 126) {
				$mask = substr($bytes, 4, 4);
				$coded_data = substr($bytes, 8);
			} elseif ($dataLength === 127) {
				$mask = substr($bytes, 10, 4);
				$coded_data = substr($bytes, 14);
			} else {
				$mask = substr($bytes, 2, 4);
				$coded_data = substr($bytes, 6);
			}
			for ($i = 0; $i < strlen($coded_data); $i++) {
				$decodedData .= $coded_data[$i] ^ $mask[$i % 4];
			}
		} 
		else {
			if ($dataLength === 126) {
				$decodedData = substr($bytes, 4);
			} elseif ($dataLength === 127) {
				$decodedData = substr($bytes, 10);
			} else {
				$decodedData = substr($bytes, 2);
			}
		}

		return $decodedData;
	}

	/**
	 * @author Jeff Morgan @link https://github.com/jam1401
	 *
	 * Sends a packet of data to a client over the websocket.
	 *
	 * @param string $data A buffer containing the data received
	 * @param bool $binary Indicator true is frame is binary false if frame is utf8
	 *
	 * @return The encoded packet to send
	 */
	private function encode($data, $binary = false){
		$databuffer = array();
		$sendlength = strlen($data);
		$rawBytesSend = $sendlength + 2;
		$packet;

		if ($sendlength > 65535) {
			// 64bit
			array_pad($databuffer, 10, 0);
			$databuffer[1] = 127;
			$lo = $sendlength | 0;
			$hi = ($sendlength - $lo) / 4294967296;

			$databuffer[2] = ($hi >> 24) & 255;
			$databuffer[3] = ($hi >> 16) & 255;
			$databuffer[4] = ($hi >> 8) & 255;
			$databuffer[5] = $hi & 255;

			$databuffer[6] = ($lo >> 24) & 255;
			$databuffer[7] = ($lo >> 16) & 255;
			$databuffer[8] = ($lo >> 8) & 255;
			$databuffer[9] = $lo & 255;

			$rawBytesSend += 8;
		} else if ($sendlength > 125) {
			// 16 bit
			array_pad($databuffer, 4, 0);
			$databuffer[1] = 126;
			$databuffer[2] = ($sendlength >> 8) & 255;
			$databuffer[3] = $sendlength & 255;

			$rawBytesSend += 2;
		} else {
			array_pad($databuffer, 2, 0);
			$databuffer[1] = $sendlength;
		}

		// Set op and find
		$databuffer[0] = (128 + ($binary ? 2 : 1));
		$packet = pack('c', $databuffer[0]);
		// write out the packet header
		for ($i = 1; $i < count($databuffer); $i++) {
			//$packet .= $databuffer[$i];
			$packet .= pack('c', $databuffer[$i]);
		}

		// write out the packet data
		for ($i = 0; $i < $sendlength; $i++) {
			$packet .= $data[$i];
		}

		return $packet;
	}

	/**
	 * This method processes the data sent by a user
	 *
	 * @param user: The user who initiated the connection
	 * @param data: The data sent by the user
	 **/
	protected abstract function process($user, $data);
	
	/**
	 * This method logs debugging messages
	 *
	 * @param msg: The message to log
	 **/
	final protected function log($msg){
		echo date('[Y-m-d H:i:s] ').$msg."\r\n";
	}
	
	/**
	 * This method sends data back to the user
	 *
	 * @param user: The user to whom the data will be sent
	 * @param data: The data to be sent
	 **/
	final protected function send($user, $data){
		$user->send($this->encode($data));
	}
	
	/**
	 * This method looks up a user by a socket
	 *
	 * @param socket: The socket for which to find the user
	 *
	 * @return The corresponding user or NULL if not found
	 **/
	protected function getUserBySocket($socket){
		foreach($this->users as $user){
			if($user->getSocket() == $socket){
				return $user;
			}
		}
		
		return null;
	}
	
	/**
	 * This method starts the WebSocket server
	 **/
	final public function run(){
		while(true){
			// Watch the sockets for changes
			socket_select($this->sockets, $this->write, $this->except, NULL);
			
			// Loop throught sockets where data has been received
			foreach($this->sockets as $socket){
				if($socket == $this->master){
					// If this is the master socket, a client is trying to connect
					$client=socket_accept($this->master);
					
					if($client < 0){
						$this->log("Accept failed");
						continue;
					}
					else{
						// Connection successful
						$this->connect($client);
					}
				}
				else{
					// Incoming data from an already connected client
					$bytes = @socket_recv($socket, $buffer, 2048, 0);
					
					// No bytes received, socket was closed by client
					if($bytes==0){
						$this->disconnect($socket);
					}
					else{
						// Figure out who sent this request
						$user = $this->getUserBySocket($socket);
						
						// If this is the first connection, perform handshake validation
						if(!$user->isPaired()){
							$this->handshake($user, $buffer);
						}
						else{
							// Process incoming data
							$this->process($user, $this->decode($buffer));
						}
					}
				}
			}
		}
	}
	
	/**
	 * This method is invoked when a new user connects to the server
	 *
	 * @param socket: The socket corresponding to the user
	 **/
	protected function connect($socket){
		// Create a new user for this socket
		$user = new User($socket);
		
		// Add the user and socket to the arrays
		array_push($this->users, $user);
		array_push($this->sockets, $socket);
		
		if($this->debug)
			$this->log("User connected: ".$socket." [".$user->getIp()."]");
	}

	/**
	 * This method is invoked when a user disconnects from the server
	 *
	 * @param socket: The socket that was disconnected
	 **/
	protected function disconnect($socket){
		// Look for the corresponding user
		$found=null;
		for($i=0, $n=count($this->users); $i<$n; ++$i){
			if($this->users[$i]->getSocket() == $socket){
				if($this->debug)
					$this->log("User disconnected: ".$socket." [".$this->users[$i]->getIp()."]");
				
				$found=$i;
				break;
			}
		}
		
		// Remove user from array
		if(!is_null($found)){
			array_splice($this->users, $found, 1);
		}
		
		// Look for socket
		$index = array_search($socket, $this->sockets);
		
		// Terminate connection
		socket_close($socket);
		
		// Remove socket
		if($index >= 0){
			array_splice($this->sockets, $index, 1);
		}
	}
}