WebSockets
=========

WebSockets is a PHP implementation of Web Sockets. 

The code is inspired by https://code.google.com/p/phpwebsocket/ and is written in an object oriented design in order to help integration with any existing web application.

A couple of methods are also inspired from other developers on github (see the source code for details).

Version
-------

1.0

Demo
----

In order to use, simply copy all PHP files to a PHP web server. 

The HTML file can be run from any browser supporting Web Sockets.

To start the server simply navigate to the directory containing the PHP files and run the following command:

`php -q demo.php`

The demo shows a basic usage of web sockets which is an Echo Server, which basically echoes back whatever you send to it.

Once the server starts listening to clients you can open the HTML file in your preferred (and hopefully compatible) browser. In there you will find a simple text field which allows you to type messages to send to the server. Simply Hit ENTER to submit and the message will be echoed back to you.

Compatibility
-------------

Currently tested and fully compatible on Google Chrome 30.0, Mozilla Firefox 24.0 and is not yet compatible with IE.