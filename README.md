# pirc
PHP IRC Client

# Example

$irc = new PIRC;
set_error_handler(array($irc, 'errorHandler'));

$irc->setServer('holywar.hanirc.org');
$irc->setPort('6666');
$irc->setChannel('test2');
$irc->setCharset('UTF-8');
// $irc->setDebug(true);
$irc->setNick('peris-bot');
$irc->setUserName('ircbot');

$irc->run();