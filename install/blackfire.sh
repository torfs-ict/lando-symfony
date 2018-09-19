#!/usr/bin/env bash
wget -O - https://packagecloud.io/gpg.key | apt-key add -
echo "deb http://packages.blackfire.io/debian any main" | tee /etc/apt/sources.list.d/blackfire.list
apt-get update
apt-get -yqq install blackfire-agent blackfire-php
echo -e "yes\n$BLACKFIRE_SERVER_ID\n$BLACKFIRE_SERVER_TOKEN" | blackfire-agent --register
/etc/init.d/blackfire-agent restart
echo -e "$BLACKFIRE_CLIENT_ID\n$BLACKFIRE_CLIENT_TOKEN" | blackfire config