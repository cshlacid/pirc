<?php
set_time_limit(0);

/**
 * IRC client daemon
 *
 * @author	peris <cshlacid@gmail.com>
 */
abstract class PIRC {
	private $sock;

	protected $debug = false;

	public $server;
	public $port = 6667;
	public $channel;
	public $channelPass;
	public $nick;
	public $nick2;
	public $currentNick;
	public $userName;
	public $charset = 'UTF-8';

	/**
	 *
	 * @return	void
	 */
	public function __construct() {
	}

	/**
	 *
	 * @return	void
	 */
	public function __destruct() {
		if(is_resource($this->sock)) {
			$this->messageWrite("QUIT :PIRC");
			socket_close($this->sock);
		}
	}


	/**
	 *
	 * @param	string	$server	Host name or IP
	 * @return	void
	 */
	public function setServer($server) {
		$this->server = $server;
	}

	/**
	 *
	 * @param	int	$port
	 * @return	void
	 */
	public function setPort($port=6667) {
		$this->port = $port;
	}

	/**
	 *
	 * @param	string	$channel
	 * @return	void
	 */
	public function setChannel($channel) {
		if(substr($channel, 0, 1) != '#') {
			$channel = '#'.$channel;
		}

		$this->channel = $channel;
	}

	/**
	 *
	 * @param	string	$channelPass	channel password
	 * @return	void
	 */
	public function setChannelPass($channelPass) {
		$this->channelPass = $channelPass;
	}

	/**
	 *
	 * @param	string	$nick
	 * @param	string	$nick2	(닉네임 중복 시 사용)
	 * @return	void
	 */
	public function setNick($nick, $nick2 = null) {
		$this->nick = $nick;

		if(!is_null($nick2)) {
			$this->nick2 = $nick2;
		}
	}

	/**
	 *
	 * @param	string	$userName
	 * @return	void
	 */
	public function setUserName($userName) {
		$this->userName = $userName;
	}

	/**
	 *
	 * @param	string	$charset
	 * @return	void
	 */
	public function setCharset($charset) {
		$this->charset = $charset;
	}

	/**
	 *
	 * @param	bool	$debug
	 * @return	void
	 */
	public function setDebug($debug = true) {
		$this->debug = $debug;
	}

	/**
	 *
	 * @param	string	$level
	 * @param	string	$msg
	 * @return	void
	 */
	public function log($level, $msg) {
		$level	= escapeshellarg($level);
		$msg	= '['.date('Y-m-d H:i:s').'] '.escapeshellarg($msg);

		exec('echo '.$msg.' >> /home/rsef/www/irc/'.$level.'/'.date('Ymd').'.log');
	}

	/**
	 * php error handler
	 *
	 * @param	int		$errno
	 * @param	string	$errstr
	 * @param	string	$errfile
	 * @param	int		$errline
	 * @return	void
	 */
	public function errorHandler($errno, $errstr, $errfile, $errline) {
		$this->log('debug', 'Error : '.$errstr.'['.$errno.'] '.$errfile.':'.$errline);
	}


	/**
	 *
	 * @return	resource
	 */
	public function getConnect() {
		if(!is_resource($this->sock)) {
			$this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die('Socket create error');
			socket_connect($this->sock, $this->server, $this->port) or die('Server connect error');
			socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>0, 'usec'=>100000));

			$this->currentNick = $this->nick;
			$this->messageWrite("NICK ".$this->nick);
			$this->messageWrite("USER ".$this->userName." localhost ".$this->userName." :test test");
		}

		return $this->sock;
	}

	/**
	 *
	 * @return	string
	 */
	public function messageRead() {
		return socket_read($this->sock, 8);
	}

	/**
	 *
	 * @param	string	$message
	 * @return	void
	 */
	public function messageWrite($message) {
		if($this->debug) {
			$this->log('debug', $message);
		}

		socket_write($this->sock, $message."\r\n");

		if(substr($message, 0, 4) != 'PONG') {
			$this->log('log', '<'.$this->userName.'> '.$message);
		}
	}


	/**
	 * $read sample
	 * - PING :1552771049
	 * - :Peris!~peris@rsef.net JOIN #channel
	 * - :Peris!~peris@rsef.net PRIVMSG #channel :message
	 *
	 * @param	string	$read
	 * @return	void
	 */
	private function messageParse($read) {
		$message			= array();

		$messageExp			= explode(':', $read, 3);
		$message['ping']	= @trim($messageExp[0]);
		$message['info']	= @trim($messageExp[1]);
		$message['message']	= @rtrim($messageExp[2]);

		$messageInfoExp		= explode(' ', $message['info']);
		$message['user']	= @trim($messageInfoExp[0]);
		$message['type']	= @trim($messageInfoExp[1]);
		$message['chan']	= @trim($messageInfoExp[2]);
		$message['mode']	= @trim($messageInfoExp[3]);
		$message['target']	= @trim($messageInfoExp[4]);

		$messageUserExp			= explode('!', $message['user']);
		$message['userNick']	= @trim($messageUserExp[0]);
		$message['userHost']	= @trim($messageUserExp[1]);

		switch($message['ping']) {
			case 'PING' :
				$this->messageParsePing($message);
				break;

			case 'ERROR' :
				$this->log('debug', $message);

				if($message['user'] == 'Closing' && $message['type'] == 'Link') {
					exit;
				}
				break;
		}

		$messageType = ucfirst(strtolower($message['type']));
		$method = 'messageParse'.$messageType;
		if(strlen($messageType) > 0 && method_exists($this, $method)) {
			$this->$method($message);

			$this->log('log', '<'.$message['userNick'].'> '.$message['message']);
		}
	}

	/**
	 * PING을 받은 경우 PONG 전송(접속 유지에 사용됨)
	 *
	 * @param	string	$message
	 * @return	void
	 */
	protected function messageParsePing($message) {
		$this->messageWrite('PONG '.$message['user']);
	}

	/**
	 * 433 type 처리(닉네임 중복 시 처리)
	 *
	 * @param	string	$message
	 * @return	void
	 */
	protected function messageParse433($message) {
		$this->currentNick = $this->nick2;
		$this->messageWrite('NICK '.$this->nick2);
	}

	/**
	 * 시간에 따른 처리가 필요한 경우 구현
	 *
	 * @return	void
	 */
	abstract public function timer();


	/**
	 * 무한루프를 돌며 socket read/write 처리
	 *
	 * @return	void
	 */
	final public function run() {
		$memory = '';

		$this->getConnect();

		while(1) {
			$memory .= $this->messageRead();

			$this->timer();

			// 개행문자가 없으면 메세지를 더 받아야 함
			if(($pos = strpos($memory, "\n")) === FALSE) {
				continue;
			}

			// 개행문자를 기준으로 앞부분은 파싱에 사용하고 뒷부분은 다음 메세지에 사용
			$read = substr($memory, 0, $pos);
			$memory = substr($memory, $pos + 1);

			if($this->debug) {
				$this->log('debug', $read);
			}

			$this->messageParse($read);
		}
	}
}
