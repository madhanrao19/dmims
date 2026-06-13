COMMIT_INSTRUCTIONS

This repository's tests and formatting were updated locally by an assistant. Git is not available in the current shell, so follow these instructions locally to commit and push the changes.

Recommended (PowerShell):

```powershell
cd "d:/Dev/IMS/Source Code/dmims-code"
# Run the helper script (it will check for git and create the branch)
.\finish_changes.ps1
```

Manual commands (PowerShell or any shell):

```powershell
cd "d:/Dev/IMS/Source Code/dmims-code"
# Create branch
git checkout -b fix/filament-admin-auth
# Stage and commit
git add -A
git commit -m "Fix Filament admin auth and tests (follow redirects)"
# Push to remote
git push -u origin fix/filament-admin-auth
```

Notes:
- Ensure `git` is installed and configured with your credentials.
- If the remote `origin` is not set, run `git remote add origin <url>` first.
- If the commit step fails with "nothing to commit", that means the files are already committed locally; verify with `git status` and `git log -n 5`.
- The branch name used by the assistant is `fix/filament-admin-auth`.

If you'd like, I can attempt to run these steps again once `git` is available in the environment, or generate a unified patch file for manual `git apply`.