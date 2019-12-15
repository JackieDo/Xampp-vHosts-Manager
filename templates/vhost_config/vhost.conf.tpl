<VirtualHost *:{{host_port}}>
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
    CustomLog "logs/{{host_name}}-access.log" common
</VirtualHost>