# Git History Cleanup Plan

This is an owner-approved maintenance procedure, not an automated task. Do not run it until all collaborators have been informed and a maintenance window is agreed.

1. Make a verified backup or mirror clone of every branch and tag.
2. Rotate any database password, application secret, password, or token that ever appeared in a log or committed config. Treat exposed values as compromised even after deletion.
3. Stop committing runtime content and remove currently tracked copies from the index while keeping local files:

   ```bash
   git rm -r --cached --ignore-unmatch logs log uploads
   git rm --cached --ignore-unmatch .env .auth_secret
   git commit -m "Stop tracking runtime data and secrets"
   ```

4. In a disposable mirror clone, use `git filter-repo` to remove historical paths. Adjust the path list after reviewing every branch and tag:

   ```bash
   git filter-repo --path logs --path log --path uploads --path .env --path .auth_secret --invert-paths
   ```

5. Search the rewritten repository for old credentials, passwords, personal data, logs, and uploads. Inspect all branches and tags, not only the default branch.
6. Have the repository owner review and explicitly approve the rewritten result.
7. Only after approval, force-push the rewritten branches and tags during the maintenance window.
8. Ask every collaborator to delete old clones and re-clone. Old clones can reintroduce removed history.

History cleanup does not revoke leaked credentials. Credential rotation is mandatory and should happen before publishing the repository.
