====== PHP RFC: Allow NULL ======

  * Version: 1.2
  * Voting Start: ?
  * Voting End: ?
  * RFC Started: 2021-12-23
  * RFC Updated: 2021-12-31
  * Author: Craig Francis, craig#at#craigfrancis.co.uk
  * Status: Draft
  * First Published at: https://wiki.php.net/rfc/allow_null
  * GitHub Repo: https://github.com/craigfrancis/php-allow-null-rfc
  * Implementation: ?

===== Introduction =====

PHP 8.1 introduced "Deprecate passing null to non-nullable arguments of internal functions" ([[https://externals.io/message/112327|short discussion]]), which is making it difficult (time consuming) for developers to upgrade.

In PHP //NULL// is often used to represent something; e.g. when a //GET/////POST/////COOKIE// variable is undefined:

<code php>
$name = ($_POST['name'] ?? NULL);

$name = $request->input('name'); // Laravel
$name = $request->get('name'); // Symfony
$name = $this->request->getQuery('name'); // CakePHP
$name = $request->getGet('name'); // CodeIgniter
</code>

And //NULL// can be returned from functions, e.g.

  * //array_pop()//
  * //filter_input()//
  * //mysqli_fetch_row()//
  * //error_get_last()//
  * //json_decode()//

This makes it common for //NULL// to be passed to many internal functions, e.g.

<code php>
trim($name);
strtoupper($name);
strlen($name);
urlencode($name);
htmlspecialchars($name);
hash('sha256', $name);
preg_match('/^[a-z]/', $name);
setcookie('name', $name);
socket_write($socket, $name);
xmlwriter_text($writer, $name);
</code>

Where //NULL// has the advantage of falling back to being treated like an empty string.

Developers also use //NULL// to skip certain parameters, e.g. //$additional_headers// in //mail()//.

Currently the deprecation notices only affect those using PHP 8.1 with //E_DEPRECATED//, but it implies everyone will need to modify their code to avoid **Fatal Errors** in PHP 9.0.

Developers using //strict_types=1// may find some value in this, but it's excessive for everyone else.

And while individual changes are easy, there are many of them, are difficult to find, and often pointless, e.g.

  * urlencode(strval($name));
  * urlencode((string) $name);
  * urlencode($name ?? '');

To find these issues, developers need to either - use these deprecation notices, or use very strict Static Analysis (one that can determine when a variable can be //NULL//; e.g. Psalm at [[https://psalm.dev/docs/running_psalm/error_levels/|level 3]], with no baseline).

===== Proposal =====

Update **some** internal function parameters to accept (or be tolerant to) //NULL//.

This needs to be done before the eventual end of the deprecation period, and //TypeError// exceptions are thrown - which would create an unnecessary burden for developers to upgrade.

While this is in Draft, the [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-change.md|list of functions are hosted on GitHub]].

Only the parameters in **bold** would be changed.

[[https://github.com/craigfrancis/php-allow-null-rfc/issues|Suggestions]] and [[https://github.com/craigfrancis/php-allow-null-rfc/pulls|pull requests]] welcome.

There is also a [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-maybe.md|Maybe List]], where the more questionable arguments end with a "!". For example, //strrpos()// accepting an empty string for //$needle// is wired in itself, and //sodium_crypto_box_open()// should never receive a blank //$ciphertext//. And there is an [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-other.md|Other List]], which can be ignored.

===== Decision Process =====

Does //NULL// for this parameter justify a Fatal Error? e.g.

  - //preg_match()// should **deprecate** //NULL// for //$pattern// ("empty regular expression" warning).
  - //preg_match()// should **accept** //NULL// for //$subject// (e.g. checking user input).
  - //hash_file()// should **deprecate** //NULL// for the //$filename//.
  - //hash()// should **accept** //NULL// for //$data//.
  - //substr_count()// should **deprecate** //NULL// for //$needle// ("$needle cannot be empty" error).
  - //mb_convert_encoding()// should **deprecate** //NULL// for //$to_encoding// (requires a valid encoding).

===== Backward Incompatible Changes =====

None

===== Proposed PHP Version(s) =====

PHP 8.2

===== RFC Impact =====

==== To SAPIs ====

None known

==== To Existing Extensions ====

None known

==== To Opcache ====

None known

===== Open Issues =====

Is the [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-change.md|list of functions]] complete?

===== Future Scope =====

Some function parameters could be updated to complain when an Empty String or //NULL// is provided; e.g. //$method// in //method_exists()//, or //$characters// in //trim()//.

===== Voting =====

Accept the RFC

TODO

===== Tests =====

To get and **Test** the list of functions, I wrote a script to //get_defined_functions()//, then used //ReflectionFunction()// to identify parameters that accepted the 'string' type, and not //->allowsNull()//. This resulted in the [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-change.md|list of functions to change]], where I manually removed the [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-other.md|functions that shouldn't be changed]], and updated the script to test every argument (to see that it complained with //NULL//, and the output remained the same) - [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions.php|Source]].

===== Implementation =====

https://github.com/craigfrancis/php-src/compare/master...allow-null

This patch defines //Z_PARAM_STR_ALLOW_NULL//.

It works a bit like //Z_PARAM_STR_OR_NULL//, but it will return an empty string instead of //NULL//.

It's a fairly easy drop in replacement for //Z_PARAM_STR//, e.g. [[https://github.com/php/php-src/blob/7b90ebeb3f954123915f6d62fb7b2cd3fdf3c6ec/ext/standard/html.c#L1324|htmlspecialchars()]].

===== Rejected Features =====

TODO

===== Notes =====

Interesting the example quote from [[http://news.php.net/php.internals/71525|Rasmus]] is:

> PHP is and should remain:
> 1) a pragmatic web-focused language
> 2) a loosely typed language
> 3) a language which caters to the skill-levels and platforms of a wide range of users
