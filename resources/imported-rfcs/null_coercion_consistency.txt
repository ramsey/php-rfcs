====== PHP RFC: NULL Coercion Consistency ======

  * Version: 0.2
  * Voting Start: ?
  * Voting End: ?
  * RFC Started: 2022-04-05
  * RFC Updated: 2022-04-05
  * Author: Craig Francis [craig#at#craigfrancis.co.uk]
  * Status: Draft
  * First Published at: https://wiki.php.net/rfc/null_coercion_consistency
  * GitHub Repo: https://github.com/craigfrancis/php-null-coercion-consistency-rfc
  * Implementation: ?

===== Introduction =====

PHP 8.1 introduced [[https://wiki.php.net/rfc/deprecate_null_to_scalar_internal_arg|Deprecate passing null to non-nullable arguments of internal functions]]. While the consistency is welcome (user-defined vs internal functions), for those **not** using strict Static Analysis or //strict_types=1//, the breaking of NULL coercion creates an upgrade problem.

This RFC does **not** change anything for //strict_types=1// (or strict static analysis), as strict type checks can be useful. For example, developers can view NULL as a missing/invalid value (not as a value in itself), and passing NULL to a function like //htmlspecialchars()// could indicate a problem.

Roughly **15%** of scripts use //strict_types=1// (calculation below).

Roughly **33%** of developers use static analysis (realistically it's less than this, details below).

There was a [[https://externals.io/message/112327|short discussion]] about the original RFC, but with the exception of Craig Duncan, there was no consideration for the problems this creates with existing code (or the inconsistency of NULL coercion compared to string/int/float/bool coercion; or other contexts like string concatenation, == comparisons, arithmetics, sprintf, etc).

The intention is to also keep [[https://github.com/Girgias/unify-typing-modes-rfc|Unify PHP's typing modes]] by George Peter Banyard in mind, with coercions like //substr($string, "offset")// and //htmlspecialchars(array())// as being clearly problematic; whereas the following is common, and has been fine:

<code php>
$search = filter_input(INPUT_GET, 'q'); // Or similar (examples below)

echo 'Results for ' . htmlspecialchars($search);
</code>

===== Problem =====

==== Documentation ====

According to the documentation, when **not** using //strict_types=1//, "PHP will coerce values of the wrong type into the expected scalar type declaration if possible" ([[https://www.php.net/manual/en/language.types.declarations.php#language.types.declarations.strict|ref]]).

Coercion from NULL is well defined:

  - [[https://www.php.net/manual/en/language.types.string.php|To String]]: "null is always converted to an empty string."
  - [[https://www.php.net/manual/en/language.types.integer.php|To Integer]]: "null is always converted to zero (0)."
  - [[https://www.php.net/manual/en/language.types.float.php|To Float]]: "For values of other types, the conversion is performed by converting the value to int first and then to float"
  - [[https://www.php.net/manual/en/language.types.boolean.php|To Boolean]]: "When converting to bool, the following values are considered false [...] the special type NULL"

==== Current State ====

<code php>
// echo(string ...$expressions): void
// print(string $expression): int
print('A');
print(1);
print(1.2);
print(false);
print(NULL); // Fine, coerced to empty string.

var_dump(3 + '5' + NULL); // Fine, int(8)
var_dump(NULL / 6); // Fine, int(0)

$o = [];

$o[] = ('' == '');
$o[] = ('' == NULL); // Fine, coerced to empty string.

$o[] = 'ConCat ' . 'A';
$o[] = 'ConCat ' . 123;
$o[] = 'ConCat ' . 1.2;
$o[] = 'ConCat ' . false;
$o[] = 'ConCat ' . NULL; // Fine, coerced to empty string.

$o[] = sprintf('%s', 'A');
$o[] = sprintf('%s', 1);
$o[] = sprintf('%s', 1.2);
$o[] = sprintf('%s', false);
$o[] = sprintf('%s', NULL); // Fine, coerced to empty string.

$o[] = htmlspecialchars('A');
$o[] = htmlspecialchars(1);
$o[] = htmlspecialchars(1.2);
$o[] = htmlspecialchars(false);
$o[] = htmlspecialchars(NULL); // Deprecated in 8.1, Fatal Error in 9.0?
</code>

With user-defined functions, while there hasn't been a backwards compatibility issue, it still highlights the coercion inconsistency, in an environment that does not expect type checking, despite NULL being a value that "can be coerced to the type requested by the hint without data loss and without creation of likely unintended data":

<code php>
function user_function(string $s, int $i, float $f, bool $b) {
  var_dump($s, $i, $f, $b);
}

user_function('1', '1', '1', '1');
  // string(1) "1" / int(1) / float(1) / bool(true)

user_function(2, 2, 2, 2);
  // string(1) "2" / int(2) / float(2) / bool(true)

user_function(3.3, 3.3, 3.3, 3.3);
  // string(3) "3.3" / int(3) / float(3.3) / bool(true)

user_function(false, false, false, false);
  // string(0) "" / int(0) / float(0) / bool(false)

user_function(NULL, NULL, NULL, NULL);
  // Uncaught TypeError x4?
</code>

Arrays, Resources, and Objects (without //__toString()//) cannot be coerced (for fairly obvious reasons).

String/Int/Float/Bool can be coerced.

NULL can usually be coerced (e.g. string concatenation, == comparisons, arithmetics, sprintf, print, echo, array keys), but...

  - PHP 7.0 introduced the ability for user-defined functions to specify parameter types via the [[https://wiki.php.net/rfc/scalar_type_hints_v5#behaviour_of_weak_type_checks|Scalar Type Declarations RFC]], where the implementation triggered Type Errors for those using //strict_types=1//, and otherwise used coercion for string/int/float/bool, but not NULL.
  - PHP 8.1 updated internal function parameters to work in the same way.

==== Examples ====

Common sources of NULL:

<code php>
$search = (isset($_GET['q']) ? $_GET['q'] : NULL);

$search = ($_GET['q'] ?? NULL); // Since PHP 7

$search = filter_input(INPUT_GET, 'q');

$search = $request->input('q'); // Laravel
$search = $request->get('q'); // Symfony
$search = $this->request->getQuery('q'); // CakePHP
$search = $request->getGet('q'); // CodeIgniter

$value = array_pop($empty_array);
$value = mysqli_fetch_row($result);
$value = json_decode($json); // Invalid JSON, or nesting limit.
</code>

Examples functions, often working with user input, where NULL has been fine:

<code php>
$search_trimmed = trim($search);

$search_len = strlen($search);

$search_upper = strtoupper($search);

$search_hash = hash('sha256', $search);

echo htmlspecialchars($search);

echo 'https://example.com/?q=' . urlencode($search);

preg_match('/^[a-z]/', $search);

exec('/path/to/cmd ' . escapeshellarg($search));

socket_write($socket, $search);

xmlwriter_text($writer, $search);
</code>

And developers have used NULL to skip certain parameters, e.g.

<code php>
setcookie('q', $search, NULL, NULL, NULL, true, true); // x4

substr($string, NULL, 3);

mail('nobody@example.com', 'subject', 'message', NULL, '-fwebmaster@example.com');
</code>

HTML Templating engines like [[https://github.com/laravel/framework/blob/ab1506091b9f166b312b3990d07b2e21d971f2e6/src/Illuminate/Support/helpers.php#L119|Laravel Blade]] suppress this deprecation with null-coalescing ([[https://github.com/laravel/framework/pull/36262/files#diff-15b0a3e2eb2d683222d19dfacc04c616a3db4e3d3b3517e96e196ccbf838f59eR118|patch]]); or [[https://github.com/twigphp/Twig/blob/b4d6723715da57667cca851051eba3786714290d/src/Extension/EscaperExtension.php#L195|Symphony Twig]] which preserves NULL, but it's often passed to //echo// (which accepts it, despite the [[https://www.php.net/echo|echo documentation]] saying it accepts non-nullable strings).

I'd argue a very strict level of type checking (that prevents all forms of coercion) is best done by Static Analysis, which can check if a variable can be nullable, and it can decide if this is a problem, in the same way that a string (e.g. '15') being provided to integer parameter could be seen as a problem.

There are approximately [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-change.md|335 parameters affected by this deprecation]].

As an aside, there are also roughly [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-maybe.md|104 questionable]] and [[https://github.com/craigfrancis/php-allow-null-rfc/blob/main/functions-other.md|558 problematic]] parameters which probably shouldn't accept NULL **or** an Empty String. For these parameters, a different RFC could consider updating them to reject both NULL and Empty Strings, e.g. //$needle// in //strpos()//, and //$characters// in //trim()//; in the same way that //$separator// in //explode()// already has a "cannot be empty" Fatal Error.

==== Finding ====

The only realistic way for developers to find when NULL is passed to these internal functions is to use the deprecation notices (not ideal).

It is possible to use very strict Static Analysis, to follow every variable from source to sink (to check if a variable could be NULL), but most developers are not in a position to do this (i.e. not using static analysis, or not at a high enough level, or they are using a baseline to ignore).

In the last JetBrains developer survey (with 67% regularly using Laravel), **only 33% used Static Analysis** ([[https://www.jetbrains.com/lp/devecosystem-2021/php/#PHP_do-you-use-static-analysis|source]]); where it's fair to many still would still not be identify these possible NULL values (too low level, and/or using a baseline).

As an example, take this simple script:

<code php>
./src/index.php
<?php
$nullable = ($_GET['a'] ?? NULL);
echo htmlentities($nullable);
?>
</code>

Even that is considered fine today by the relevant tools:

<code cli>
composer require --dev rector/rector
./vendor/bin/rector init
./vendor/bin/rector process ./src/
[OK] Rector is done!
</code>

<code cli>
composer require --dev "squizlabs/php_codesniffer=*"
./vendor/bin/phpcs -p ./src/
E 1 / 1 (100%)
[...]
 2 | ERROR | Missing file doc comment
[...]
</code>

<code cli>
composer require friendsofphp/php-cs-fixer
./vendor/bin/php-cs-fixer fix src --diff --allow-risky=yes
Loaded config default.
Using cache file ".php-cs-fixer.cache".
   1) src/index.php
      ---------- begin diff ----------
--- src/index.php
+++ src/index.php
@@ -1,4 +1,4 @@
 <?php
+
 $nullable = ($_GET['a'] ?? null);
 echo htmlentities($nullable);
-?>
\ No newline at end of file

      ----------- end diff -----------

Fixed all files in 0.012 seconds, 12.000 MB memory used
</code>

<code cli>
composer require --dev phpcompatibility/php-compatibility
sed -i '' -E 's/(PHPCSHelper::getConfigData)/(string) \1/g' vendor/phpcompatibility/php-compatibility/PHPCompatibility/Sniff.php
./vendor/bin/phpcs --config-set installed_paths vendor/phpcompatibility/php-compatibility

./vendor/bin/phpcs -p ./src/ --standard=PHPCompatibility --runtime-set testVersion 8.1
. 1 / 1 (100%)
</code>

Note: Juliette (@jrfnl) has confirmed that getting PHPCompatibility to solve this problem will be "pretty darn hard to do" because it's "not reliably sniffable" ([[https://twitter.com/jrf_nl/status/1497937320766496772|source]]).

<code cli>
composer require --dev phpstan/phpstan
./vendor/bin/phpstan analyse -l 9 ./src/
[OK] No errors
</code>

<code cli>
composer require --dev phpstan/phpstan-strict-rules
composer require --dev phpstan/extension-installer
./vendor/bin/phpstan analyse -l 9 ./src/
[OK] No errors
</code>
Note: There are [[https://phpstan.org/config-reference#stricter-analysis|Stricter Analysis]] options for PHPStan, but they don't seem to help with this problem.

<code cli>
composer require --dev vimeo/psalm
./vendor/bin/psalm --init ./src/ 4
./vendor/bin/psalm
No errors found!
</code>
Note: Psalm can detect this at [[https://psalm.dev/docs/running_psalm/error_levels/|levels 1, 2, and 3]] (don't use a baseline).

==== Temporary Solutions ====

You can disable //E_DEPRECATED// (as recommended by projects like WordPress).

Alternatively you can use //set_error_handler()//, with something like:

<code php>
function ignore_null_coercion($errno, $errstr) {
  // https://github.com/php/php-src/blob/012ef7912a8a0bb7d11b2dc8d108cc859c51e8d7/Zend/zend_API.c#L458
  if ($errno === E_DEPRECATED && preg_match('/Passing null to parameter #.* of type .* is deprecated/', $errstr)) {
    return true;
  }
  return false;
}
set_error_handler('ignore_null_coercion', E_DEPRECATED);
</code>

And some developers are simply [[https://externals.io/message/116519#116559|patching php-src]] (risky).

==== Updating ====

While making each change is fairly easy - they are difficult to find, there are many of them (time consuming), and the updates used are often pointless, e.g.

  * //urlencode(strval($name));//
  * //urlencode((string) $name);//
  * //urlencode($name ?? "");//

One example diff didn't exactly make the code easier to read:

<code diff>
 - $result = substr($string, 0, $length);
 + $result = substr($string ?? '', 0, $length);
</code>

As noted above - PHPCompatibility, CodeSniffer, Rector, etc are unable to find or update these cases.

===== Proposal =====

Revert the NULL deprecation for parameters (when **not** using //strict_types=1//), so it continues to work (as NULL coercion does in other contexts), to avoid the upgrade problems (i.e. Fatal Errors in PHP 9.0).

And, in the spirit of the original RFC to keep user-defined and internal functions consistent, also change user-defined functions so NULL is coerced for non-nullable parameters (when **not** using //strict_types=1//).

This means the type "//?int//" will allow NULL or an integer to be provided to the function; whereas the non-nullable type "//int//" would coerce NULL to 0, in the same way the string "0" would be.

===== Backward Incompatible Changes =====

While the intention of this RFC is to avoid a BC break; for user defined functions to be updated to also coerce NULL (instead of throwing a Type Error), it's possible some code may rely on that behaviour, for example:

<code php>
function my_function(string $my_string) {
  var_dump($my_string);
}

try {
  my_function('A');   // string(1) "A"
  my_function(1);     // string(1) "1"
  my_function(1.2);   // string(3) "1.2"
  my_function(true);  // string(1) "1"
  my_function(false); // string(0) ""
  my_function(NULL);  // Throw Type Error
} catch (TypeError $e) {
  // Do something important?
}
</code>

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

"it's a bit late" - We only have a deprecation at the moment (which can and is being ignored), it will be "too late" when PHP 9.0 uses Fatal Errors.

"Userland scalar types [...] did not include coercion from NULL for //very// good reasons." - The only reason mentioned in [[https://wiki.php.net/rfc/scalar_type_hints_v5|Scalar Type Declarations]] is "to be consistent with our existing type declarations" (no further details given). Talking to developers, the only reason mentioned is noted above, where NULL can be viewed as a missing/invalid value, and passing NULL to a function like //htmlspecialchars()// could indicate a problem (which can a be useful check for static analysis, or in the context of //strict_types=1//).

The function //mt_rand()// can be called with no arguments, or with min and max integer arguments. A developer may call //mt_rand(NULL, NULL)// and expect it to work the same as no arguments (returning a random number between 0 and //mt_getrandmax()//), but the NULL's would be coerced to 0, so it would always return 0. That said, I cannot find any public examples of this happening ([[https://grep.app/search?q=mt_rand%28NULL&filter%5Blang%5D%5B0%5D=PHP|1]], [[https://grep.app/search?q=mt_rand%5Cs%2A%5C%28%5Cs%2ANULL&regexp=true&filter[lang][0]=PHP|2]], [[https://www.google.com/search?q=%22mt_rand+NULL+NULL%22|3]]).

===== Future Scope =====

Some function parameters could be updated to rase a Fatal Error when //NULL// **or** //Empty String// is provided; e.g.

  - //$needle// in [[https://php.net/strpos|strpos()]]
  - //$characters// in [[https://php.net/trim|trim()]]
  - //$method// in [[https://php.net/method_exists|method_exists()]]
  - //$json// in [[https://php.net/json_decode|json_decode()]]

===== Voting =====

Accept the RFC

TODO

===== Implementation =====

TODO

===== Rejected Features =====

  - Updating some parameters to accept NULL ([[https://wiki.php.net/rfc/allow_null|details]]). This was rejected because some developers view NULL as a missing/invalid value that should never be passed to functions like //htmlspecialchars()// ([[https://quiz.craigfrancis.co.uk/|quiz results]]).

===== Notes =====

The **15%** of scripts that do not use //strict_types=1// was calculated using [[https://grep.app/|grep.app]], to "search across a half million git repos", were each result is a script (not a count of matches,  [[https://grep.app/search?q=defuse/php-encryption&filter[lang][0]=PHP|example]]). We can see [[https://grep.app/search?q=strict_types&filter[lang][0]=PHP|272,871]] scripts using //strict_types=1//, out of [[https://grep.app/search?q=php&filter[lang][0]=PHP|1,842,666]]. And keep in mind that [[https://grep.app/search?q=class%20wpdb%20%7B&filter[lang][0]=PHP|WordPress only really appears once]], it is [[https://make.wordpress.org/core/2022/01/10/wordpress-5-9-and-php-8-0-8-1/#php-8-1-deprecation-passing-null-to-non-nullable-php-native-functions-parameters|affected by this deprecation]], and is installed/used by many.

In the [[https://wiki.php.net/rfc/scalar_type_hints_v5#behaviour_of_weak_type_checks|Scalar Type Declarations]] RFC for PHP 7.0, scalar types were defined as "int, float, string and bool" - but, despite NULL also being a simple value (i.e. not an array/object/resource), it was not included in this definition. For backwards compatibility reasons this definition is unlikely to change.

Also, note the example quote from [[http://news.php.net/php.internals/71525|Rasmus]]:

> PHP is and should remain:
> 1) a pragmatic web-focused language
> 2) a loosely typed language
> 3) a language which caters to the skill-levels and platforms of a wide range of users
