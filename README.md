PHP Requests for Comments (RFCs)
================================

About
-----

This repository is an experiment to export all [RFCs from the PHP wiki][rfcs]
into Git, including the change history for each RFC (along with the date and
author of each change).

The raw RFC text files exported from Dokuwiki are stored in
[resources/imported-rfcs/][raw]. Each file has a commit history that mirrors
that of the changes on Dokuwiki. Since many edits to the wiki do not include
descriptions of the changes, most of the commit messages here use the phrase
"Wiki changes." However, each commit includes the trailers `X-Dokuwiki-Revision`
and `X-Dokuwiki-Slug` to connect them to their respective wiki pages and
revisions.

Why do this?
------------

Because a wiki is a terrible place to keep track of RFCs and changes to RFCs.
This is for me; it's not intended to replace the wiki.

I plan to expand on these tools to convert the RFCs into Markdown or
reStructuredText. My goal is to automate these conversions to keep them
up-to-date with the latest changes to the wiki.

Usage
-----

Run `composer install` and then check out the commands available:

```shell
php bin/rfc.php
```

Requirements
------------

Requirements, other than those listed in `composer.json`, are:

* [Git](https://www.git-scm.com) 2.30 or later
* [Pandoc](https://pandoc.org) 2.14 or later

Technical Notes
---------------

Importing all the differences from Dokuwiki and creating separate commits for
each resulted in a repository with commits that were wildly out of order. To
put them in the correct order, here is the process I followed:

```shell
# This was the initial command to crawl Dokuwiki and import all
# of the RFCS, including their histories as separate commits.
php bin/rfc.php wiki:crawl

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
```

From this point forward, new runs of `wiki:crawl` will create new commits on
top of the existing history for any new changes made to RFCs on the wiki. Since
any new changes are recent, commit order history will be mostly intact.


[rfcs]: https://wiki.php.net/rfc
[raw]: https://github.com/ramsey/php-rfcs/tree/main/resources/imported-rfcs
