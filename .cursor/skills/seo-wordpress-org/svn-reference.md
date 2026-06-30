# SVN Reference — seo-wordpress

## Initial setup

```bash
# Set SVN password (separate from WordPress.org login)
# https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password

svn co https://plugins.svn.wordpress.org/seo-wordpress ~/seo-wordpress-svn
```

**Username:** WordPress.org username (e.g. `mervinpraison`)

## Repository structure

```
seo-wordpress/
├── trunk/          # Current development (what wordpress.org/trunk serves)
├── tags/X.Y.Z/     # Immutable release snapshots
├── assets/         # banner-1544x500.png, icon-256x256.png, screenshots
└── branches/       # Optional dev branches
```

## Standard release commands

```bash
cd ~/seo-wordpress-svn
svn up

# Copy files from git repo into trunk (example)
cp /path/to/seo-wordpress/includes/class-aiseo-rest.php trunk/includes/
# Edit trunk/seo-wordpress.php version + trunk/readme.txt

svn status trunk/
svn ci trunk/ -m "Release X.Y.Z: description."
svn cp trunk tags/X.Y.Z
svn ci -m "Tagging version X.Y.Z"
```

## Verify deployment

```bash
svn info ~/seo-wordpress-svn/trunk | grep Revision
svn ls https://plugins.svn.wordpress.org/seo-wordpress/tags/
```

Public read does not require auth; commit requires SVN password.

## Troubleshooting

### Working copy locked (E155004)

Caused by interrupted commit. Fix:

```bash
cd ~/seo-wordpress-svn
svn cleanup
# retry commit
```

### Tag scheduled but missing (E155010 / E155033)

Broken local tag from failed `svn cp`. Fix:

```bash
cd ~/seo-wordpress-svn
svn cleanup
svn revert -R tags/X.Y.Z
# If folder missing but still scheduled:
svn status tags/X.Y.Z | awk '/^!/ {print $2}' | xargs svn revert 2>/dev/null
svn revert tags/X.Y.Z 2>/dev/null

# Recreate tag
svn cp trunk tags/X.Y.Z
svn ci -m "Tagging version X.Y.Z"
```

### Tree conflict on old tag (e.g. tags/4.0.16)

Resolve without touching trunk:

```bash
svn resolve --accept working tags/4.0.16
svn ci trunk/ -m "..."   # commit only trunk/
```

### Authentication failed on commit

- Use **SVN password**, not WordPress.org login password
- Read (`svn ls`) is public — auth failure on `svn ci` means wrong password or username
- Regenerate password at profiles.wordpress.org if compromised

### Commit only trunk (avoid tag noise)

```bash
svn ci trunk/ -m "Message"
```

## Asset specifications

| Asset | Size |
|-------|------|
| Banner (retina) | 1544×500 px |
| Banner | 772×250 px |
| Icon (retina) | 256×256 px |
| Icon | 128×128 px |
| Screenshots | 1200×900 px recommended |

```bash
cd ~/seo-wordpress-svn/assets
svn add banner-1544x500.png icon-256x256.png
svn ci -m "Add plugin assets"
```

## Git + SVN combined workflow

```bash
# 1. Develop in git (5.x branch)
git checkout -B release/X.Y.Z origin/main
# edit, test, commit, push

# 2. Sync to SVN trunk
svn up ~/seo-wordpress-svn
rsync -av --exclude-from=.distignore \
  ./ ~/seo-wordpress-svn/trunk/

# 3. Ensure seo-wordpress.php version matches (SVN main file name)
# 4. svn ci trunk + tag as above
```

## Quick reference

```bash
svn up                  # Update working copy
svn status              # Pending changes
svn diff                # View diffs
svn add --force trunk/* # Stage new files
svn revert -R path      # Discard local changes
svn cleanup             # Clear locks
svn cp trunk tags/X.Y.Z # Create release tag
```
