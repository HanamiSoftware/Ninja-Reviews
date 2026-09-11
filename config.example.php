Options -Indexes

<FilesMatch "^(config\.php|config\.example\.php|README\.txt)$">
    Require all denied
</FilesMatch>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>
