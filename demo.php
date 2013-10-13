#!/php -q
<?php
require 'class.echo.server.php';

$s = new EchoServer('localhost', 12345);
$s->run();
?>