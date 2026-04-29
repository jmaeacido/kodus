install node JS

install npm

create a service in /etc/systemd/system and paste this code

=================================================================
[Unit]
Description=Node.js Socket.IO Server
After=network.target

[Service]
# Adjust paths and user/group as needed
User=www-data
Group=www-data
WorkingDirectory=/opt/apps/socketio-server
ExecStart=/usr/bin/node /opt/apps/socketio-server/server.js

# Restart automatically if the process crashes
Restart=always
RestartSec=5
StopTimeoutSec=60

# Optional: redirect logs to syslog or journald
StandardOutput=journal
StandardError=journal
SyslogIdentifier=node-socket-server

[Install]
WantedBy=multi-user.target
================================================================

name the service and run it via: systemctl start <name_of_service.service>
