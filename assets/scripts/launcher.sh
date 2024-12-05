#!/bin/bash

export PATH=$PATH:/usr/local/bin:/usr/bin:/bin

chmod +x /opt/cumanphone/bin/updates.sh
nohup sh /opt/cumanphone/bin/updates.sh &
