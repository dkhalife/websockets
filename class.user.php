<?
/**
 * This class represents a user
 *
 * @author: Dany Khalife
 **/
class User {
	// The user's unique identifier
	private $id = null;
	// Is the user paired (did a handshake succeed?)
	private $paired = false;
	// The socket connected to that user
	private $socket = null;
	// The user's ip address
	private $ip = null;
	
	/**
	 * Constructor
	 *
	 * @param socket: The socket the user is connected to
	 **/
	public function __construct($socket){
		// Assign this user an id
		$this->id = uniqid();
		$this->socket = $socket;
		
		// Find out his ip address
		socket_getpeername($socket, $this->ip);
	}
	
	/**
	 * This method sends frame to the user
	 *
	 * @param frame: The frame to send to the user
	 **/
	public function send($frame){
		socket_write($this->socket, $frame, strlen($frame));
	}
	
	/**
	 * Getter for the paired field
	 *
	 * @return True if the user is paired
	 **/
	public function isPaired(){
		return $this->paired;
	}
	
	/**
	 * Setter for the paired field
	 **/
	public function setPaired(){
		$this->paired = true;
	}
	
	/**
	 * Getter for the socket field
	 *
	 * @return The socket associated with this user
	 **/
	public function getSocket(){
		return $this->socket;
	}
	
	/**
	 * Getter for the ip field
	 *
	 *
	  @return The ip address for this user
	 **/
	public function getIp(){
		return $this->ip;
	}
}