<?php
require_once 'PIRC.class.php';

/**
 * IRC bot
 *
 * @author	peris <cshlacid@gmail.com>
 */
class PIRCBot extends PIRC {
	/**
	 * 시간에 따른 처리가 필요한 경우 구현
	 *
	 * @return	void
	 */
	public function timer() {
	}

	/**
	 * 일반 메세지 처리
	 *
	 * @param	string	$message
	 * @return	void
	 */
	protected function messageParsePrivmsg($message) {
		$command = explode(' ', $message['message'], 2);

		switch(trim($command[0])) {
			case '@세계인구' :
				$this->processWorldPopulation($message);
				break;
		}
	}

	/**
	 *
	 * @param	string	$message
	 * @return	void
	 */
	protected function processWorldPopulation($message) {
		$XMLParser = new XMLParser;
		if($XMLParser->parse('http://www.census.gov/main/www/rss/popclocks.xml') === false) {
			return false;
		}

		$dom = new DOM($XMLParser->document);
		$item = $dom->rss->channel->item(1);
		$title = trim(str_replace(array("\r", "\n"), " ", $item->title));

		if(!$title) {
			$this->messageWrite("PRIVMSG ".$message['chan']." :Error");
			return;
		}

		$this->messageWrite("PRIVMSG ".$message['chan']." :".$title);

		unset($dom, $item);
	}
}
