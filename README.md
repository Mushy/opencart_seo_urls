# OpenCart SEO URLs
Script to run through OpenCart tables and update products to have SEO friendly URLs and generate the redirect rules for htaccess. Does not automatically work on new products, this is a standalone script for updating existing sites.

Based on OpenCart Version 3.0.3.2 when written, should be fine for other versions but might want to check the table config to see.

Overly commented because I don't write enough comments.

Update the URL characters that are replaced in the $find and $repl arrays if you aren't happy with what I have used.

Some optional variables can be set for checking / updating / rewrites, check the comments.

The excellent htaccess tester by Made With Love (no affiliation) can be used to test the rewrite rules.
https://htaccess.madewithlove.be/
