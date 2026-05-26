#!/bin/bash

# Rename the remote 'origin' to 'upstream'
git remote rename origin upstream

# Configure the 'upstream' remote to disable pushing to it
git remote set-url --push upstream no_push

echo "WCCMS setup completed successfully"