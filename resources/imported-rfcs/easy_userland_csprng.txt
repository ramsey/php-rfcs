====== PHP RFC: Easy User-land CSPRNG ======
  * Version: 0.0
  * Date: 2015-02-20
  * Author: Sammy Kaye Powers, me@sammyk.me
  * Status: Draft
  * First Published at: http://wiki.php.net/rfc/easy_userland_csprng


===== Introduction =====
This RFC proposes adding an easy user-land API for a reliable [[http://en.wikipedia.org/wiki/Cryptographically_secure_pseudorandom_number_generator|CSPRNG]] in PHP.

==== The Problem ====
PHP is particularly bad at providing CSPRNG's in user-land. Users have a few options like ''openssl_random_pseudo_bytes()'' and ''mcrypt_create_iv()'' to generate pseudo-random bytes, but unfortunately system support for these functions varies.

The ''mcrypt_create_iv()'' function is provided by the [[http://mcrypt.sourceforge.net/|MCrypt lib]] which is solid, but unmaintained. Since it is built into PHP as an extension, it might not be enabled in certain environments (like most versions of PHP on Mac OS X). The longer this lib goes unmaintained, the more likely it is to have a security hole discovered that goes unfixed. And on top of all that, [[https://twitter.com/ircmaxell/status/564919926700658689|there is a bounty on MCrypt's head]]!

The ''openssl_random_pseudo_bytes()'' function is provided by the [[https://www.openssl.org/|OpenSSL lib]] which is being actively maintained but is hugely bloated and we've seen several major security issues pop up requiring the most-up-to-date version of the lib to stay secure. Moreover, in certain configurations, ''openssl_random_pseudo_bytes()'' will return bytes that are not cryptographically secure adding more required knowledge in user-land to ensure secure bytes.

Currently the most reliable way to grab pseudo-random bytes across systems is by using either of the libs mentioned above or falling back to a stream of bytes from ''/dev/urandom'' which is OS-specific and can fail when the ''open_basedir'' ini setting is set. This requires user-land apps to write potentially 100's of lines of code to simply generate pseudo-random bytes and there are several caveats that will not generate cryptographically secure bytes. And in some cases no reliable method can be found at all.

See the [[https://github.com/facebook/facebook-php-sdk-v4/tree/master/src/Facebook/PseudoRandomString|Facebook PHP SDK's implementation of a CSPRNG]] in PHP to understand how much code is needed in user-land to simply generate cryptographically secure pseudo-random bytes.

===== Proposal =====
There should be a user-land API to easily return an arbitrary length of cryptographically secure pseudo-random bytes directly from ''arc4random'', ''getrandom'' or ''/dev/urandom'' and work on any supported server configuration or OS.

The initial proposal is to add **two** user-land functions that return the bytes as binary and integer.

<code php>
$randBinary = random_bytes($bytes = 10);

$randomInt = random_int($maxInt = 900);
</code>

===== Backward Incompatible Changes =====
There would be no BC breaks.

===== Proposed PHP Version(s) =====
PHP 7

===== RFC Impact =====
==== To SAPIs ====
This RFC should not impact the SAPI's.

==== To Existing Extensions ====
No existing extensions be affected.

==== To Opcache ====
__TODO Leigh - this one is all yours :)__
Please explain how you have verified your RFC's compatibility with opcache.

==== New Constants ====
There would be no new constants.

==== php.ini Defaults ====
There would be no new php.ini defaults.

===== Open Issues =====
  * Verify Windows support (@auroraeosrose?)
  * Implement support for ''arc4random'' / ''getrandom'' (Leigh)

===== Unaffected PHP Functionality =====
This change should not affect any of the existing ''rand()'' or ''mt_rand()'' functionality.

===== Future Scope =====
The concepts from the RFC could be used to:

   * Deprecate ''mcrypt_create_iv()''
   * Improve ''session_id'' randomness generation

===== Patches and Tests =====
The current WIP patch can be found here: https://github.com/SammyK/php-src/compare/rand-bytes#diff-f1067207e863d8fa568e63446920e7fcR182

===== References =====
None so far.

===== Rejected Features =====
None so far.

===== Changelog =====
   * 0.0: Initial draft - need Leigh's input

===== Acknowledgements =====
Big thanks to Anthony Ferrara, Daniel Lowrey, Leigh, E. Smith and [[http://chat.stackoverflow.com/rooms/11/php|all the kids in the PHP room]] for all the help with this one!
