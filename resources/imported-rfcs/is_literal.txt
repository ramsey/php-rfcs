====== PHP RFC: Is Literal Check ======

  * Version: 0.2
  * Date: 2020-03-21
  * Updated: 2020-12-22
  * Author: Craig Francis, craig#at#craigfrancis.co.uk
  * Status: Draft
  * First Published at: https://wiki.php.net/rfc/is_literal
  * GitHub Repo: https://github.com/craigfrancis/php-is-literal-rfc

===== Introduction =====

Add an //is_literal()// function, so developers/frameworks can check if a given variable is **safe**.

As in, at runtime, being able to check if a variable has been created by literals, defined within a PHP script, by a trusted developer.

This simple check can be used to warn or completely block SQL Injection, Command Line Injection, and many cases of HTML Injection (aka XSS).

===== The Problem =====

Escaping strings for SQL, HTML, Commands, etc is **very** error prone.

The vast majority of programmers should never do this (mistakes will be made).

Unsafe values (often user supplied) **must** be kept separate (e.g. parameterised SQL), or be processed by something that understands the context (e.g. a HTML Templating Engine).

This is primarily for security reasons, but it can also cause data to be damaged (e.g. ASCII/UTF-8 issues).

Take these mistakes, where the value has come from the user:

<code php>
echo "<img src=" . $url . " alt='' />";
</code>

Flawed, and unfortunately very common, a classic XSS vulnerability.

<code php>
echo "<img src=" . htmlentities($url) . " alt='' />";
</code>

Flawed because the attribute value is not quoted, e.g. //$url = '/ onerror=alert(1)'//

<code php>
echo "<img src='" . htmlentities($url) . "' alt='' />";
</code>

Flawed because //htmlentities()// does not encode single quotes by default, e.g. //$url = "/' onerror='alert(1)"//

<code php>
echo '<a href="' . htmlentities($url) . '">Link</a>';
</code>

Flawed because a link can include JavaScript, e.g. //$url = 'javascript:alert(1)'//

<code html>
<script>
  var url = "<?= addslashes($url) ?>";
</script>
</code>

Flawed because //addslashes()// is not HTML context aware, e.g. //$url = '</script><script>alert(1)</script>'//

<code php>
echo '<a href="/path/?name=' . htmlentities($name) . '">Link</a>';
</code>

Flawed because //urlencode()// has not been used, e.g. //$name = 'A&B'//

<code html>
<p><?= htmlentities($url) ?></p>
</code>

Flawed because the encoding is not guaranteed to be UTF-8 (or ISO-8859-1 before PHP 5.4), so the value could be corrupted.

Also flawed because some browsers (e.g. IE 11), if the charset isn't defined (header or meta tag), could guess the output as UTF-7, e.g. //$url = '+ADw-script+AD4-alert(1)+ADw-+AC8-script+AD4-'//

<code php>
example.html:
    <img src={{ url }} alt='' />

$loader = new \Twig\Loader\FilesystemLoader('./templates/');
$twig = new \Twig\Environment($loader, ['autoescape' => 'name']);

echo $twig->render('example.html', ['url' => $url]);
</code>

Flawed because Twig is not context aware (in this case, an unquoted HTML attribute), e.g. //$url = '/ onerror=alert(1)'//

<code php>
$sql = 'SELECT 1 FROM user WHERE id=' . $mysqli->escape_string($id);
</code>

Flawed because the value has not been quoted, e.g. //$id = 'id', or '1 OR 1=1'//

<code php>
$sql = 'SELECT 1 FROM user WHERE id="' . $mysqli->escape_string($id) . '"';
</code>

Flawed if 'sql_mode' includes //NO_BACKSLASH_ESCAPES//, e.g. //$id = '2" or "1"="1'//

<code php>
$sql = 'INSERT INTO user (name) VALUES ("' . $mysqli->escape_string($name) . '")';
</code>

Flawed if 'SET NAMES latin1' has been used, and escape_string() uses 'utf8'.

<code php>
$parameters = "-f$email";

// $parameters = '-f' . escapeshellarg($email);

mail('a@example.com', 'Subject', 'Message', NULL, $parameters);
</code>

Flawed because it's not possible to safely escape values in //$additional_parameters// for //mail()//, e.g. //$email = 'b@example.com -X/www/example.php'//

===== Previous Solutions =====

[[https://github.com/laruence/taint|Taint extension]] by Xinchen Hui, but this approach explicitly allows escaping, which doesn't address the issues listed above.

[[https://wiki.php.net/rfc/sql_injection_protection|Automatic SQL Injection Protection]] by Matt Tait, where it was noted:

  * "unfiltered input can affect way more than only SQL" ([[https://news-web.php.net/php.internals/87355|Pierre Joye]]);
  * this amount of work isn't ideal for "just for one use case" ([[https://news-web.php.net/php.internals/87647|Julien Pauli]]);
  * It would have effected every SQL function, such as //mysqli_query()//, //$pdo->query()//, //odbc_exec()//, etc (concerns raised by [[https://news-web.php.net/php.internals/87436|Lester Caine]] and [[https://news-web.php.net/php.internals/87650|Anthony Ferrara]]);
  * Each of those functions would need a bypass for cases where unsafe SQL was intentionally being used (e.g. phpMyAdmin taking SQL from POST data) because some applications intentionally "pass raw, user submitted, SQL" (Ronald Chmara [[https://news-web.php.net/php.internals/87406|1]]/[[https://news-web.php.net/php.internals/87446|2]]).

I also agree that "SQL injection is almost a solved problem [by using] prepared statements" ([[https://news-web.php.net/php.internals/87400|Scott Arciszewski]]), but we still need something to identify mistakes.

===== Related Go Implementation =====

As discussed by [[https://www.usenix.org/conference/usenixsecurity15/symposium-program/presentation/kern|Christoph Kern (Google) in 2015]], this approach works.

The Go language can do this by checking for "compile time constants", which isn't as good as a run time solution (e.g. the //WHERE IN// issue), but it does work.

Google currently avoids most issues by using [[https://blogtitle.github.io/go-safe-html/|go-safe-html]] and [[https://github.com/google/go-safeweb/tree/master/safesql|safesql]].

===== Related JavaScript Implementation =====

This RFC is taking some ideas from TC39, where a similar idea is being discussed for JavaScript, to support the introduction of Trusted Types.

https://github.com/tc39/proposal-array-is-template-object\\
https://github.com/mikewest/tc39-proposal-literals

They are looking at "Distinguishing strings from a trusted developer, from strings that may be attacker controlled".

===== Solution =====

Literals are safe values, defined within the PHP scripts, for example:

<code php>
$a = 'Example';
is_literal($a); // true

$a = 'Example ' . $a . ', ' . 5;
is_literal($a); // true

$a = 'Example ' . $_GET['id'];
is_literal($a); // false

$a = 'Example ' . time();
is_literal($a); // false

$a = sprintf('LIMIT %d', 3);
is_literal($a); // false

$c = count($ids);
$a = 'WHERE id IN (' . implode(',', array_fill(0, $c, '?')) . ')';
is_literal($a); // true, the odd one that involves functions.

$limit = 10;
$a = 'LIMIT ' . ($limit + 1);
is_literal($a); // false, but might need some discussion.
</code>

This uses a similar definition of [[https://wiki.php.net/rfc/sql_injection_protection#safeconst|SafeConst]] from Matt Tait's RFC, but it does not need to accept Integer or FloatingPoint variables as safe (unless it makes the implementation easier), nor should this proposal effect any existing functions.

Thanks to [[https://news-web.php.net/php.internals/87396|Xinchen Hui]], we know the PHP5 Taint extension was complex, but "with PHP7's new zend_string, and string flags, the implementation will become easier".

And thanks to [[https://chat.stackoverflow.com/transcript/message/48927813#48927813|Mark R]], it might be possible to use the fact that "interned strings in PHP have a flag", which is there because these "can't be freed".

Commands can be checked to ensure they are a "programmer supplied constant/static/validated string", and all other unsafe variables are provided separately (as noted by [[https://news-web.php.net/php.internals/87725|Yasuo Ohgaki]]).

This approach allows all systems/frameworks to decide if they want to **block**, **educate** (via a notice), or **ignore** these issues (to avoid the "don't nanny" concern raised by [[https://news-web.php.net/php.internals/87383|Lester Caine]]).

Unlike the Taint extension, there must **not** be an equivalent //untaint()// function, or support any kind of escaping.

==== Solution: SQL Injection ====

Database abstractions (e.g. ORMs) will be able to ensure they are provided with strings that are safe.

[[https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/query-builder.html#high-level-api-methods|Doctrine]] could use this to ensure //->where($predicates)// is a literal:

<code php>
$users = $queryBuilder
  ->select('u')
  ->from('User', 'u')
  ->where('u.id = ' . $_GET['id'])
  ->getQuery()
  ->getResult();

// example.php?id=u.id
</code>

This mistake can be easily identified by:

<code php>
public function where($predicates)
{
    if (function_exists('is_literal') && !is_literal($predicates)) {
        throw new Exception('->where() can only accept a literal');
    }
    ...
}
</code>

[[https://redbeanphp.com/index.php?p=/finding|RedBean]] could check //$sql// is a literal:

<code php>
$users = R::find('user', 'id = ' . $_GET['id']);
</code>

[[http://propelorm.org/Propel/reference/model-criteria.html#relational-api|PropelORM]] could check //$clause// is a literal:

<code php>
$users = UserQuery::create()->where('id = ' . $_GET['id'])->find();
</code>

The //is_literal()// function could also be used internally by ORM developers, so they can be sure they have created their SQL strings out of literals. This would avoid mistakes such as the ORDER BY issues in the Zend framework [[https://framework.zend.com/security/advisory/ZF2014-04|1]]/[[https://framework.zend.com/security/advisory/ZF2016-03|2]].

==== Solution: SQL Injection, Basic ====

A simple example:

<code php>
$sql = 'SELECT * FROM table WHERE id = ?';

$result = $db->exec($sql, [$id]);
</code>

Checked in the framework by:

<code php>
class db {

  public function exec($sql, $parameters = []) {

    if (!is_literal($sql)) {
      throw new Exception('SQL must be a literal.');
    }

    $statement = $this->pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();

  }

}
</code>

This also works with string concatenation:

<code php>
define('TABLE', 'example');

$sql = 'SELECT * FROM ' . TABLE . ' WHERE id = ?';

  is_literal($sql); // Returns true

$sql .= ' AND id = ' . $mysqli->escape_string($_GET['id']);

  is_literal($sql); // Returns false
</code>

==== Solution: SQL Injection, ORDER BY ====

To ensure //ORDER BY// can be set via the user, but only use acceptable values:

<code php>
$order_fields = [
    'name',
    'created',
    'admin',
  ];

$order_id = array_search(($_GET['sort'] ?? NULL), $order_fields);

$sql = ' ORDER BY ' . $order_fields[$order_id];
</code>

==== Solution: SQL Injection, WHERE IN ====

Most SQL strings can be a simple concatenations of literal values, but //WHERE x IN (?,?,?)// needs to use a variable number of literal placeholders.

There needs to be a special case for //array_fill()//+//implode()//, so the //is_literal// state can be preserved, allowing us to create the safe literal string '?,?,?':

<code php>
$in_sql = implode(',', array_fill(0, count($ids), '?'));

$sql = 'SELECT * FROM table WHERE id IN (' . $in_sql . ')';
</code>

==== Solution: CLI Injection ====

Rather than using functions such as:

  * //exec()//
  * //shell_exec()//
  * //system()//
  * //passthru()//

Frameworks (or PHP) could introduce something similar to //pcntl_exec()//, where arguments are provided separately.

Or, take a safe literal for the command, and use parameters for the arguments (like SQL does):

<code php>
$output = parameterised_exec('grep ? /path/to/file | wc -l', [
    'example',
  ]);
</code>

Rough implementation:

<code php>
function parameterised_exec($cmd, $args = []) {

  if (!is_literal($cmd)) {
    throw new Exception('The first argument must be a literal');
  }

  $offset = 0;
  $k = 0;
  while (($pos = strpos($cmd, '?', $offset)) !== false) {
    if (!isset($args[$k])) {
      throw new Exception('Missing parameter "' . ($k + 1) . '"');
      exit();
    }
    $arg = escapeshellarg($args[$k]);
    $cmd = substr($cmd, 0, $pos) . $arg . substr($cmd, ($pos + 1));
    $offset = ($pos + strlen($arg));
    $k++;
  }
  if (isset($args[$k])) {
    throw new Exception('Unused parameter "' . ($k + 1) . '"');
    exit();
  }

  return exec($cmd);

}
</code>

==== Solution: HTML Injection ====

Template engines should receive variables separately from the raw HTML.

Often the engine will get the HTML from static files (safe):

<code php>
$html = file_get_contents('/path/to/template.html');
</code>

But small snippets of HTML are often easier to define as a literal within the PHP script:

<code php>
$template_html = '
  <p>Hello <span id="username"></span></p>
  <p><a>Website</a></p>';
</code>

Where the variables are supplied separately, in this example I'm using XPath:

<code php>
$values = [
    '//span[@id="username"]' => [
        NULL      => 'Name', // The textContent
        'class'   => 'admin',
        'data-id' => '123',
      ],
    '//a' => [
        'href' => 'https://example.com',
      ],
  ];

echo template_parse($template_html, $values);
</code>

The templating engine can then accept and apply the supplied variables for the relevant context.

As a simple example, this can be done with:

<code php>
function template_parse($html, $values) {

  if (!is_literal($html)) {
    throw new Exception('Invalid Template HTML.');
  }

  $dom = new DomDocument();
  $dom->loadHTML('<' . '?xml encoding="UTF-8">' . $html);

  $xpath = new DOMXPath($dom);

  foreach ($values as $query => $attributes) {

    if (!is_literal($query)) {
      throw new Exception('Invalid Template XPath.');
    }

    foreach ($xpath->query($query) as $element) {
      foreach ($attributes as $attribute => $value) {

        if (!is_literal($attribute)) {
          throw new Exception('Invalid Template Attribute.');
        }

        if ($attribute) {
          $safe = false;
          if ($attribute == 'href') {
            if (preg_match('/^https?:\/\//', $value)) {
              $safe = true; // Not "javascript:..."
            }
          } else if ($attribute == 'class') {
            if (in_array($value, ['admin', 'important'])) {
              $safe = true; // Only allow specific classes?
            }
          } else if (preg_match('/^data-[a-z]+$/', $attribute)) {
            if (preg_match('/^[a-z0-9 ]+$/i', $value)) {
              $safe = true;
            }
          }
          if ($safe) {
            $element->setAttribute($attribute, $value);
          }
        } else {
          $element->textContent = $value;
        }

      }
    }

  }

  $html = '';
  $body = $dom->documentElement->firstChild;
  if ($body->hasChildNodes()) {
    foreach ($body->childNodes as $node) {
      $html .= $dom->saveXML($node);
    }
  }

  return $html;

}
</code>

===== Backward Incompatible Changes =====

None

===== Proposed PHP Version(s) =====

PHP 8.1?

===== RFC Impact =====

==== To SAPIs ====

Not sure

==== To Existing Extensions ====

Not sure

==== To Opcache ====

Not sure

===== Open Issues =====

On [[https://github.com/craigfrancis/php-is-literal-rfc/issues|GitHub]]:

  - Would this cause performance issues? Presumably not as bad a type checking.
  - Can //array_fill()//+//implode()// pass though the "is_literal" flag for the "WHERE IN" case?
  - Should the function be named //is_from_literal()//? (suggestion from [[https://news-web.php.net/php.internals/109197|Jakob Givoni]])
  - Systems/Frameworks that define certain variables (e.g. table name prefixes) without the use of a literal (e.g. ini/json/yaml files), so they might need to make some changes to use this check, as originally noted by [[https://news-web.php.net/php.internals/87667|Dennis Birkholz]].

===== Alternatives =====

  - The current Taint Extension (notes above)
  - Using static analysis (not at runtime), for example [[https://psalm.dev/|psalm]] (thanks [[https://news-web.php.net/php.internals/109192|Tyson Andre]]). But I can't find any which do these checks by default (if they even try), and we can't expect all programmers to use static analysis (especially those who have just stated).

===== Unaffected PHP Functionality =====

Not sure

===== Future Scope =====

Certain functions (//mysqli_query//, //preg_match//, etc) could use this information to generate a error/warning/notice.

PHP could also have a mode where output (e.g. //echo '<html>'//) is blocked, and this can be bypassed (maybe via //ini_set//) when the HTML Templating Engine has created the correctly encoded output.

===== Proposed Voting Choices =====

N/A

===== Patches and Tests =====

A volunteer is needed to help with implementation.

===== Implementation =====

N/A

===== Rejected Features =====

N/A
