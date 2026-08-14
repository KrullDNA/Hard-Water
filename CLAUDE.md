# Hard Water

WordPress plugin: **Water Hardness Lookup** (`kdna-water-hardness`), built for
KDNA/Apotheca against `Water_Hardness_Lookup_Project_Brief.docx`.

## Releasing

The version number is the plugin's, and it moves on every change to the
plugin, however small.

1. Bump the version in **both** places, and keep them identical:
   - the `Version:` line in the `kdna-water-hardness/kdna-water-hardness.php`
     header
   - `KDNA_WH_VERSION` in the same file
2. Add a changelog entry to `kdna-water-hardness/README.txt` and update
   `Stable tag:` to match.
3. Repackage, with the version in the filename:

   ```
   rm -f kdna-water-hardness-*.zip
   zip -rq kdna-water-hardness-<version>.zip kdna-water-hardness \
       -x '*.DS_Store' '*/.*'
   ```

   One zip in the repository at a time: the previous one is deleted, not kept
   alongside. Git history is where old releases live.
4. Commit both the source change and the rebuilt zip together, so the zip in
   the tree is always the code beside it.

Semantic versioning: patch for a fix, minor for a feature, major for a break.

`KDNA_WH_DB_VERSION` is the schema's, not the plugin's. It moves only when the
tables change, and it is what triggers the upgrade path on activation.

## Testing

There is no WordPress, MySQL or Elementor in this container. The suites in the
session scratchpad run the plugin's classes against stubs, under PHP 8.4 and
Node, and every one of them turns a PHP notice, warning or deprecation into a
hard failure. Run them all before packaging.
