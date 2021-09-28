.. role:: raw-text(raw)
   :format: html

Index of PHP Requests for Comments (PHP-RFCs)
=============================================

:PHP-RFC: 0000
:Title: Index of PHP Requests for Comments (PHP-RFCs)
:Author: PHP Internals <internals@lists.php.net>
:Status: Active
:Type: Informational

.. contents::

Introduction
------------

This document contains the index of all PHP Requests for Comments (PHP-RFCs).

Index by Category
-----------------

Process PHP-RFCs
~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'process'}) }}

Informational PHP-RFCs
~~~~~~~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'informational'}) }}

Open PHP-RFCs (under consideration)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'open'}) }}

Accepted PHP-RFCs (accepted; may not be implemented yet)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'accepted'}) }}

Implemented PHP-RFCs
~~~~~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'implemented'}) }}

Unknown State (PHP-RFCs for which we can't determine the status)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'unknown'}) }}

Abandoned, Withdrawn, and Declined PHP-RFCs
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

{{ include('rfc-table.rst', {category: 'declined'}) }}

Numerical Index
---------------

{{ include('rfc-table.rst', {category: 'numerical'}) }}

PHP-RFC Types Key
-----------------

I - Informational PHP-RFC
  An Informational RFC provides general guidelines or information to the
  PHP community but does not propose a new feature or process for PHP.
  Examples include definitions of terms, release schedules, etc.

P - Process PHP-RFC
  A Process RFC describes a process surrounding PHP or proposes a change to a
  process. Process RFCs are like Standards Track RFCs but apply to areas other
  than the PHP language itself. They may propose an implementation, but not to
  PHP's codebase. Examples include procedures, guidelines, changes to the
  decision-making process, and changes to the tools or environment used in PHP.
  Meta-RFCs are always Process RFCs.

S - Standards Track PHP-RFC
  A Standards Track RFC describes a new feature or implementation for PHP.
  It may also describe an interoperability standard that will be supported
  outside the standard library for current PHP versions before a subsequent
  RFC adds standard library support in a future version (e.g., reservation
  of type names).

U - Unknown PHP-RFC
  An Unknown RFC is one our automated tooling was unable to determine a type
  for. Once the RFC editors have categorized all historical RFCs, there should
  not be any RFCs with this type.

PHP-RFC Status Definitions
--------------------------

Accepted
  Accepted RFCs have gone through the discussion and voting phases and have
  been approved for implementation. This status indicates the RFC has not been
  implemented. RFCs may be partially accepted, though their status is simply
  "Accepted." Partially accepted RFCs must clearly describe which parts of the
  RFC will be in force when the RFC is active or implemented.

Active
  An active RFC was accepted, and the information, policies, or procedures it
  describes are in full force and considered the best current practices for the
  PHP project. Informational and process RFCs may receive the active status.

Declined
  A declined RFC went through the discussion and voting phases and failed to
  receive a 2/3 majority of votes.

Draft
  All RFCs begin as drafts and remain as drafts throughout the discussion
  period.

Implemented
  An implemented RFC was accepted, and the code or work necessary to fulfill the
  requirements of the RFC is complete. For changes to php-src, this means any
  patches created to fulfill the work have been merged to the main branch.
  Standards track RFCs may receive the implemented status.

Superseded
  Accepted, active, and implemented RFCs may be superseded by another RFC. In
  this case, the new RFC takes precedence and the original one is set to the
  superseded status.

  An RFC that updates an accepted, active, or implemented RFC **but does not
  replace it** does not supersede the existing RFC. Rather, the existing RFC
  is still accepted, active, or implemented, but it must explain that it is
  updated by the new RFC. "Updated" is not a status.

Unknown
  For historical reasons, this status exists to identify historic RFCs for which
  the status could not be automatically determined. This status must not be
  applied to new RFCs.

Voting
  Following the draft state, which includes the discussion phase, RFCs are
  *frozen* during their voting phases. This is the time during which voters may
  vote on the RFC as it exists in its current state.

Withdrawn
  If an RFC never proceeds to the voting phase, it may be withdrawn from
  consideration. Withdrawn RFCs are no longer drafts and should not receive
  continued updates from discussion unless they are reopened as drafts.

  Withdrawn RFCs include historically inactive and abandoned draft RFCs.
