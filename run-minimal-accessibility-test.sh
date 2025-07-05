#!/bin/bash

# Run with only chromium and headed mode for WSL2
DISPLAY=:0 npm run test:accessibility -- --project=chromium --headed