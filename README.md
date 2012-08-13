Uncrashable PHP Shell
=====================

Offers a readline interactive shell for PHP that can handle Parser Errors as well as Fatal Errors.
You can execute what every command you type in without loosing the current variable scope.

Example session
---------------

    psh > $msg="Hello World\n";
    psh > this_fails();
    PHP Fatal error:  Call to undefined function this_fails() in /.../psh.php(55) : eval()'d code on line 1
    psh > this_is_wrong.
    PHP Parse error:  syntax error, unexpected $end in /.../psh.php(55) : eval()'d code on line 2
    psh > echo "$msg\n";
    Hello World
    psh > exit();

