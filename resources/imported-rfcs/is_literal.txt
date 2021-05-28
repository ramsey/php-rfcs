====== PHP RFC: Is Literal Check ======

  * Version: 0.7
  * Date: 2020-03-21
  * Updated: 2021-05-22
  * Author: Craig Francis, craig#at#craigfrancis.co.uk
  * Status: Draft
  * First Published at: https://wiki.php.net/rfc/is_literal
  * GitHub Repo: https://github.com/craigfrancis/php-is-literal-rfc

===== Introduction =====

A new function, //is_literal(string $string)//, to identify variables that have been created from a programmer defined string.

This takes the concept of "taint checking" and makes it simpler and stricter.

It does not allow a variable to be marked as untainted (important).

For example, take a database library that supports parametrised queries at the driver level, today a programmer could use either of these:

<code php>
$db->query('SELECT * FROM users WHERE id = ?', [$_GET['id']]);

$db->query('SELECT * FROM users WHERE id = ' . $_GET['id']); // INSECURE
</code>

If the library only accepted a literal SQL string (written by the programmer), and simply rejected the second example (not written as a literal), the library can provide an "inherently safe API".

Do not confuse this by saying the string is "safe" (more on this later).

This definition of an "inherently safe API" comes from Christoph Kern, who did a talk in 2016 about [[https://www.youtube.com/watch?v=ccfEu-Jj0as|Preventing Security Bugs through Software Design]] (also at [[https://www.usenix.org/conference/usenixsecurity15/symposium-program/presentation/kern|USENIX Security 2015]]), which covers how this concept has been successfully used at Google. The idea is that we "Don't Blame the Developer, Blame the API"; where we need to put the burden on libraries (written once, used by many) to ensure that it's impossible for the developer to make these common injection mistakes.

By adding a way for libraries to check if the strings they receive came from the developer (from trusted PHP source code), it allows the library to check they are being //used// in a safe way (no user data being //injected//).

===== Why =====

Injection and XSS vulnerabilities are still **easy to make**, **hard to identify**, and **very common**.

This is why these two issues have always been on the [[https://owasp.org/www-project-top-ten/|OWASP Top 10]]; a list designed to raise awareness of common issues, which are ranked based on their prevalence, exploitability, detectability, and impact.

Injection and XSS have **always** been on the list, and ranked:

^  Year   ^  Injection Position  ^  XSS Position  ^
|  2003   |  6                   |  4             |
|  2004   |  6                   |  4             |
|  2007   |  2                   |  **1**         |
|  2010   |  **1**               |  2             |
|  2013   |  **1**               |  3             |
|  2017   |  **1**               |  7             |


We like to think all developers read the documentation, and never directly include (inject) user values into their SQL, HTML, OS Command, etc. But that's clearly not the case. By using is_literal(), libraries can identify when this happens, and warn the developer immediately.

And when we come to "Phase 2", this concept can be used by the relevant PHP functions as well.

===== Examples =====

The [[https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/query-builder.html#high-level-api-methods|Doctrine Query Builder]] allows a custom WHERE clause to be provided as a string. This is intended for use with literals and placeholders, but it cannot protect against this simple mistake:

<code php>
// INSECURE
$qb->select('u')
   ->from('User', 'u')
   ->where('u.id = ' . $_GET['id'])
</code>

The definition of the //where()// method could use //is_literal()// and advise the programmer to use placeholders:

<code php>
$qb->select('u')
   ->from('User', 'u')
   ->where('u.id = :identifier')
   ->setParameter('identifier', $_GET['id']);
</code>

Similarly, Twig allows [[https://twig.symfony.com/doc/2.x/recipes.html#loading-a-template-from-a-string|loading a template from a string]], where it's easy to skip the default escaping functionality:

<code php>
// INSECURE
echo $twig->createTemplate('<p>Hi ' . $_GET['name'] . '</p>')->render();
</code>

If //createTemplate()// checked with //is_literal()//, the programmer could be advised to write this instead:

<code php>
echo $twig->createTemplate('<p>Hi {{ name }}</p>')->render(['name' => $_GET['name']]);
</code>

===== Alternatives =====

These have been around for years, and have not worked.

==== Education ====

Developer training simply does not scale (people start programming every day), and learning about every single issue is difficult.

Keeping in mind that programmers will frequently do just enough to complete their task (busy), where they often download a library or copy/paste something (risky), don't really understand it (risky), modify it for their needs (risky), then move on.

We cannot keep saying they 'need to be careful', and rely on them to never make a mistake.

==== Escaping ====

Escaping is **hard**, and **error prone**.

There is a long list of common [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification.md#common-mistakes|escaping mistakes]].

Developers should use parameterised queries (e.g. SQL), or a well tested library that knows how to escape values based on their context (e.g. HTML).

==== Taint Checking ====

Some languages implement a "taint flag" which tracks whether values are considered "safe".

There is a [[https://github.com/laruence/taint|Taint extension for PHP]] by Xinchen Hui, and [[https://wiki.php.net/rfc/taint|a previous RFC proposing it be added to the language]] by Wietse Venema.

These solutions assume the output of an escaping function is safe for a particular context. This sounds reasonable in theory, but the operation of escaping functions, and the context for which their output is safe, are very hard to define. This leads to a feature that is both complex and unreliable.

<code php>
$sql = 'SELECT * FROM users WHERE id = ' . $db->real_escape_string($id);
$html = "<img src=" . htmlentities($url) . " alt='' />";
$html = "<a href='" . htmlentities($url) . "'>...";
</code>

The first two need the values to be quoted, but would be considered "untained" (wrong). The third example, htmlentities before PHP 8.1 ([[https://github.com/php/php-src/commit/50eca61f68815005f3b0f808578cc1ce3b4297f0|fixed]]) does not escape single quotes by default, and it does not consider what happens with 'javascript:' URLs.

This proposal avoids this complexity by addressing a different part of the problem: to keep the inputs written by the programmer separate from inputs supplied by the user.

==== Static Analysis ====

While I agree with [[https://news-web.php.net/php.internals/109192|Tyson Andre]], it is highly recommended to use Static Analysis.

The **biggest** problem is that Static Analysis is simply not (and will not) be used by most developers, especially those who are new to programming (usage tends to be higher by those writing well tested libraries). It is simply an extra step they cannot be bothered to do.

And these tools currently focus on other issues (type checking, basic logic flaws, code formatting, etc).

Those that do attempt to address injection vulnerabilities, do so via Taint Checking (see above), and are [[https://github.com/vimeo/psalm/commit/2122e4a1756dac68a83ec3f5abfbc60331630781|often incomplete]].

For a quick example, Psalm, even in its strictest errorLevel (1), and/or running //--taint-analysis// (I bet you don't use this), will raise a load of false positives, and will not notice the missing quote marks in this SQL, incorrectly assuming this is safe:

<code php>
$db = new mysqli('...');

$id = (string) ($_GET['id'] ?? 'id'); // Keep the type checker happy.

$db->prepare('SELECT * FROM users WHERE id = ' . $db->real_escape_string($id));
</code>

Then, when Psalm comes to taint checking the usage of a library (like Doctrine), it just assumes all methods are safe, because none of them have noted the sinks (and even if they did, you're back to escaping being an issue).

===== Proposal =====

This RFC proposes adding four functions:

  * //is_literal(string $string): bool// to check if a variable represents a value written into the source code or not.

  * //literal_implode(string $glue, array $pieces): string// - implode an array of literals, with a literal.
  * //literal_concat(string $piece, string ...$pieces): string// - concatenating literal strings.
  * //literal_sprintf(string $format, string ...$values): string// - a version of sprintf that uses literals.

A literal is defined as a value (string) which has been written by the programmer.

The value may be passed between functions, as long as the content is not modified.

<code php>
is_literal('Example'); // true

$a = 'Hello';
$b = 'World';

is_literal($a); // true

is_literal($_GET['id']); // false
is_literal('WHERE id = ' . intval($_GET['id'])); // false
is_literal(rand(1, 10)); // false
is_literal(sprintf('LIMIT %d', 3)); // false

function example($input) {
  if (!is_literal($input)) {
    throw new Exception('Non-literal detected!');
  }
  return $input;
}

example($a); // OK
example(example($a)); // OK, still the same literal.
example(strtoupper($a)); // Exception thrown.
</code>

Because so much existing code uses string concatenation (as noted by Benjamin Eberlei as being important in Doctrine), and because it does not modify what the programmer has written, concatenated literals will keep their flag - which includes the use of //str_repeat()//, //str_pad()//, //implode()//, //join()//, //array_pad()//, and //array_fill()//.

<code php>
is_literal($a . $b); // true

is_literal(str_repeat($a, 10)); // true
is_literal(str_repeat($a, rand(1, 10))); // true
is_literal(str_repeat(rand(1, 10), 10)); // false, non-literal input

is_literal(str_pad($a, 10, '-')); // true
is_literal(str_pad($a, 10, rand(1, 10))); // false

is_literal(implode(' AND ', [$a, $b])); // true
is_literal(implode(' AND ', [$a, rand(1, 10)])); // false

is_literal(array_pad([$a], 10, $b)); // true
is_literal(array_pad([$a], rand(1, 10), $b)); // true
is_literal(array_pad([$a], 10, rand(1, 10))); // false
is_literal(array_pad([$a, rand(1, 10)], 10, $b)); // false

is_literal(array_fill(0, 10, $a)); // true
is_literal(array_fill(0, 10, rand(1, 10))); // false

$c = literal_concat($a, $b); // Exception thrown with non-literal inputs.
is_literal($c); // true

$c = literal_implode(' AND ', [$a, $b]); // Exception thrown with non-literal inputs.
is_literal($c); // true
</code>

There is **no way** to mark a string as a literal (i.e. no equivalent to //untaint()//); as soon as the value has been manipulated or includes anything that is not from a literal (e.g. user data), it is no longer marked as a literal (important).

===== Previous Work =====

Google uses "compile time constants" in **Go**, which isn't as good as a run time solution (e.g. the //WHERE IN// issue), but it works, and is used by [[https://blogtitle.github.io/go-safe-html/|go-safe-html]] and [[https://github.com/google/go-safeweb/tree/master/safesql|go-safesql]].

Google also uses [[https://errorprone.info/|Error Prone]] in **Java** to augment the compiler's type analysis, where [[https://errorprone.info/bugpattern/CompileTimeConstant|@CompileTimeConstant]] ensures method parameters can only use "compile-time constant expressions" (this isn't a complete solution either).

**Perl** has a [[https://perldoc.perl.org/perlsec#Taint-mode|Taint Mode]], via the -T flag, where all input is marked as "tainted", and cannot be used by some methods (like commands that modify files), unless you use a regular expression to match and return known-good values (where regular expressions are easy to get wrong).

**JavaScript** is looking to implement [[https://github.com/tc39/proposal-array-is-template-object|isTemplateObject]] for "Distinguishing strings from a trusted developer from strings that may be attacker controlled" (intended to be [[https://github.com/mikewest/tc39-proposal-literals|used with Trusted Types]]).

As noted above, there is the [[https://github.com/laruence/taint|Taint extension for PHP]] by Xinchen Hui.

And there is the [[https://wiki.php.net/rfc/sql_injection_protection|Automatic SQL Injection Protection]] RFC by Matt Tait, where this RFC uses a similar concept of the [[https://wiki.php.net/rfc/sql_injection_protection#safeconst|SafeConst]]. When Matt's RFC was being discussed, it was noted:

  * "unfiltered input can affect way more than only SQL" ([[https://news-web.php.net/php.internals/87355|Pierre Joye]]);
  * this amount of work isn't ideal for "just for one use case" ([[https://news-web.php.net/php.internals/87647|Julien Pauli]]);
  * It would have effected every SQL function, such as //mysqli_query()//, //$pdo->query()//, //odbc_exec()//, etc (concerns raised by [[https://news-web.php.net/php.internals/87436|Lester Caine]] and [[https://news-web.php.net/php.internals/87650|Anthony Ferrara]]);
  * Each of those functions would need a bypass for cases where unsafe SQL was intentionally being used (e.g. phpMyAdmin taking SQL from POST data) because some applications intentionally "pass raw, user submitted, SQL" (Ronald Chmara [[https://news-web.php.net/php.internals/87406|1]]/[[https://news-web.php.net/php.internals/87446|2]]).

I also agree that "SQL injection is almost a solved problem [by using] prepared statements" ([[https://news-web.php.net/php.internals/87400|Scott Arciszewski]]), and this is the point of //is_literal()// - so we can check that no mistakes have been made.

===== Usage =====

==== Basic ====

How a library could use is_literal():

<code php>
class db {

  protected $protection_level = 1;
    // 1 = Just warnings
    // 2 = Exceptions, maybe the default in a few years.

  function literal_check($var) {
    if (!function_exists('is_literal') || is_literal($var)) {
      // Fine - Cannot check in PHP 8.0, or this is a programmer defined string.
    } else if ($var instanceof unsafe_sql) {
      // Fine - Not ideal, but at least they know this one is unsafe.
    } else if ($this->protection_level === 0) {
      // Fine - Programmer aware, and is choosing to disable this check everywhere.
    } else if ($this->protection_level === 1) {
      trigger_error('Non-literal detected!', E_USER_WARNING);
    } else {
      throw new Exception('Non-literal detected!');
    }
  }
  function enforce_injection_protection() {
    $this->protection_level = 2;
  }
  function unsafe_disable_injection_protection() {
    $this->protection_level = 0; // Use unsafe_sql for special cases.
  }

  function where($sql, $parameters = [], $aliases = []) {
    $this->literal_check($sql);
    // ...
  }

}
</code>

You will notice this library supports an //unsafe_sql// value-object which bypasses this check. This should only be a temporary thing, as there are much safer ways of handling exceptions.

<code php>
class unsafe_sql {
  private $value = '';
  function __construct($unsafe_sql) {
    $this->value = $unsafe_sql;
  }
  function __toString() {
    return $this->value;
  }
}
</code>

How this library would be used:

<code php>
$db = new db();
$db->where('id = ?'); // OK
$db->where('id = ' . $_GET['id']); // Mistake detected (warning given)
</code>

==== WHERE IN ====

When you have an undefined number of parameters; for example //WHERE IN//.

You should already be following the advice from [[https://stackoverflow.com/a/23641033/538216|Levi Morrison]], [[https://www.php.net/manual/en/pdostatement.execute.php#example-1012|PDO Execute]], and [[https://www.drupal.org/docs/7/security/writing-secure-code/database-access#s-multiple-arguments|Drupal Multiple Arguments]]:

<code php>
$sql = 'WHERE id IN (' . join(',', array_fill(0, count($ids), '?')) . ')';
</code>

Or, if you prefer to see the concatenation in action:

<code php>
$sql = '?';
for ($k = 1; $k < $count; $k++) {
  $sql .= ',?';
}
</code>

This will push everyone to use parameters properly, rather than using implode() on user values, to include them directly in the SQL, because it's easy to get wrong.

==== Non Parameterised Values ====

Adding Table and Fields in SQL cannot use parameters, but you can //still// use literals:

<code php>
$order_fields = [
    'name',
    'created',
    'admin',
  ];

$order_id = array_search(($_GET['sort'] ?? NULL), $order_fields);

$sql = literal_concat(' ORDER BY ', $order_fields[$order_id]);
</code>

And by using an allow-list, we ensure the user (attacker) cannot use anything unexpected.

==== Special Cases ====

And for a real edge case, where the end user must provide the table/field names (maybe a weird CMS that changes the table structure).

This would require the database abstraction to handle those values separately. This ensures the majority of the SQL is a literal (good), and it will check/escape those table/field names when applying them to the SQL (also good).

[[https://gist.github.com/craigfrancis/5b057414de4119bcc80163ac669a7360#file-is_literal-php-L235|How it can be done]].

===== Considerations =====

==== Naming ====

A "Literal String" is the standard name for strings in source code. See [[https://www.google.com/search?q=what+is+literal+string+in+php|Google]].

> A string literal is the notation for representing a string value within the text of a computer program. In PHP, strings can be created with single quotes, double quotes or using the heredoc or the nowdoc syntax...

Alternative suggestions have included //is_from_literal()// from [[https://news-web.php.net/php.internals/109197|Jakob Givoni]].

Other terms have included "compile time constants" and "code string".

We cannot call it //is_safe_string()// because we do not know that it is "safe". For example, the command //'rm -rf ?'// is not safe if given a path to something that shouldn't be deleted (insert classic //rm -rf /// joke here).

==== Int/Float/Boolean Values. ====

When converting to string, they aren't guaranteed (and often don't) have the exact same value they have in source code.

For example, //TRUE// and //true// when cast to string give "1".

It's also a very low value feature, and there might not be space for a flag to be added.

==== Performance ====

To do this testing, Máté Kocsis has created a [[https://github.com/kocsismate/php-version-benchmarks/|php benchmark]] to replicate the old [[https://01.org/node/3774|Intel Tests]].

The [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/tests/results/with-concat/kocsismate.pdf|preliminary testing on this implementation]] has found a 0.124% performance hit for the Laravel Demo app, 0.161% for Symfony (rounds 4-6, which involved 5000 requests). These tests do not connect to a database, as the variability introduced makes it impossible to measure the difference.

There is a more severe 3.719% when running this [[https://github.com/kocsismate/php-version-benchmarks/blob/main/app/zend/concat.php#L25|concat test]], which is not representative of a typical PHP script (it's not normal to concatenate 4 strings, 5 million times, with no other actions).

==== String Concatenation ====

Dan Ackroyd has considered an approach that does not use string concatenation at run time. The intention was to reduce the performance impact even further; and by using the //literal_concat()// or //literal_implode()// support functions, it can make it easier for developers identify their mistakes.

Performance wise, I made up a test patch (not properly checked), to skip string concat at runtime, and with my own [[https://github.com/craigfrancis/php-is-literal-rfc/tree/main/tests|simplistic testing]] the [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/tests/results/with-concat/local.pdf|results]] found:

    Laravel Demo App: +0.30% with, vs +0.18% without concat.
    Symfony Demo App: +0.06% with, vs +0.06% without concat.
    My Concat Test:   +4.36% with, vs +2.23% without concat.
    -
    Website with 22 SQL queries: Inconclusive, too variable.

There is still a small impact without concat because the //concat_function()// in "zend_operators.c" uses //zend_string_extend()// (where the literal flag needs to be removed). And in "zend_vm_def.h", it has a similar version; and supports a quick concat with an empty string, which doesn't create a new variable (x2) and would need its flag removed as well.

Technically runtime concat isn't needed for most libraries, like an ORM or Query Builder, where their methods nearly always take a small literal string. But it does make the adoption of //is_literal()// easier for existing projects that are currently using string concat for their SQL, HTML Snippets, etc.

Supporting runtime concat would make the is_literal() easier to understand, as it would be consistent with compiler vs runtime concat (because the compiler can sometimes concat strings, creating a single literal that would have the literal flag set).

==== Support Functions ====

The //literal_concat()// and //literal_implode()// functions.

These do not need to be used, but they can help developers identify their mistakes.

As noted by Dan Ackroyd, rather than waiting to the end, after multiple string concatenations, any mistakes would be picked up much earlier when using these functions. For example:

<code php>
$sortOrder = 'ASC';

// 300 lines of code, or multiple function calls

$sql .= ' ORDER BY name ' . $sortOrder;

// 300 lines of code, or multiple function calls

$db->query($sql);
</code>

If a developer changed the literal //'ASC'// to //$_GET['order']//, the error would be noticed by //$db->query()//, but it's not clear where the mistake was made. Whereas, if they were using //literal_concat()//, it would raise an exception much earlier, and highlight exactly where the mistake happened:

<code php>
$sql = literal_concat($sql, ' ORDER BY name ', $sortOrder);
</code>

==== Non Literal Values ====

As noted by [[https://news-web.php.net/php.internals/87667|Dennis Birkholz]], some Systems/Frameworks currently define certain variables (e.g. table name prefixes) without the use of a literal (e.g. ini/json/yaml).

And Larry Garfield notes that in Drupal's ORM "the table name itself is user-defined" (not in the PHP script).

While most systems will be able to use literals entirely, those that cannot will be able to use a Query Builder - one that validates the majority of its input are literals, and the exceptions can be accepted via appropriate validation and escaping (i.e. does this string match the format, or is a known, table name).

Have a look at [[https://gist.github.com/craigfrancis/5b057414de4119bcc80163ac669a7360|this example gist]] to see all the different ways this can be done.

==== Existing String Functions ====

Trying to determine if the //is_literal// flag should be passed through functions like //substr()// etc is difficult. Having a security feature be difficult to reason about, gives a much higher chance of mistakes.

==== Reflection API ====

It might be possible for the Reflection API to say if a the code calling a method used a literal string, but that's all it could do. It will not be able to support variables; or provide any future scope for these checks (aka "Phase 2").

===== Backward Incompatible Changes =====

No known BC breaks, except for code-bases that already contain userland functions //is_literal()//, //literal_implode()// or //literal_concat()//.

===== Proposed PHP Version(s) =====

PHP 8.1

===== RFC Impact =====

==== To SAPIs ====

None known

==== To Existing Extensions ====

Not sure

==== To Opcache ====

Not sure

===== Open Issues =====

None

===== Unaffected PHP Functionality =====

None known

===== Future Scope =====

As noted by MarkR, the biggest benefit will come when it can be used by PDO and similar functions (//mysqli_query//, //preg_match//, //exec//, etc). But the basic idea can be used immediately by frameworks and general abstraction libraries, and they can give feedback for future work.

**Phase 2** could introduce a way for programmers to specify certain PHP function/method arguments can only accept literals, and/or specific value-objects their project trusts (this idea comes from [[https://web.dev/trusted-types/|Trusted Types]] in JavaScript, and their [[https://www.youtube.com/watch?v=po6GumtHRmU&t=92s|60+ Injection Sinks]] - like innerHTML).

For example, a project could require the second argument for //pg_query()// only accept literals or their //query_builder// object (which provides a //__toString// method); and that any output (print, echo, readfile, etc) must use the //html_output// object that's returned by their trusted HTML Templating system (using //ob_start()// might be useful here).

**Phase 3** could set a default of 'only literals' for all of the relevant PHP function arguments, so developers are given a warning, and later prevented (via an exception), when they provide an unsafe value to those functions (they could still specify that unsafe values are allowed, e.g. phpMyAdmin).

And, for a bit of silliness (Spaß ist verboten), MarkR would like a //is_figurative()// function (functionality to be confirmed).

===== Proposed Voting Choices =====

Accept the RFC. Yes/No

If no, why not?

===== Implementation =====

[[https://github.com/craigfrancis/php-src/tree/is_literal-with-functions|Available on GitHub]].

It includes [[https://github.com/php/php-src/compare/master...krakjoe:literals|Joe Watkin's implementation]], which applies the literal flags, and supports string concat at runtime. And [[https://github.com/php/php-src/compare/master...Danack:is_literal_attempt_two|Dan Ackroyd's implementation]], which provides //literal_concat()// and //literal_implode()//.

===== References =====

N/A

===== Rejected Features =====

N/A

===== Thanks =====

  - **Dan Ackroyd**, DanAck, for starting the first implementation (which made this a reality), and followup on the version that provides //literal_concat()// and //literal_implode()//.
  - **Joe Watkins**, krakjoe, for finding how to set the literal flag, and creating the implementation that supports string concat.
  - **Máté Kocsis**, mate-kocsis, for setting up and doing the performance testing.
  - **Rowan Tommins**, IMSoP, for re-writing this RFC to focus on the key features, and putting it in context of how it can be used by libraries.
  - **Nikita Popov**, NikiC, for suggesting where the literal flag could be stored. Initially this was going to be the "GC_PROTECTED flag for strings", which allowed Dan to start the first implementation.
  - **Mark Randall**, MarkR, for suggestions, and noting that "interned strings in PHP have a flag", which started the conversation on how this could be implemented.
  - **Xinchen Hui**, who created the Taint Extension, allowing me to test the idea; and noting how Taint in PHP5 was complex, but "with PHP7's new zend_string, and string flags, the implementation will become easier" [[https://news-web.php.net/php.internals/87396|source]].
