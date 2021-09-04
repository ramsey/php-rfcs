PHP Requests for Comments (RFCs)
================================

About
-----

This repository is an experiment to export all `RFCs from the PHP wiki <https://wiki.php.net/rfc>`_
into Git, including the change history for each RFC (along with the date and
author of each change).

The raw RFC text files exported from Dokuwiki are stored in
`resources/imported-rfcs/ <https://github.com/ramsey/php-rfcs/tree/main/resources/imported-rfcs>`_.
Each file has a commit history that mirrors that of the changes on Dokuwiki.
Since many edits to the wiki do not include descriptions of the changes, most of
the commit messages here use the phrase "Wiki changes." However, each commit
includes the trailers ``X-Dokuwiki-Revision`` and ``X-Dokuwiki-Slug`` to connect
them to their respective wiki pages and revisions.

Why do this?
------------

Because a wiki is a terrible place to keep track of RFCs and changes to RFCs.
This is for me; it's not intended to replace the wiki.

I plan to expand on these tools to convert the RFCs into Markdown or
reStructuredText. My goal is to automate these conversions to keep them
up-to-date with the latest changes to the wiki.

Requirements
------------

Requirements, other than those listed in ``composer.json``, are:

* `Git <https://www.git-scm.com>`_ 2.30 or later
* `Pandoc <https://pandoc.org>`_ 2.14 or later

Recommended
~~~~~~~~~~~

* `jq <https://stedolan.github.io/jq/>`_ for querying the
  ``resources/metadata-*.json`` data files from the console
* Knowledge of `reStructuredText <https://docutils.sourceforge.io/rst.html>`_

Usage
-----

Run ``composer install`` and then check out the commands available:

.. code:: shell

    ./rfc list

Importing Updates to RFCs
~~~~~~~~~~~~~~~~~~~~~~~~~

For regular imports of RFC updates on the wiki:

.. code:: shell

    ./rfc wiki:crawl
    ./rfc wiki:metadata > resources/metadata-raw.json
    ./rfc rfc:metadata --raw-metadata=resources/metadata-raw.json > resources/metadata-clean.json
    ./rfc rfc:update --clean-metadata=resources/metadata-clean.json

Note that ``wiki:crawl`` will auto-commit any changes to the wiki it finds, but
you will need to manually commit any changes to ``metadata-raw.json``,
``metadata-clean.json``, or any of the files in ``rfcs/``. Review the changes
before committing them to make sure they look correct.

.. NOTE::

    If any new RFCs are detected by ``wiki:crawl``, when running ``rfc:update``,
    the tool will pick the next available RFC number to apply to the RFC. It
    will then add that RFC number to the JSON file for the RFC in
    ``resources/metadata-overrides/`` to reserve it. If you wish to use a
    different RFC number, please update the override file.

Overriding RFC Metadata
~~~~~~~~~~~~~~~~~~~~~~~

To override metadata for an RFC (i.e., title, date, authors, etc.):

1. Edit the appropriate JSON file in ``resources/metadata-overrides/``. If the
   file does not exist, create it.
2. Regenerate the clean metadata file with the command
   ``./rfc rfc:metadata --raw-metadata=resources/metadata-raw.json > resources/metadata-clean.json``.
   This will apply your overrides to the metadata.
3. Update the RFCs with the command
   ``./rfc rfc:update --clean-metadata=resources/metadata-clean.json``
4. Review the changes made and commit them.

Importing RFCs Not Detected During the Crawl
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If you find a page that needs to be imported that isn't picked up with
``wiki:crawl``, you can import it using:

.. code:: shell

    ./rfc.php wiki:save subnamespace:page-name

This command will auto-commit the RFC's entire history.

Use a colon as a separator instead of the forward slash that might appear in
URLs for the page. For example, if the page is at
https://wiki.php.net/rfc/php8/merge_member_symbol_tables, then use
``php8:merge_member_symbol_tables``.

Notes
-----

Types
~~~~~

This project consolidates and standardizes the types of all PHP RFCs to the
following:

*Informational*
  An Informational RFC provides general guidelines or information to the
  PHP community but does not propose a new feature or process for PHP.
  Examples include definitions of terms, release schedules, etc.

*Process*
  A Process RFC describes a process surrounding PHP or proposes a change to a
  process. Process RFCs are like Standards Track RFCs but apply to areas other
  than the PHP language itself. They may propose an implementation, but not to
  PHP's codebase. Examples include procedures, guidelines, changes to the
  decision-making process, and changes to the tools or environment used in PHP.
  Meta-RFCs are always Process RFCs.

*Standards Track*
  A Standards Track RFC describes a new feature or implementation for PHP.
  It may also describe an interoperability standard that will be supported
  outside the standard library for current PHP versions before a subsequent
  RFC adds standard library support in a future version (e.g., reservation
  of type names).

Statuses
~~~~~~~~

This project consolidates and standardizes the statuses of all PHP RFCs to the
following:

*Accepted*
  Accepted RFCs have gone through the discussion and voting phases and have
  been approved for implementation. This status indicates the RFC has not been
  implemented. RFCs may be partially accepted, though their status is simply
  "Accepted." Partially accepted RFCs must clearly describe which parts of the
  RFC will be in force when the RFC is active or implemented.

*Active*
  An active RFC was accepted, and the information, policies, or procedures it
  describes are in full force and considered the best current practices for the
  PHP project. Informational and process RFCs may receive the active status.

*Declined*
  A declined RFC went through the discussion and voting phases and failed to
  receive a 2/3 majority of votes.

*Draft*
  All RFCs begin as drafts and remain as drafts throughout the discussion
  period.

*Implemented*
  An implemented RFC was accepted, and the code or work necessary to fulfill the
  requirements of the RFC is complete. For changes to php-src, this means any
  patches created to fulfill the work have been merged to the main branch.
  Standards track RFCs may receive the implemented status.

*Superseded*
  Accepted, active, and implemented RFCs may be superseded by another RFC. In
  this case, the new RFC takes precedence and the original one is set to the
  superseded status.

  An RFC that updates an accepted, active, or implemented RFC **but does not
  replace it** does not supersede the existing RFC. Rather, the existing RFC
  is still accepted, active, or implemented, but it must explain that it is
  updated by the new RFC. "Updated" is not a status.

*Unknown*
  For historical reasons, this status exists to identify historic RFCs for which
  the status could not be automatically determined. This status must not be
  applied to new RFCs.

*Voting*
  Following the draft state, which includes the discussion phase, RFCs are
  *frozen* during their voting phases. This is the time during which voters may
  vote on the RFC as it exists in its current state.

*Withdrawn*
  If an RFC never proceeds to the voting phase, it may be withdrawn from
  consideration. Withdrawn RFCs are no longer drafts and should not receive
  continued updates from discussion unless they are reopened as drafts.

  Withdrawn RFCs include historically inactive and abandoned draft RFCs.

Interesting jq Queries
~~~~~~~~~~~~~~~~~~~~~~

.. code:: shell

    # List all unique statuses in the raw metadata
    cat resources/metadata-raw.json| jq '[.[] | .status] | unique'

    # List all unique index sections in the raw metadata
    cat resources/metadata-raw.json| jq '[.[] | .section] | unique'

    # List all drafts that have the type "Unknown" in the cleaned metadata
    cat resources/metadata-clean.json| jq '[.[] | select(.Status == "Draft" and .Type == "Unknown")]'

Importing History
~~~~~~~~~~~~~~~~~

Importing all the differences from Dokuwiki and creating separate commits for
each resulted in a repository with commits that were wildly out of order. To
put them in the correct order, here is the process I followed:

.. code:: shell

    # This was the initial command to crawl Dokuwiki and import all
    # of the RFCS, including their histories as separate commits.
    ./rfc wiki:crawl

    # These are the commands I ran to put the commits in the correct
    # order in the repository.
    git checkout --orphan sort-branch
    git rm -rf .
    git commit --allow-empty -m "Initial commit to create a HEAD"
    git log --pretty="format:%at%x09%H%x09%an%x09%ae%x09%aD" main \
        | sort \
        | awk -F"\t" 'OFS="\t" {print $2,$3,$4,$5}' ORS="\t" \
        | xargs -d\\t -n4 bash -c 'GIT_COMMITTER_NAME="$1" GIT_COMMITTER_EMAIL="$2" GIT_COMMITTER_DATE="$3" git cherry-pick --allow-empty --no-gpg-sign "$0"'

    # Delete the main branch and make this branch the new main.
    git branch -D main
    git branch -M main

From this point forward, new runs of ``wiki:crawl`` will create new commits on
top of the existing history for any new changes made to RFCs on the wiki. Since
any new changes are recent, commit order history will be mostly intact.
