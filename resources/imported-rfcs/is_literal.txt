====== PHP RFC: Is Literal Check ======

  * Version: 0.8
  * Date: 2020-03-21
  * Updated: 2021-06-06
  * Author: Craig Francis, craig#at#craigfrancis.co.uk
  * Contributors: Joe Watkins, Dan Ackroyd, Máté Kocsis
  * Status: Draft
  * First Published at: https://wiki.php.net/rfc/is_literal
  * GitHub Repo: https://github.com/craigfrancis/php-is-literal-rfc

===== Introduction =====

Add the function //is_literal(string $string)//, to identify strings written by the developer (defined in the source code).

It takes the concept of "taint checking" (which gives a false sense of security), and instead makes it simpler and stricter; so we get a lightweight, simple, and very effective way to identify Injection Vulnerabilities.

Escaping is **hard** ([[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/escaping.php?ts=4|examples]]) and should be avoided (e.g. using parameterised queries), or done by well tested libraries.

Unfortunately, even with thousands of developers using these libraries, far too many still introduce Injection Vulnerabilities by mixing literal strings (e.g. SQL/HTML/CLI) with user data, e.g.

<code php>
$qb->select('u')
   ->from('User', 'u')
   ->where('u.id = ' . $_GET['id']); // INSECURE
</code>

Here are a few other [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/mistakes.php?ts=4|example mistakes]]. Notice how these insecure examples are simple edits from the Libraries' official documentation, they still "work", and are often shorter/easier than doing it correctly. I've found variations of every one of these on production websites (trust me, the shock of finding these wears off after a while, and that's why I'm here).

Libraries would be able to use //is_literal()// immediately, allowing them to warn developers about these issues as soon as they receive any non-literal strings.

For the next step, which requires a gradual deployment (and a separate RFC to cover the details), we take the [[https://web.dev/trusted-types/|Trusted Types]] concept from JavaScript (which suce protects [[https://www.youtube.com/watch?v=po6GumtHRmU&t=92s|60+ Injection Sinks]], like innerHTML). Libraries create a stringable object as their output (which won't contain any Injection Vulnerabilities), and these get marked as "trusted" for native functions like //mysqli_query//, //preg_match//, //exec//, etc.

===== The Problem =====

Injection and Cross-Site Scripting (XSS) vulnerabilities are **easy to make**, **hard to identify**, and **very common**.

We like to think every developer reads the documentation, and would never ever directly include (inject) user values into their SQL/HTML/CLI - but we all know that's not the case.

It's why these two issues have **always** been on the [[https://owasp.org/www-project-top-ten/|OWASP Top 10]]; a list designed to raise awareness of common issues, ranked on their prevalence, exploitability, detectability, and impact:

^  Year           ^  Injection Position  ^  XSS Position  ^
|  2017 - Latest  |  **1**               |  7             |
|  2013           |  **1**               |  3             |
|  2010           |  **1**               |  2             |
|  2007           |  2                   |  **1**         |
|  2004           |  6                   |  4             |
|  2003           |  6                   |  4             |


===== Proposal =====

Add //is_literal(string $string): bool// to check if a variable contains a string defined in the PHP script.

A literal is defined as a value (string) which has been written by the programmer.

<code php>
is_literal('Example'); // true

$a = 'Hello';
$b = 'World';

is_literal($a); // true
is_literal($a . $b); // true
is_literal("Hi $b"); // true

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

Because so much existing code uses string concatenation, and because it does not modify what the programmer has written, concatenated literals will keep their flag. This includes the use of //str_repeat()//, //str_pad()//, //implode()//, //join()//, //array_pad()//, and //array_fill()//.

[[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/usage.php?ts=4|Longer set of examples]] and [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/example.php?ts=4|how it can be used by libraries]] - Notice how this second script provides an //unsafe_sql// value-object to bypasses the //is_literal()// check, but doesn't actually use it (it's useful as a temporary thing, but there are much safer/better solutions). And the library just raises a warning, to let the developer know about the issue, without breaking anything.

===== Taint Checking =====

The key difference from Taint Checking - there's no method to mark a string as a literal (i.e. no equivalent to //untaint()//). As soon as the value has been manipulated or includes anything that is not from a literal (e.g. user data), it is no longer marked as a literal. You should use a well tested library to escape values appropriately.

Taint Checking assumes the output of an escaping function is "safe" for a particular context. This sounds reasonable in theory, but the operation of escaping functions, and the context for which their output is safe, are very hard to define. This leads to a feature that is both complex and unreliable.

<code php>
$sql = 'SELECT * FROM users WHERE id = ' . $db->real_escape_string($id); // INSECURE
$html = "<img src=" . htmlentities($url) . " alt='' />"; // INSECURE
$html = "<a href='" . htmlentities($url) . "'>..."; // INSECURE
</code>

The first two need the values to be quoted, but would be considered "untained" (wrong). The third example, htmlentities before PHP 8.1 ([[https://github.com/php/php-src/commit/50eca61f68815005f3b0f808578cc1ce3b4297f0|fixed]]) does not escape single quotes by default, and it does not consider what happens with 'javascript:' URLs.

===== Previous Work =====

Christoph Kern did a talk in 2016 about [[https://www.youtube.com/watch?v=ccfEu-Jj0as|Preventing Security Bugs through Software Design]] (also at [[https://www.usenix.org/conference/usenixsecurity15/symposium-program/presentation/kern|USENIX Security 2015]]). Starting with the premise of "Don't Blame the Developer, Blame the API", we should use well tested Libraries (written once, used by many) to ensure developers do introduce Injection Vulnerabilities. This has been achieved at Google in **Go** ([[https://blogtitle.github.io/go-safe-html/|go-safe-html]] and [[https://github.com/google/go-safeweb/tree/master/safesql|go-safesql]]) by testing for "compile time constants"; and in **Java** ([[https://errorprone.info/|Error Prone]], where [[https://errorprone.info/bugpattern/CompileTimeConstant|@CompileTimeConstant]] ensure method parameters can only use "compile-time constant expressions").

**JavaScript** is implementing [[https://github.com/tc39/proposal-array-is-template-object|isTemplateObject]] for "Distinguishing strings from a trusted developer from strings that may be attacker controlled" (intended to be [[https://github.com/mikewest/tc39-proposal-literals|used with Trusted Types]]).

**Perl** has a [[https://perldoc.perl.org/perlsec#Taint-mode|Taint Mode]], via the -T flag, where all input is marked as "tainted", and cannot be used by some methods (like commands that modify files), unless you use a regular expression to match and return known-good values (where regular expressions are easy to get wrong).

There is a [[https://github.com/laruence/taint|Taint extension for PHP]] by Xinchen Hui, and [[https://wiki.php.net/rfc/taint|a previous RFC proposing it be added to the language]] by Wietse Venema.

And there is the [[https://wiki.php.net/rfc/sql_injection_protection|Automatic SQL Injection Protection]] RFC by Matt Tait, where this RFC uses a similar concept of the [[https://wiki.php.net/rfc/sql_injection_protection#safeconst|SafeConst]]. When Matt's RFC was being discussed, it was noted:

  * "unfiltered input can affect way more than only SQL" ([[https://news-web.php.net/php.internals/87355|Pierre Joye]]);
  * this amount of work isn't ideal for "just for one use case" ([[https://news-web.php.net/php.internals/87647|Julien Pauli]]);
  * It would have effected every SQL function, such as //mysqli_query()//, //$pdo->query()//, //odbc_exec()//, etc (concerns raised by [[https://news-web.php.net/php.internals/87436|Lester Caine]] and [[https://news-web.php.net/php.internals/87650|Anthony Ferrara]]);
  * Each of those functions would need a bypass for cases where unsafe SQL was intentionally being used (e.g. phpMyAdmin taking SQL from POST data) because some applications intentionally "pass raw, user submitted, SQL" (Ronald Chmara [[https://news-web.php.net/php.internals/87406|1]]/[[https://news-web.php.net/php.internals/87446|2]]).

I also agree that "SQL injection is almost a solved problem [by using] prepared statements" ([[https://news-web.php.net/php.internals/87400|Scott Arciszewski]]), and this is the point of //is_literal()// - so we can check that no mistakes have been made.

===== FAQ's =====

==== Interest ====

Propel (Mark Scherer): "given that this would help to more safely work with user input, I think this syntax would really help in Propel."

RedBean (Gabor de Mooij): "You can list RedBeanPHP as a supporter, we will implement this into the core."

==== Educate Everyone ====

You can't - developer training simply does not scale, and mistakes will still happen.

People start to program every day, learning about Injection Vulnerabilities is difficult (even though I find it fun), and it's close to impossible to never make a mistake - especially when they are busy, copying/pasting code, not necessarily understanding what it does, editing it for their needs, then moving on to the next task.

==== Static Analysis ====

I agree with [[https://news-web.php.net/php.internals/109192|Tyson Andre]], please use Static Analysis.

But Static Analysis is not used by most developers (and never will be).

It's an extra step that most programmers cannot be bothered to do, especially those who are new to programming (its usage tends to be higher among those writing well-tested libraries).

Also, these tools currently focus on other issues (type checking, basic logic flaws, code formatting, etc), rarely attempting to address injection vulnerabilities. Those that do are [[https://github.com/vimeo/psalm/commit/2122e4a1756dac68a83ec3f5abfbc60331630781|often incomplete]], need sinks specified on all library methods (unlikely to happen), and are not enabled by default. For example, Psalm, even in its strictest errorLevel (1), and running //--taint-analysis// (I bet you don't use this), it will not notice the missing quote marks in this SQL, and incorrectly assume it's safe:

<code php>
$db = new mysqli('...');

$id = (string) ($_GET['id'] ?? 'id'); // Keep the type checker happy.

$db->prepare('SELECT * FROM users WHERE id = ' . $db->real_escape_string($id)); // INSECURE
</code>

==== WHERE IN ====

When you have an undefined number of parameters; for example //WHERE id IN (?, ?, ?)//.

You should already be following the advice from [[https://stackoverflow.com/a/23641033/538216|Levi Morrison]], [[https://www.php.net/manual/en/pdostatement.execute.php#example-1012|PDO Execute]], and [[https://www.drupal.org/docs/7/security/writing-secure-code/database-access#s-multiple-arguments|Drupal Multiple Arguments]]:

<code php>
$sql = 'WHERE id IN (' . join(',', array_fill(0, count($ids), '?')) . ')';
</code>

Or, if you prefer to use concatenation:

<code php>
$sql = '?';
for ($k = 1; $k < $count; $k++) {
  $sql .= ',?';
}
</code>

This pushes everyone to use parameters properly; rather than using implode() on user values, and including them directly in the SQL (which is so easy to get wrong).

==== Non Parameterised Values ====

Table and Fields in SQL cannot use parameters, but are often written as literals anyway.

And you can //still// use literals when it's dependent on user input:

<code php>
$order_fields = [
    'name',
    'created',
    'admin',
  ];

$order_id = array_search(($_GET['sort'] ?? NULL), $order_fields);

$sql .= ' ORDER BY ' . $order_fields[$order_id];
</code>

By using an allow-list, we ensure the user (attacker) cannot use anything unexpected.

==== Non Literal Values ====

As noted by [[https://news-web.php.net/php.internals/87667|Dennis Birkholz]], some Systems/Frameworks currently define certain variables (e.g. table name prefixes) without the use of a literal (e.g. ini/json/yaml).

And Larry Garfield notes that in Drupal's ORM "the table name itself is user-defined" (not in the PHP script).

While most systems can use literals entirely, special values should still be handled separately. This allows the library to ensure the majority of the input (SQL) is a literal, then it can consistently check/escape those special values (e.g. do the match a known table/field name, or match a known-safe value).

[[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/example.php?ts=4#L169|How this can be done]].

==== Naming ====

A "Literal String" is the standard name for strings in source code. See [[https://www.google.com/search?q=what+is+literal+string+in+php|Google]].

> A string literal is the notation for representing a string value within the text of a computer program. In PHP, strings can be created with single quotes, double quotes or using the heredoc or the nowdoc syntax...

Alternative suggestions have included //is_from_literal()// from [[https://news-web.php.net/php.internals/109197|Jakob Givoni]], and references to alternative implementations like "compile time constants" and "code string".

We cannot call it //is_safe_string()//, because we cannot say that a string is safe:

<code php>
$cli = 'rm -rf ?';
$sql = 'DELETE FROM my_table WHERE my_date >= ?';
eval('$name = "' . $_GET['name'] . '";'); // INSECURE
</code>

The parameters for the first two could be set to "/" or "0000-00-00" (providing a nice vanishing magic trick); and the last one, well, they probably have much bigger issues to worry about.

==== Performance ====

Máté Kocsis has created a [[https://github.com/kocsismate/php-version-benchmarks/|php benchmark]] to replicate the old [[https://01.org/node/3774|Intel Tests]].

The [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/tests/results/with-concat/kocsismate.pdf|preliminary testing on this implementation]] has found a 0.124% performance hit for the Laravel Demo app, 0.161% for Symfony (rounds 4-6, which involved 5000 requests). These tests do not connect to a database, as the variability introduced makes it impossible to measure the difference.

There is a more severe 3.719% when running this [[https://github.com/kocsismate/php-version-benchmarks/blob/main/app/zend/concat.php#L25|concat test]], which is not representative of a typical PHP script (it's not normal to concatenate 4 strings, 5 million times, with no other actions).

==== String Concatenation ====

Technically concat support isn't needed for most libraries, like an ORM or Query Builder, where their methods nearly always take small literal strings. But it does make the adoption of //is_literal()// easier for existing projects that are currently using string concat for their SQL/HTML/CLI/etc.

Dan Ackroyd has considered an approach that does not use string concatenation at run time. The intention was to reduce the performance impact even further; and by introducing //literal_concat()// or //literal_implode()// support functions, it would make it easier for developers to identify their mistakes.

Performance wise, I made up a test patch (not properly checked), to skip string concat at runtime, and with my own [[https://github.com/craigfrancis/php-is-literal-rfc/tree/main/tests|simplistic testing]] the [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/tests/results/with-concat/local.pdf|results]] found:

    Laravel Demo App: +0.30% with, vs +0.18% without concat.
    Symfony Demo App: +0.06% with, vs +0.06% without concat.
    My Concat Test:   +4.36% with, vs +2.23% without concat.
    -
    Website with 22 SQL queries: Inconclusive, too variable.

There is still a small impact without concat support as the //concat_function()// in "zend_operators.c" uses //zend_string_extend()// needs to remove the literal flag. And in "zend_vm_def.h", it has a similar version; and supports a quick concat with an empty string, which doesn't create a new variable (x2) and would need its flag removed as well.

Also, supporting runtime concat would make //is_literal()// easier to understand, as the compiler can sometimes concat strings (making a single literal), making it appear that concat works in some cases but not others.

==== String Splitting ====

This behaviour is very different to String Concatenation.

Taking "trusted" to mean that it came from the developer - Splitting a trusted string with something that is not trusted (e.g. length), creates an untrusted modification.

Or in other words; trying to determine if the //is_literal// flag should be passed through functions like //substr()// is difficult. Having a security feature be difficult to reason about, gives a much higher chance of mistakes.

Also, we cannot find any use-cases.

==== Support Functions ====

Dan Ackroyd proposed the //literal_concat()// and //literal_implode()// functions, which can be created as userland functions.

Developers may want to use these to help identify exactly where mistakes are made, for example:

<code php>
$sortOrder = 'ASC';

// 300 lines of code, or multiple function calls

$sql .= ' ORDER BY name ' . $sortOrder;

// 300 lines of code, or multiple function calls

$db->query($sql);
</code>

If a developer changed the literal //'ASC'// to //$_GET['order']//, the error would be noticed by //$db->query()//, but it's not clear where the mistake was made. Whereas, if they were using //literal_concat()//, it could raise an exception much earlier, and highlight exactly where the mistake happened:

<code php>
$sql = literal_concat($sql, ' ORDER BY name ', $sortOrder);
</code>

==== Int/Float/Boolean Values. ====

When converting to string, they aren't guaranteed (and often don't) have the exact same value they have in source code. e.g. //TRUE// and //true// when cast to string give "1". It's also a very low value feature.

==== Reflection API ====

The Reflection API currently allows you to "introspect classes, interfaces, functions, methods and extensions"; it's not currently set up for object methods to inspect the code calling it. Even if that was to be added (unlikely), it could only check if the literal was defined there, it couldn't handle variables (tracking back to their source), nor could it provide any future scope for these checks happening in native functions (see "Phase 2").

===== Backward Incompatible Changes =====

No known BC breaks, except for code-bases that already contain the userland function //is_literal()// which is unlikely.

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

As noted by MarkR, the biggest benefit will come when it can be used by PDO and similar functions (//mysqli_query//, //preg_match//, //exec//, etc). An overview is mentioned at the end of the Introduction, aka **Phase 2**.

And, for a bit of silliness (Spaß ist verboten), MarkR would like a //is_figurative()// function (I have no idea what it would be used for).

===== Proposed Voting Choices =====

Accept the RFC. Yes/No

===== Implementation =====

[[https://github.com/php/php-src/compare/master...krakjoe:literals|Joe Watkin's implementation]] applies the literal flags, and supports string concat at runtime.

[[https://github.com/php/php-src/compare/master...Danack:is_literal_attempt_two|Dan Ackroyd's implementation]] provides //literal_concat()// and //literal_implode()//.

===== References =====

N/A

===== Rejected Features =====

N/A

===== Thanks =====

  - **Dan Ackroyd**, DanAck, for starting the first implementation (which made this a reality), and followup on how it should work.
  - **Joe Watkins**, krakjoe, for finding how to set the literal flag, and creating the implementation that supports string concat.
  - **Máté Kocsis**, mate-kocsis, for setting up and doing the performance testing.
  - **Rowan Tommins**, IMSoP, for re-writing this RFC to focus on the key features, and putting it in context of how it can be used by libraries.
  - **Nikita Popov**, NikiC, for suggesting where the literal flag could be stored. Initially this was going to be the "GC_PROTECTED flag for strings", which allowed Dan to start the first implementation.
  - **Mark Randall**, MarkR, for suggestions, and noting that "interned strings in PHP have a flag", which started the conversation on how this could be implemented.
  - **Xinchen Hui**, who created the Taint Extension, allowing me to test the idea; and noting how Taint in PHP5 was complex, but "with PHP7's new zend_string, and string flags, the implementation will become easier" [[https://news-web.php.net/php.internals/87396|source]].
