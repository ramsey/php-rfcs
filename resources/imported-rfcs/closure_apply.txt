====== PHP RFC: Closure::call ======
  * Version: 0.3
  * Date: 2014-07-29, put to internals 2014-08-03, latest 2014-08-19
  * Author: Andrea Faulds, ajf@ajf.me
  * Status: Accepted
  * First Published at: http://wiki.php.net/rfc/closure_apply

===== Introduction =====

PHP has had Closures since 5.3, and since 5.4 has had ''Closure::bind'' (static method) and ''Closure::bindTo'' (method) to allow creating new closures that have ''$this'' bound to a specific method. However, it has not been possible to bind at call-time, and you must instead create a temporary new closure, making calling bound to multiple objects cumbersome and inefficient (at least two statements are needed, and a new closure must be created and immediately disposed of for each).

===== Proposal =====

A new method is added to ''Closure'', with the following signature:

<code php>
mixed Closure::call(object $to[, mixed ...$parameters])
</code>

It calls the closure with the given parameters and returns the result, with ''$this'' bound to the given object ''$to'', using the scope of the given object. Like the ''bind''(''To'') methods, a static class cannot be bound (using ''->call'' will fail).

It can be used like so:

<code php>
$foo = new StdClass;
$foo->bar = 3;
$foobar = function ($qux) { var_dump($this->bar + $qux); };
$foobar->call($foo, 4); // prints int(7)
</code>

The ''->call'' method, unlike ''bind''(''To''), does not take a scope parameter. Instead, it will always use the class of the object as its scope. Thus:

<code php>
class Foo { private $x = 3; }
$foo = new Foo;
$foobar = function () { var_dump($this->x); };
$foobar->call($foo); // prints int(3)
</code>

''call'' would be useful in many cases where ''bindTo'' is used (e.g. [[https://github.com/search?l=php&p=34&q=bindTo&ref=cmdform&type=Code|search of GitHub for bindTo]]). A search on GitHub reveals [[https://github.com/search?q=bindTo+call_user_func&type=Code&ref=searchresults|many using bindTo and immediately calling with call_user_func]], which would now not be necessary as they could just use ''call''.

===== Performance =====

While not the sole benefit of this RFC, it can provide a performance improvement in some applications.

We use two test scripts, ''a.php'' using bindTo and ''b.php'' using call.

<file php a.php>
$a = function () {
    return $this->x;
};
class FooBar {
    private $x = 3;
}
$foobar = new FooBar;
for ($i = 0; $i < 1000000; $i++) {
    $x = $a->bindTo($foobar, "FooBar");
    $x();
}
</file>

<file php b.php>
$a = function () {
    return $this->x;
};
class FooBar {
    private $x = 3;
}
$foobar = new FooBar;
for ($i = 0; $i < 1000000; $i++) {
    $a->call($foobar);
}
</file>

''b.php'' shows a 2.18x improvement over ''a.php'':

<code>
andreas-air:php-src ajf$ time sapi/cli/php a.php

real	0m1.877s
user	0m1.835s
sys	0m0.025s

andreas-air:php-src ajf$ time sapi/cli/php b.php

real	0m0.859s
user	0m0.826s
sys	0m0.018s
</code>

===== Backward Incompatible Changes and RFC Impact =====

This has no effect on backwards compatibility.

===== Proposed PHP Version(s) =====

This is proposed for the next version of PHP, either the next 5.x or PHP NEXT, whichever comes sooner. The patch is based on master, intended for PHP 7.

===== Future Scope =====

Partial application (where a new closure is returned that pre-fills the first X arguments) is a possibly worthwhile (though more difficult to implement) addition.

===== Vote =====

This is not a language change, so a straight 50%+1 Yes/No vote can be held.

Voting started 2014-08-17 but was cancelled the same day due to the removal of unbound scoped closures.

Voting started again on 2014-08-20 and ended 2014-08-27.

<doodle title="Closure::apply() (Approve RFC and merge into master?)" auth="ajf" voteType="single" closed="true">
   * Yes
   * No
</doodle>

===== Patches and Tests =====

A branch which implements this (with a test) based on the current master can be found here: https://github.com/TazeTSchnitzel/php-src/tree/Closure_apply

There is a pull request for review purposes here: https://github.com/php/php-src/pull/775

===== References =====

  * My [[rfc:function_referencing|Function Referencing as Closures]] RFC has this RFC as a prerequisite

===== Changelog =====

  * v0.3 - Removed unbound scoped closures, made ''->call'' use class of object as its scope
  * v0.2.1 - Added performance section
  * v0.2 - ''Closure::apply'' renamed to ''Closure::call'' for consistency with JavaScript (former takes an array in JS à la ''call_user_func_array'', latter bare parameters)
  * v0.1 - Initial version