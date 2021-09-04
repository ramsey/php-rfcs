#!/bin/bash
#
# This script does the following:
#
# 1. Checks out a new "auto-rfcs/" branch, named based on the current date.
# 2. Crawls the PHP wiki, adding any new changes to this repository.
# 3. Updates the meta data and reStructuredText RFCs in this repository, based
#    on the changes found in the wiki data.
# 4. Pushes the new branch to the remote repository.
#

author_name="github-actions[bot]"
author_email="41898282+github-actions[bot]@users.noreply.github.com"
branch_prefix="auto-rfcs/"
commit_message="auto-rfcs: Update RFCs"
current_date=$(date +"%Y-%m-%d")
remote_repo="https://${GITHUB_ACTOR}:${INPUT_GITHUB_TOKEN}@github.com/${GITHUB_REPOSITORY}.git"

# Find the directory where this script is located (not where it's called from).
__dir__="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

cd "${__dir__}/../" || exit 1

git checkout -b "${branch_prefix}${current_date}"

#./rfc wiki:crawl

has_changes=$(git rev-list main..@ --count)

#if [[ "$has_changes" -eq "0" ]]; then
#    echo "No changes detected to the wiki since the last update. Exiting."
#    exit 0
#fi

#./rfc wiki:metadata > resources/metadata-raw.json
#./rfc rfc:metadata --raw-metadata=resources/metadata-raw.json > resources/metadata-clean.json
#./rfc rfc:update --clean-metadata=resources/metadata-clean.json

git add .

# If this returns a non-zero exit code, then there are changes to commit.
if ! git diff-index --quiet HEAD; then
    export GIT_COMMITTER_NAME="${author_name}"
    export GIT_COMMITTER_EMAIL="${author_email}"
    git commit -m "${commit_message}" --author="${author_name} <${author_email}>"
fi

git push "${remote_repo}" HEAD
