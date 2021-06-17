====== PHP RFC: Is Literal Check ======

  * Version: 0.8
  * Date: 2020-03-21
  * Updated: 2021-06-06
  * Author: Craig Francis, craig#at#craigfrancis.co.uk
  * Contributors: Joe Watkins, Dan Ackroyd, Máté Kocsis
  * Status: Under Discussion
  * First Published at: https://wiki.php.net/rfc/is_literal
  * GitHub Repo: https://github.com/craigfrancis/php-is-literal-rfc

===== Introduction =====

Add the function //is_literal(string $string)//, so strings can be tested to ensure they were written by the developer (defined in the PHP source code, not containing any user input).

This flag provides a lightweight, simple, and very effective way to identify common Injection Vulnerabilities.

It avoids the "false sense of security" that comes with the flawed "Taint Checking" approach, [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/escaping.php?ts=4|because escaping is very difficult to get right]].

Developers should not escape anything themselves; they should use parameterised queries, and/or well-tested libraries.

These libraries require certain sensitive strings to only come from the developer; but because it's [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/mistakes.php?ts=4|easy to incorrectly include user values]], Injection Vulnerabilities are still introduced by the thousands of developers using these libraries incorrectly. You will notice the linked examples are based on examples found in the Libraries' official documentation, they still "work", and are typically shorter/easier than doing it correctly (I've found many of them on live websites, and it's why I'm here). A simple Query Builder example being:

<code php>
$qb->select('u')
   ->from('User', 'u')
   ->where('u.id = ' . $_GET['id']); // INSECURE
</code>

The "Future Scope" section explains how native functions will be able to use //is_literal()//.

===== Background =====

==== The Problem ====

Injection and Cross-Site Scripting (XSS) vulnerabilities are **easy to make**, **hard to identify**, and **very common**.

We like to think every developer reads the documentation, and would never directly include (inject) user values into their SQL/HTML/CLI - but we all know that's not the case.

It's why these two issues have **always** been on the [[https://owasp.org/www-project-top-ten/|OWASP Top 10]]; a list designed to raise awareness of common issues, ranked on their prevalence, exploitability, detectability, and impact:

^  Year           ^  Injection Position  ^  XSS Position  ^
|  2017 - Latest  |  **1**               |  7             |
|  2013           |  **1**               |  3             |
|  2010           |  **1**               |  2             |
|  2007           |  2                   |  **1**         |
|  2004           |  6                   |  4             |
|  2003           |  6                   |  4             |

==== Usage Elsewhere ====

Google are already using this concept with their **Go** and **Java** libraries, and it's been very effective.

Christoph Kern (Information Security Engineer at Google) did a talk in 2016 about [[https://www.youtube.com/watch?v=ccfEu-Jj0as|Preventing Security Bugs through Software Design]] (also at [[https://www.usenix.org/conference/usenixsecurity15/symposium-program/presentation/kern|USENIX Security 2015]]), pointing out the need for developers to use libraries (like [[https://blogtitle.github.io/go-safe-html/|go-safe-html]] and [[https://github.com/google/go-safeweb/tree/master/safesql|go-safesql]]) to do the encoding, where they **only accept strings written by the developer** (literals). This ensures the thousands of developers using these libraries cannot introduce Injection Vulnerabilities.

It's been so successful Krzysztof Kotowicz (Information Security Engineer at Google, or "Web security ninja") is now adding it to **JavaScript** (details below).

==== Usage in PHP ====

Libraries would be able to use //is_literal()// immediately, allowing them to warn developers about Injection Issues as soon as they receive any non-literal strings, for example:

**Propel** (Mark Scherer): "given that this would help to more safely work with user input, I think this syntax would really help in Propel."

**RedBean** (Gabor de Mooij): "You can list RedBeanPHP as a supporter, we will implement this into the core."

**Psalm** (Matthew Brown): "I've just added support for a //literal-string// type to Psalm: https://psalm.dev/r/9440908f39"

===== Proposal =====

Add //is_literal(string $string): bool// to check if a variable contains a string defined in the PHP script.

<code php>
is_literal('Example'); // true

$a = 'Hello';
$b = 'World';

is_literal($a); // true
is_literal($a . $b); // true
is_literal("Hi $b"); // true

is_literal($_GET['id']); // false
is_literal(rand(0, 10)); // false
is_literal(sprintf('Example %d', true)); // false
is_literal('/bin/rm -rf ' . $_GET['path']); // false
is_literal('<img src=' . htmlentities($_GET['src']) . ' />'); // false
is_literal('WHERE id = ' . $db->real_escape_string($_GET['id'])); // false
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

Because so much existing code uses string concatenation, and because it does not modify what the programmer has written, concatenated literals will keep the literal flag. This includes the use of //str_repeat()//, //str_pad()//, //implode()//, //join()//, //array_pad()//, and //array_fill()//.

===== Try it =====

[[https://3v4l.org/#focus=rfc.literals|Have a play with it on 3v4l.org]]

[[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/example.php?ts=4|How it can be used by libraries]] - Notice how this example library just raises a warning, to simply let the developer know about the issue, **without breaking anything**. And it provides an //"unsafe_value"// value-object to bypass the //is_literal()// check, but none of the examples need to use it (can be useful as a temporary thing, but there are much safer/better solutions, which developers are/should already be using).

===== FAQ's =====

==== Taint Checking ====

**Taint checking is flawed, isn't this the same?** It is not the same.

Taint Checking incorrectly assumes the output of an escaping function is "safe" for a particular context. While it sounds reasonable in theory, the operation of escaping functions, and the context for which their output is safe, is very hard to define. This leads to a feature that is both complex and unreliable.

<code php>
$sql = 'SELECT * FROM users WHERE id = ' . $db->real_escape_string($id); // INSECURE
$html = "<img src=" . htmlentities($url) . " alt='' />"; // INSECURE
$html = "<a href='" . htmlentities($url) . "'>..."; // INSECURE
</code>

All three examples would be incorrectly considered "untainted". The first two need the values to be quoted. The third example, //htmlentities()// does not escape single quotes by default before PHP 8.1 ([[https://github.com/php/php-src/commit/50eca61f68815005f3b0f808578cc1ce3b4297f0|fixed]]), and it does not consider the issue of 'javascript:' URLs.

In comparison, //is_literal()// doesn't have an equivalent of //untaint()//, or support escaping. Instead PHP will set the literal flag, and as soon as the value has been manipulated or includes anything that is not from a literal (e.g. user data), the literal flag is lost.

This allows libraries to use //is_literal()// to identify when they are provided a sensitive value that should not include user input. Then it's up to the library to handle the escaping (if it's even needed). The "Future Scope" section notes how native functions will be able to use the literal flag as well.

==== Education ====

**Why not educate everyone?** You can't - developer training simply does not scale, and mistakes still happen.

We cannot expect everyone to have formal training, know everything from day 1, and consider programming a full time job. We want new programmers, with a variety of experiences, ages, and backgrounds. Everyone should be guided to do the right thing, and notified as soon as they make a mistake (we all make mistakes). We also need to acknowledge that many programmers are busy, do copy/paste code, don't necessarily understand what it does, edit it for their needs, then simply move on to their next task.

==== Static Analysis ====

**Why not use static analysis?** It will never be used by most developers.

I still agree with [[https://news-web.php.net/php.internals/109192|Tyson Andre]], you should use Static Analysis, but it's an extra step that most programmers cannot be bothered to do, especially those who are new to programming (its usage tends to be higher among those writing well-tested libraries).

Also, these tools currently focus on other issues (type checking, basic logic flaws, code formatting, etc), rarely attempting to address injection vulnerabilities. Those that do are [[https://github.com/vimeo/psalm/commit/2122e4a1756dac68a83ec3f5abfbc60331630781|often incomplete]], need sinks specified on all library methods (unlikely to happen), and are not enabled by default. For example, Psalm, even in its strictest errorLevel (1), and running //--taint-analysis// (I bet you don't use this), will not notice the missing quote marks in this SQL, and incorrectly assume it's safe:

<code php>
$db = new mysqli('...');

$id = (string) ($_GET['id'] ?? 'id'); // Keep the type checker happy.

$db->prepare('SELECT * FROM users WHERE id = ' . $db->real_escape_string($id)); // INSECURE
</code>

==== Performance ====

**What about the performance impact?** Máté Kocsis has created a [[https://github.com/kocsismate/php-version-benchmarks/|php benchmark]] to replicate the old [[https://01.org/node/3774|Intel Tests]], and the [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/tests/results/with-concat/kocsismate.pdf|preliminary testing on this implementation]] has found a 0.124% performance hit for the Laravel Demo app, and 0.161% for Symfony (rounds 4-6, which involved 5000 requests). These tests do not connect to a database, as the variability introduced makes it impossible to measure the difference.

There is a more severe 3.719% when running this [[https://github.com/kocsismate/php-version-benchmarks/blob/main/app/zend/concat.php#L25|concat test]], but this is not representative of a typical PHP script (it's not normal to concatenate 4 strings, 5 million times, with no other actions).

Joe Watkins has also noted that further optimisations are possible (the implementation has focused on making it work).

==== String Concatenation ====

**Is string concatenation supported?**

Yes. The literal flag is preserved when two literal strings are concatenated; this makes it easier to use //is_literal()//, especially by developers that use concatenation for their SQL/HTML/CLI/etc.

Previously we tried a version that only supported concatenation at compile-time (not run-time), to see if it would reduce the performance impact even further, and doing so might help developers with debugging. The idea was to require everyone to use special //literal_concat()// and //literal_implode()// functions, which would raise exceptions to highlight where mistakes were made. These two functions can still be implemented by developers themselves (see [[#support_functions|Support Functions]] below), as they can be useful; but requiring everyone to use them would have required big changes to existing projects, and exceptions are not a graceful way of handling mistakes.

Performance wise, my [[https://github.com/craigfrancis/php-is-literal-rfc/tree/main/tests|simplistic testing]] found there was still [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/tests/results/with-concat/local.pdf|a small impact without run-time concat]]:

    Laravel Demo App: +0.30% with, vs +0.18% without.
    Symfony Demo App: +0.06% with, vs +0.06% without.
    My Concat Test:   +4.36% with, vs +2.23% without.
    -
    Website with 22 SQL queries: Inconclusive, too variable.

(This is because //concat_function()// in "zend_operators.c" uses //zend_string_extend()// which needs to remove the literal flag. Also "zend_vm_def.h" does the same; and supports a quick concat with an empty string (x2), which would need its flag removed as well).

And by supporting both forms of concatenation, it makes it easier for developers to understand (many are not aware of the difference).

==== String Splitting ====

**Why don't you support string splitting?** In short, we can't find any use cases (security features should try to keep the implementation as simple as possible).

Also, the security considerations are different. Concatenation joins known/fixed units together, whereas if you're starting with a "developer created string" (which is trusted), and the program allows the evil user to split the string (e.g. setting the length in substr), then they get considerable control over the result (it creates an untrusted modification).

These are unlikely to be written by a programmer, but consider these:

<code php>
$length = ($_GET['length'] ?? -5);
$url    = substr('https://example.com/js/a.js?v=55', 0, $length);
$html   = substr('<a href="#">#</a>', 0, $length);
</code>

If that URL was used in a Content-Security-Policy, then it's necessary to remove the query string, but as more of the string is removed, the more resources can be included ("https:" basically allows resources from anywhere). With the HTML example, moving from the tag content to the attribute can be a problem (technically the HTML Templating Engine should be fine, but unfortunately libraries like Twig are not currently context aware, so you need to change from the default 'html' encoding to explicitly using 'html_attr' encoding).

Or in other words; trying to determine if the //literal// flag should be passed through functions like //substr()// is difficult. Having a security feature be difficult to reason about, gives a much higher chance of mistakes.

Krzysztof Kotowicz has confirmed that, at Google, with "go-safe-html", splitting is explicitly not supported because it "can cause issues"; for example, "arbitrary split position of a HTML string can change the context".

==== WHERE IN ====

**What about an undefined number of parameters, e.g. //WHERE id IN (?, ?, ?)//?** You should already be following the advice from [[https://stackoverflow.com/a/23641033/538216|Levi Morrison]], [[https://www.php.net/manual/en/pdostatement.execute.php#example-1012|PDO Execute]], and [[https://www.drupal.org/docs/7/security/writing-secure-code/database-access#s-multiple-arguments|Drupal Multiple Arguments]]:

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

This pushes everyone to use parameters properly; rather than using implode() on user values, and including them directly in the SQL (which is easy to get wrong).

==== Non-Parameterised Values ====

**How can this work with Table and Field names in SQL, which cannot use parameters?** They are often in variables written as literals anyway (so no changes needed); and if they are dependent on user input, you //can// and //should// use literals:

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

==== Non-Literal Values ====

**How does this work in cases where you can't use literals?**

For example [[https://news-web.php.net/php.internals/87667|Dennis Birkholz]] noted that some Systems/Frameworks currently define some variables (e.g. table name prefixes) without the use of a literal (e.g. ini/json/yaml).

And Larry Garfield noted that in Drupal's ORM "the table name itself is user-defined" (not in the PHP script).

While most systems can use literals entirely, these special non-literal values should still be handled separately (and carefully). This approach allows the library to ensure the majority of the input (SQL) is a literal, then it can consistently check/escape those special values (e.g. does it match a valid table/field name, which can be included safely).

[[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/example.php?ts=4#L194|How this can be done with aliases]], or the [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/example.php?ts=4#L229|example Query Builder]].

==== Usage by Libraries ====

**Could libraries use is_literal() internally?** Yes, they could.

It would be fantastic if they did use additional //is_literal()// checks after receiving the strings from developers (it ensures they haven't introduced a vulnerability either); but this isn't a priority, simply because libraries are rarely the source of Injection Vulnerabilities.

That said, consider the Drupalgeddon vulnerability; where //$db->expandArguments()// allowed unsafe/non-literal values to be used as placeholders with //IN (:arg_0, :arg_1)//. By using something like the [[https://github.com/craigfrancis/php-is-literal-rfc/blob/main/justification/example.php?ts=4#L229|example Query Builder]], //is_literal()// would have been used to check the raw SQL, and the field/parameter names (which are not literals in this case) get checked and appended separately.

Zend also had a couple of issues with ORDER BY, where it didn't check the inputs either ([[https://framework.zend.com/security/advisory/ZF2014-04|1]]/[[https://framework.zend.com/security/advisory/ZF2016-03|2]]).

==== Naming ====

**Why is it called is_literal()?** A "Literal String" is the standard name for strings in source code. See [[https://www.google.com/search?q=what+is+literal+string+in+php|Google]].

> A string literal is the notation for representing a string value within the text of a computer program. In PHP, strings can be created with single quotes, double quotes or using the heredoc or the nowdoc syntax...

Alternative suggestions have included //is_from_literal()// from [[https://news-web.php.net/php.internals/109197|Jakob Givoni]], and references to alternative implementations like "compile time constants" and "code string".

We cannot call it //is_safe_string()//, because we cannot say that a string is safe:

<code php>
$cli = 'rm -rf ?';
$sql = 'DELETE FROM my_table WHERE my_date >= ?';
eval('$name = "' . $_GET['name'] . '";'); // INSECURE
</code>

While the first two cannot include Injection Vulnerabilities, the parameters could be set to "/" or "0000-00-00" (providing a nice vanishing magic trick); and the last one, well, they have much bigger issues to worry about (it's clearly irresponsible, and intentionally dangerous).

Also, this name is unlikely to clash with any userland functions.

==== Integer Values ====

**Can you support Integer values?** This is being considered.

Matthew Brown wants to support integer values, simply because so much code already includes them, and we cannot find a single way that integers can cause issues from an Injection Vulnerability point of view (but if anyone can, we absolutely welcome their input).

==== Other Values ====

**Why don't you support Boolean/Float values?** It's a very low value feature, and we cannot be sure of the security implications.

For example, the value you put in often is not always the same as what you get out:

<code php>
var_dump((string) true);  // "1"
var_dump((string) false); // ""
var_dump(2.3 * 100);      // 229.99999999999997

setlocale(LC_ALL, 'de_DE.UTF-8');
var_dump(sprintf('%.3f', 1.23)); // "1,230"
 // Note the comma, which can be bad for SQL.
 // Pre 8.0 this also happened with string casting.
</code>

==== Support Functions ====

**What about other support functions?** We did consider //literal_concat()// and //literal_implode()// functions (see [[#string_concatenation|String Concatenation]] above), but these can be userland functions:

<code php>
function literal_implode($separator, $array) {
  $return = implode($separator, $array);
  if (!is_literal($return)) {
      // You will probably only want to raise
      // an exception on your development server.
    throw new Exception('Non-literal detected!');
  }
  return $return;
}

function literal_concat(...$a) {
  return literal_implode('', $a);
}
</code>

Developers can use these to help identify exactly where they made a mistake, for example:

<code php>
$sortOrder = 'ASC';

// 300 lines of code, or multiple function calls

$sql .= ' ORDER BY name ' . $sortOrder;

// 300 lines of code, or multiple function calls

$db->query($sql);
</code>

If a developer changed the literal //'ASC'// to //$_GET['order']//, the error would be noticed by //$db->query()//, but it's not clear where the non-literal value was introduced. Whereas, if they used //literal_concat()//, that would raise an exception much earlier, and highlight exactly where the mistake happened:

<code php>
$sql = literal_concat($sql, ' ORDER BY name ', $sortOrder);
</code>

==== Other Functions ====

**Why not support other string functions?** We might do, but like [[#string_splitting|String Splitting]], we can't find any use cases, and don't want to make this complicated (just identifying strings defined in the PHP source code). For example //strtoupper()// might be reasonable, but we will need to consider how it would be used (good and bad), and check for any oddities (e.g. output varying based on the current locale). Also, functions like //str_shuffle()// create unpredictable results.

==== Faking it ====

**What happens if I really want a non-literal to appear as one?**

This implementation does not provide a way for a developer to mark anything they want as a literal. This is on purpose. We do not want to recreate the biggest flaw of Taint Checking. It would be very easy for a naive developer to mark escaped values as a literal, incorrectly seeing this as a "safe" flag.

That said, we do not pretend there aren't ways around this (e.g. using var_export), but doing so is clearly the developer doing something wrong. We want to provide safety rails, there is nothing stopping the developer from jumping over them.

==== Extensions ====

**Extensions create and manipulate strings, won't this break the literal flag?** Strings have multiple flags already, and are off by default, this is the correct behaviour when extensions create their own strings (should not be considered a literal). If an extension is found to be changing the literal flag incorrectly (unlikely), that's the same as any new flag being introduced, and will need to be fixed in the same way.

==== Reflection API ====

**Why don't you use the reflection API?** It currently allows you to "introspect classes, interfaces, functions, methods and extensions"; it's not currently set up for object methods to inspect the code calling it. Even if that was to be added (unlikely), it could only check if the literal was defined there, it couldn't handle variables (tracking back to their source), nor could it provide any future scope for these checks happening in native functions (see "Future Scope").

===== Previous Work =====

**Go** programs can use "ScriptFromConstant" to express the concept of a "compile time constant" ([[https://blogtitle.github.io/go-safe-html/|more details]]).

**Java** can use [[https://errorprone.info/|Error Prone]] with [[https://errorprone.info/bugpattern/CompileTimeConstant|@CompileTimeConstant]] to ensure method parameters can only use "compile-time constant expressions".

**JavaScript** is getting [[https://github.com/tc39/proposal-array-is-template-object|isTemplateObject]], for "Distinguishing strings from a trusted developer from strings that may be attacker controlled" (intended to be [[https://github.com/mikewest/tc39-proposal-literals|used with Trusted Types]]).

**Perl** has a [[https://perldoc.perl.org/perlsec#Taint-mode|Taint Mode]], via the -T flag, where all input is marked as "tainted", and cannot be used by some methods (like commands that modify files), unless you use a regular expression to match and return known-good values (where regular expressions are easy to get wrong).

There is a [[https://github.com/laruence/taint|Taint extension for PHP]] by Xinchen Hui, and [[https://wiki.php.net/rfc/taint|a previous RFC proposing it be added to the language]] by Wietse Venema.

And there is the [[https://wiki.php.net/rfc/sql_injection_protection|Automatic SQL Injection Protection]] RFC by Matt Tait (this RFC uses a similar concept of the [[https://wiki.php.net/rfc/sql_injection_protection#safeconst|SafeConst]]). When Matt's RFC was being discussed, it was noted:

  * "unfiltered input can affect way more than only SQL" ([[https://news-web.php.net/php.internals/87355|Pierre Joye]]);
  * this amount of work isn't ideal for "just for one use case" ([[https://news-web.php.net/php.internals/87647|Julien Pauli]]);
  * It would have effected every SQL function, such as //mysqli_query()//, //$pdo->query()//, //odbc_exec()//, etc (concerns raised by [[https://news-web.php.net/php.internals/87436|Lester Caine]] and [[https://news-web.php.net/php.internals/87650|Anthony Ferrara]]);
  * Each of those functions would need a bypass for cases where unsafe SQL was intentionally being used (e.g. phpMyAdmin taking SQL from POST data) because some applications intentionally "pass raw, user submitted, SQL" (Ronald Chmara [[https://news-web.php.net/php.internals/87406|1]]/[[https://news-web.php.net/php.internals/87446|2]]).

All of these concerns have been addressed by //is_literal()//.

I also agree with [[https://news-web.php.net/php.internals/87400|Scott Arciszewski]], "SQL injection is almost a solved problem [by using] prepared statements", where //is_literal()// is essential for identifying the mistakes developers are still making.

===== Backward Incompatible Changes =====

No known BC breaks, except for code-bases that already contain the userland function //is_literal()// which is unlikely.

===== Proposed PHP Version(s) =====

PHP 8.1

===== RFC Impact =====

==== To SAPIs ====

None known

==== To Existing Extensions ====

None known

==== To Opcache ====

None known

===== Open Issues =====

  - Supporting Integers/Interned values.
  - The name.

===== Unaffected PHP Functionality =====

None known

===== Future Scope =====

1) As noted by someniatko and Matthew Brown, having a dedicated type would be useful in the future, as "it would serve clearer intent", which can be used by IDEs, Static Analysis, etc. It was agreed to do via a separate RFC as it leads into the next point...

2) As noted by MarkR, the biggest benefit will come when this flag can be used by PDO and similar functions (//mysqli_query//, //preg_match//, //exec//, etc).

First we need libraries to start using //is_literal()// to check their inputs, and use the appropriate escaping. This can result in strings that are no longer literals, but can still be trusted.

Then, with a future RFC, we can introduce checks for the native functions. By using the [[https://web.dev/trusted-types/|Trusted Types]] concept from JavaScript (which protects [[https://www.youtube.com/watch?v=po6GumtHRmU&t=92s|60+ Injection Sinks]], like innerHTML), the libraries create a stringable object as their output. These objects would be marked as "trusted" (because the library is sure they do not contain any Injection Vulnerabilities). The native functions can then **warn** developers when they do not receive a literal, or one of these trusted objects. These warnings would not **break anything**, they just make developers aware of the mistakes they have made.

===== Proposed Voting Choices =====

Accept the RFC. Yes/No

===== Implementation =====

[[https://github.com/php/php-src/compare/master...krakjoe:literals|Joe Watkin's implementation]]

===== References =====

N/A

===== Rejected Features =====

N/A

===== Thanks =====

  - **Dan Ackroyd**, DanAck, for starting the [[https://github.com/php/php-src/compare/master...Danack:is_literal_attempt_two|first implementation]], which made this a reality, providing //literal_concat()// and //literal_implode()//, and followup on how it should work.
  - **Joe Watkins**, krakjoe, for finding how to set the literal flag, and creating the implementation that supports string concat.
  - **Máté Kocsis**, mate-kocsis, for setting up and doing the performance testing.
  - **Xinchen Hui**, who created the Taint Extension, allowing me to test the idea; and noting how Taint in PHP5 was complex, but "with PHP7's new zend_string, and string flags, the implementation will become easier" [[https://news-web.php.net/php.internals/87396|source]].
  - **Rowan Francis**, for proof-reading, and helping me make an RFC that contains readable English.
  - **Rowan Tommins**, IMSoP, for re-writing this RFC to focus on the key features, and putting it in context of how it can be used by libraries.
  - **Nikita Popov**, NikiC, for suggesting where the literal flag could be stored. Initially this was going to be the "GC_PROTECTED flag for strings", which allowed Dan to start the first implementation.
  - **Mark Randall**, MarkR, for suggestions, and noting that "interned strings in PHP have a flag", which started the conversation on how this could be implemented.
  - **Sara Golemon**, SaraMG, for noting how this RFC had to explain how //is_literal()// is different to the flawed Taint Checking approach, so we don't get "a false sense of security or require far too much escape hatching".
