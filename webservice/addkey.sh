#!/bin/sh

# Script for adding a public key to the authorized_keys file of a
# particular user. The script takes two input arguments. The first
# defines the username and the second carries the public key

# Check that we received two arguments
if [ $# != 2 ]; then
        echo Usage: $0 USERNAME PUBLIC_KEY
        exit 1
fi

# Check if .ssh directory exists for the user and create it if not
if [ ! -d "/home/$1/.ssh" ]; then
        echo Directory /home/$1/.ssh does not exist...creating directory
        mkdir /home/$1/.ssh
        chown $1:$1 /home/$1/.ssh
fi

# Check if authorized_keys exists
if [ -a "/home/$1/.ssh/authorized_keys" ]; then

        # Check if key is already in authorized_keys file
        DoesExist=`grep -c "$2" /home/$1/.ssh/authorized_keys`

        if [ "$DoesExist" -ne 0 ]; then
                echo Key already exists in authorized_keys file
                exit 1
        fi
fi

echo $2 >> /home/$1/.ssh/authorized_keys

chown $1:$1 /home/$1/.ssh/authorized_keys
