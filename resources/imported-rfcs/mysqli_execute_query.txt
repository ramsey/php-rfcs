====== PHP RFC: MySQLi Execute Query ======

  * Version: 0.1
  * Voting Start: ?
  * Voting End: ?
  * RFC Started: 2022-04-21
  * RFC Updated: 2022-04-21
  * Author: Kamil Tekiela, and Craig Francis [craig#at#craigfrancis.co.uk]
  * Status: Under Discussion
  * First Published at: https://wiki.php.net/rfc/mysqli_execute_query
  * GitHub Repo: https://github.com/craigfrancis/php-mysqli-execute-query-rfc
  * Implementation: [[https://github.com/php/php-src/compare/master...kamil-tekiela:execute_query|From Kamil Tekiela]] (proof of concept)

===== Introduction =====

Make parameterised MySQLi queries easier, with //mysqli_execute_query($sql, $params)//.

This will further reduce the complexity of using parameterised queries - making it easier for developers to move away from //mysqli_query()//, and the dangerous/risky escaping of user values.

===== Proposal =====

This new function is a simple combination of:

  - //mysqli_prepare()//
  - //mysqli_execute()//
  - //mysqli_stmt_get_result()//

It follows the original Draft RFC [[https://wiki.php.net/rfc/mysqli_execute_parameters|MySQLi Execute with Parameters]], which proposed a single function to execute a parameterised query; and the Implemented RFC [[https://wiki.php.net/rfc/mysqli_bind_in_execute|mysqli bind in execute]], which addressed the difficulties with //bind_param()//.

Using an example, assume we start with:

<code php>
$db = new mysqli('localhost', 'user', 'password', 'database');

$sql = 'SELECT * FROM user WHERE name LIKE ? AND type IN (?, ?)';

$name = '%a%';
$type1 = 'admin';
$type2 = 'editor';
</code>

Before PHP 8.1, a typical parameterised query would look like this:

<code php>
$statement = $db->prepare($sql);
$statement->bind_param('sss', $name, $type1, $type2);
$statement->execute();

foreach ($statement->get_result() as $row) {
    print_r($row);
}
</code>

Since PHP 8.1, we no longer have problems with binding by reference, or needing to specify the variable types via the first argument to //bind_param()//, e.g.

<code php>
$statement = $db->prepare($sql);
$statement->execute([$name, $type1, $type2]);

foreach ($statement->get_result() as $row) {
    print_r($row);
}
</code>

The proposed function will simplify this even further, by allowing developers to write this in a one line foreach:

<code php>
foreach ($db->execute_query($sql, [$name, $type1, $type2]) as $row) {
    print_r($row);
}
</code>

In pseudo-code it's basically:

<code php>
function mysqli_execute_query(mysqli $mysqli, string $sql, array $params = null)
{
    $driver = new mysqli_driver();

    $stmt = $mysqli->prepare($sql);
    if (!($driver->report_mode & MYSQLI_REPORT_STRICT) && $mysqli->error) {
        return false;
    }

    $stmt->execute($params);
    if (!($driver->report_mode & MYSQLI_REPORT_STRICT) && $stmt->error) {
        return false;
    }

    return $stmt->get_result();
}
</code>

===== Notes =====

==== Function Name ====

The name was inspired by [[https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/data-retrieval-and-manipulation.html#executequery|Doctrine\DBAL\Connection::executeQuery()]].

==== Returning false ====

Because the implementation is effectively calling [[https://www.php.net/mysqli_stmt_get_result|mysqli_stmt_get_result()]] last, while it will return //false// on failure, it will also return //false// for queries that do not produce a result set (e.g. //UPDATE//). Historically this has been addressed by using //mysqli_errno()//, but since 8.1 the [[https://wiki.php.net/rfc/mysqli_default_errmode|Change Default mysqli Error Mode RFC]] was accepted, and Exceptions are used by default.

==== Re-using Statements ====

The implementation discards the //mysqli_stmt// object immediately, so you cannot re-issue a statement with new parameters. This is a rarely used feature, and anyone who would benefit from this (to skip running prepare again), can still use //mysqli_prepare()//.

==== Updating Existing Functions ====

Cannot change //mysqli_query()// because it's second argument is //$resultmode//.

Cannot replace the deprecated //mysqli_execute()// function, which is an alias for //mysqli_stmt_execute()//, because it would create a backwards compatibility issue.

==== Why Now ====

Because the [[https://wiki.php.net/rfc/mysqli_support_for_libmysql|Remove support for libmysql from mysqli RFC]] has been accepted, it makes it much easier to implement with //mysqlnd//.

===== Backward Incompatible Changes =====

None

===== Proposed PHP Version(s) =====

PHP 8.2

===== RFC Impact =====

==== To SAPIs ====

None known

==== To Existing Extensions ====

  - mysqli, adding a new function.

==== To Opcache ====

None known

==== New Constants ====

None

==== php.ini Defaults ====

None

===== Open Issues =====

==== Affected Rows ====

Currently //$mysqli->affected_rows// and //mysqli_affected_rows($mysqli)// returns -1.

==== Properties ====

Because [[https://www.php.net/manual/en/class.mysqli-stmt.php|mysqli_stmt]] is not returned, it's not possible to use its properties:

  - int|string **$affected_rows** - see above
  - int|string **$insert_id** - can use //$mysqli->insert_id// or //mysqli_insert_id($mysqli)//
  - int|string **$num_rows** - also available on //mysqli_result//
  - int **$param_count**
  - int **$field_count** - also available on //mysqli_result//
  - int **$errno** - can use //mysqli_errno($mysqli)//, //$mysqli->errno//
  - string **$error** - can use //mysqli_error($mysqli)//, //$mysqli->error//
  - array **$error_list** - can use //mysqli_error_list($mysqli)//, //$mysqli->error_list//
  - string **$sqlstate** - can use //mysqli_sqlstate($mysqli)//, //$mysqli->sqlstate//
  - int **$id**

===== Unaffected PHP Functionality =====

N/A

===== Future Scope =====

N/A

===== Voting =====

Accept the RFC

TODO

===== Implementation =====

[[https://github.com/php/php-src/compare/master...kamil-tekiela:execute_query|From Kamil Tekiela]] (proof of concept)

===== References =====

N/A

===== Rejected Features =====

TODO
