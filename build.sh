#!/bin/bash

zip -r wisecp-synergywholesale-registrar-module.zip . \
    -x .git/\* \
    -x docs/\* \
    -x .vscode/\* \
    -x screenshots/\* \
    -x license-error.html \
    -x logo.jpg \
    -x .gitignore \
    -x .gitattributes \
    -x build.sh
