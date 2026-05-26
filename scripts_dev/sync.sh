#!/bin/bash

cd ..

# Fetch and Pull changes from the upstream repository
git fetch upstream
git pull upstream main

echo "WCCMS sync completed successfully"