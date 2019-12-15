<VirtualHost *:{{ssl_port}}>
    ServerName {{host_name}}
    ServerAlias www.{{host_name}}
    ServerAdmin {{admin_email}}
    DocumentRoot "{{document_root}}"
    <Directory "{{document_root}}">
        Options Indexes FollowSymLinks Includes ExecCGI
        # IndexOptions FancyIndexing
        # IndexOrderDefault Ascending Size
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "logs/{{host_name}}-error.log"
    TransferLog "logs/{{host_name}}-transfer.log"

    SSLEngine on
    SSLCertificateFile "{{cert_file}}"
    SSLCertificateKeyFile "{{cert_key_file}}"

    <FilesMatch "\.(cgi|shtml|phtml|php)$">
        SSLOptions +StdEnvVars
    </FilesMatch>
    <Directory "{{document_root}}/cgi-bin">
        SSLOptions +StdEnvVars
    </Directory>

    BrowserMatch "MSIE [2-5]" \
             nokeepalive ssl-unclean-shutdown \
             downgrade-1.0 force-response-1.0

    CustomLog "logs/{{host_name}}-ssl_request.log" \
              "%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"
</VirtualHost>