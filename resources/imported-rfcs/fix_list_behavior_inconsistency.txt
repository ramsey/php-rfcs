====== PHP RFC: Fix list() behavior inconsistency ======
  * Version: 1.0
  * Date: 2014-09-11
  * Author: Dmitry Stogov, dmitry@zend.com
  * Status: Implemented (in PHP 7)
  * First Published at: http://wiki.php.net/rfc/fix_list_behavior_inconsistency

===== Introduction =====

According to [[http://php.net/manual/en/function.list.php|PHP documentation]] list() construct doesn't work with strings.
However in some cases it works.

<code>
$ php -r 'list($a,$b) = "aa";var_dump($a,$b);'
NULL
NULL
$ php -r '$a[0]="ab"; list($a,$b) = $a[0]; var_dump($a,$b);'
string(1) "a"
string(1) "b"
</code>

This behavior caused by implementation feature and wasn't made on purpose.

===== Proposal =====

Make list() behave with strings in consistent way. There are two options:

==== Disable string handling in all cases ====

This will disable undocumented feature and it may break some existing PHP code.

==== Enable string handling in all cases ====

This will make the following code work.

<code>
list($a,$b) = "str";
</code>

Instead of assignment NULL into $a and $b, it'll assign 's' and 't' characters.
However, it also may break some existing PHP code.

===== Backward Incompatible Changes =====

Both options may affect existing PHP code.

===== Proposed PHP Version(s) =====

PHP7

===== Vote =====

This project requires a 2/3 majority, between first and second or third options.

Voting started on 2014-09-25 and ends 2014-10-02.

<doodle title="Fix list() behavior inconsistency?" auth="dmitry" voteType="single" closed="true">
   * don't fix
   * disable string handling in all cases
   * enable string handling in all cases
</doodle>

===== Implementation =====

Support for strings has been removed for all cases. Support for ''ArrayAccess'' has been added for all cases (previously it was not supported for temporary variables).

https://github.com/php/php-src/commit/7c7b9184b1fdf7add1715079f22241bc1185fcb0