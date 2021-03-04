# OpenCart SEO URLs
Script to run through OpenCart tables and update products and/or categories to have SEO friendly URLs and generate the redirect rules for htaccess. Does not automatically work on new products/categories, this is a standalone script for updating existing sites / CRON.

## Version Support
It has been tested on 2.3.0.2 and 3.0.3.2, it should be fine for other versions but might want to check the table config to see.

## No VQMOD or OCMOD?
No. I don't overly like OpenCart and I dislike VQ and OC MOD. I don't even know if this is/isn't possible in those and am not really prepared to spend the time finding out.

## Future Plans
None unless someone points out something else it needs.

## Notes
Overly commented because I don't write enough comments.

It is assumed that the script lives in a new directory like /custom/ or /scripts/ or such and as such loads the config using ../ so you may need to alter that.

Update the URL characters that are replaced in the $find and $repl arrays inside the GenerateSeoUrl() function if you aren't happy with what I have used.

Some optional variables can be set for checking / updating / rewrites, check the comments.

The excellent htaccess tester by Made With Love (no affiliation) can be used to test the rewrite rules / make your own. https://htaccess.madewithlove.be/

Rewrite rules are because OpenCart doesn't alter the old product_id= URL to redirect to the new one.
